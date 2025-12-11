<?php

namespace Backpack\Articles\app\Models;

use Backpack\Articles\app\Events\ArticleChanged;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;
use Backpack\Tag\app\Traits\Taggable;
use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use ParabellumKoval\BackpackImages\Traits\HasImages;
use Backpack\Helpers\Traits\FormatsUniqAttribute;

// FACTORY
use Backpack\Articles\database\factories\ArticleFactory;

use Backpack\Articles\app\Traits\SlicesTrait;

class Article extends Model
{
    use CrudTrait;
    use HasFactory;
    use HasTranslations;
    use Sluggable;
    use SluggableScopeHelpers;
    use HasImages;
    use Taggable;
    use FormatsUniqAttribute;
    use SlicesTrait;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'ak_articles';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    // protected $fillable = [];
    // protected $hidden = [];
    protected $dates = ['published_at'];

    protected $casts = [
      'extras' => 'array',
      'seo' => 'array',
      'images' => 'array',
      'countries' => 'array',
    ];

    protected $fakeColumns = [
      'seo',
      'extras',
    ];

    protected $translatable = ['title', 'excerpt', 'seo'];
    protected array $additional_translatable = ['content'];

    protected array $pendingContentTranslations = [];
    
    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */
    public function toArray(){
	    return [
		    'id' => $this->id,
		    'title' => $this->title,
		    'slug' => $this->slug,
		    'excerpt' => $this->excerpt,
		    'image' => $this->getFirstImageForApi(),
            'time' =>  $this->time,
	    ];
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
      return ArticleFactory::new();
    }
    
    protected static function boot()
    {
        parent::boot();

        static::saved(function (Article $article) {
            $article->syncPendingContentTranslations();
            event(ArticleChanged::for($article, 'saved'));
        });

        static::deleted(function (Article $article) {
            event(ArticleChanged::for($article, 'deleted'));
        });
    }
    
    public function clearGlobalScopes()
    {
        static::$globalScopes = [];
    }

    
    public static function imageProviderName(?string $attribute = null): string
    {
        return 'local';
    }

    public static function imageStorageFolder(?string $attribute = null): string
    {
        return 'articles';
    }


    public static function imageCollections(): array
    {
        return [
            static::imageAttributeName() => [
                'folder' => static::imageStorageFolder(),
            ],
        ];
    }

    // public static function imageFieldPrefix(): string
    // {
    //     return (string) config('services.cdn.articles_url', '/');
    // }

    public function sluggable():array
    {
        return [
            'slug' => [
                'source' => 'slug_or_title',
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function contents(): HasMany
    {
        return $this->hasMany(ArticleContent::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
    public function scopePublished($query)
    {
      return $query->where('status', 'PUBLISHED');
    }

    public function scopeAvailableInRegion(Builder $query, ?string $region): Builder
    {
        $region = static::normalizeRegionCode($region);

        if ($region === null) {
            return $query;
        }

        $column = $query->qualifyColumn('countries');
        $emptyClause = sprintf('JSON_LENGTH(%s) = 0', $column);

        return $query->where(function (Builder $builder) use ($column, $emptyClause, $region) {
            $builder->whereNull($column)
                ->orWhereRaw($emptyClause)
                ->orWhereJsonContains($column, $region);
        });
    }

    public function scopeWithContentLocales(Builder $query, array|string|null $locales = null): Builder
    {
        $locales = static::normalizeContentLocales($locales);

        return $query->with(['contents' => function ($relation) use ($locales) {
            if ($locales !== []) {
                $relation->whereIn('lang', $locales);
            }
        }]);
    }

    /**
     * @param  Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function addRegionAvailabilityClause($query, string $qualifiedColumn, ?string $region): void
    {
        $region = static::normalizeRegionCode($region);

        if ($region === null) {
            return;
        }

        $emptyClause = sprintf('JSON_LENGTH(%s) = 0', $qualifiedColumn);

        $query->where(function ($builder) use ($qualifiedColumn, $emptyClause, $region) {
            $builder->whereNull($qualifiedColumn)
                ->orWhereRaw($emptyClause)
                ->orWhereJsonContains($qualifiedColumn, $region);
        });
    }

    protected static function normalizeContentLocales(array|string|null $locales): array
    {
        if ($locales === null) {
            $locales = [app()->getLocale(), config('app.fallback_locale')];
        }

        return array_values(array_unique(array_filter(Arr::wrap($locales))));
    }

    protected static function normalizeRegionCode(?string $region): ?string
    {
        if (! is_string($region)) {
            return null;
        }

        $region = strtolower(trim($region));

        if ($region === '') {
            return null;
        }

        if ($region === 'global') {
            $alias = config('dress.store.global_region_code', 'zz');

            if (is_string($alias) && $alias !== '') {
                $normalized = strtolower(trim($alias));

                if ($normalized !== '') {
                    return $normalized;
                }
            }
        }

        return $region;
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */
    public function getUniqStringAttribute(): string
    {
        $countries = is_array($this->countries) ? implode(', ', $this->countries) : null;

        return $this->formatUniqString([
            '#'.$this->id,
            $this->title,
            $this->slug,
            sprintf('status: %s', $this->status ?? '-'),
            $this->published_at ? 'published '.$this->published_at->format('Y-m-d H:i') : null,
            $countries ? 'countries: '.$countries : null,
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $countries = is_array($this->countries) ? implode(', ', $this->countries) : null;
        $headline = $this->formatUniqString([
            '#'.$this->id,
            $this->title,
        ]);

        return $this->formatUniqHtml($headline, [
            $this->slug,
            sprintf('status: %s', $this->status ?? '-'),
            $this->published_at ? 'published '.$this->published_at->format('Y-m-d H:i') : null,
            $countries ? 'countries: '.$countries : null,
        ]);
    }

    /**
     * getSlugOrTitleAttribute
     *
     * @return void
     */
    public function getSlugOrTitleAttribute()
    {
        if ($this->slug != '') {
            return $this->slug;
        }
        return $this->title;
    }

    public function getTimeAttribute() {
        return $this->extras['reading_time_minutes'] ?? null;
    }
    public function getContentAttribute(): ?string
    {
        $attempted = [];
        $content = $this->resolveContentTranslation($this->resolveContentLocales(), $attempted);

        if ($content !== null) {
            return $content;
        }

        if ($this->shouldFallbackToAnyContentLocale()) {
            return $this->getAnyContentTranslation($attempted);
        }

        return null;
    }

    public function getContentForLocale(?string $locale = null, bool $useFallback = true): ?string
    {
        $attempted = [];

        $preferredLocales = $locale
            ? [$locale]
            : [$this->getLocale(), backpack_translatable_request_locale(), app()->getLocale()];

        $content = $this->resolveContentTranslation($preferredLocales, $attempted);

        if ($content !== null || ! $useFallback) {
            return $content;
        }

        $fallback = $this->getFallbackContentLocale();

        if ($fallback && ! in_array($fallback, $attempted, true)) {
            $content = $this->resolveContentTranslation([$fallback], $attempted);

            if ($content !== null) {
                return $content;
            }
        }

        if ($this->shouldFallbackToAnyContentLocale()) {
            return $this->getAnyContentTranslation($attempted);
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
    public function setContentAttribute($value): void
    {
        if (is_array($value)) {
            foreach ($value as $locale => $translation) {
                $locale = is_string($locale) ? trim($locale) : null;

                if (! $locale) {
                    continue;
                }

                $this->setContentTranslation($locale, $translation);
            }

            return;
        }

        $locale = $this->getLocale() ?: backpack_translatable_request_locale();

        if (! $locale) {
            $locale = app()->getLocale();
        }

        if (! $locale) {
            $locale = config('app.fallback_locale', 'en');
        }

        if (! $locale) {
            return;
        }

        $this->setContentTranslation($locale, $value);
    }

    public function setContentTranslation(string $locale, ?string $value): self
    {
        $locale = trim($locale);

        if ($locale === '') {
            return $this;
        }

        $this->pendingContentTranslations[$locale] = $value;

        return $this;
    }

    protected function resolveContentLocales(): array
    {
        return static::normalizeContentLocales([
            $this->getLocale(),
            backpack_translatable_request_locale(),
            app()->getLocale(),
            config('app.fallback_locale'),
        ]);
    }

    protected function resolveContentTranslation(array $locales, array &$attempted): ?string
    {
        $locales = static::normalizeContentLocales($locales);

        foreach ($locales as $locale) {
            $attempted[] = $locale;

            $entry = $this->findContentEntryForLocale($locale);

            if ($entry && $this->hasNonEmptyContentValue($entry->content)) {
                return $entry->content;
            }
        }

        return null;
    }

    protected function findContentEntryForLocale(string $locale): ?ArticleContent
    {
        if ($locale === '') {
            return null;
        }

        if ($this->relationLoaded('contents')) {
            return $this->contents->firstWhere('lang', $locale);
        }

        if (! $this->exists) {
            return null;
        }

        return $this->contents()->where('lang', $locale)->first();
    }

    protected function shouldFallbackToAnyContentLocale(): bool
    {
        if (method_exists($this, 'backpackShouldFallbackToAnyLocale')) {
            return $this->backpackShouldFallbackToAnyLocale();
        }

        return true;
    }

    protected function getFallbackContentLocale(): ?string
    {
        return config('app.fallback_locale');
    }

    protected function getAnyContentTranslation(array $attemptedLocales = []): ?string
    {
        $attempted = array_values(array_unique(array_filter(array_map(function ($locale) {
            return is_string($locale) ? trim($locale) : null;
        }, $attemptedLocales))));

        if ($this->relationLoaded('contents')) {
            foreach ($this->contents as $entry) {
                if ($entry && $this->hasNonEmptyContentValue($entry->content)) {
                    return $entry->content;
                }

                if ($entry && is_string($entry->lang)) {
                    $attempted[] = trim($entry->lang);
                }
            }

            $attempted = array_values(array_unique(array_filter($attempted)));
        }

        if (! $this->exists) {
            return null;
        }

        $query = $this->contents()->newQuery();

        if ($attempted !== []) {
            $query->whereNotIn('lang', $attempted);
        }

        $entries = $query->orderBy('lang')->get();

        foreach ($entries as $entry) {
            if ($this->hasNonEmptyContentValue($entry->content)) {
                return $entry->content;
            }
        }

        return null;
    }

    protected function hasNonEmptyContentValue($value): bool
    {
        return $this->calculateTranslationValueLength($value) > 0;
    }

    protected function syncPendingContentTranslations(): void
    {
        if ($this->pendingContentTranslations === [] || ! $this->exists) {
            return;
        }

        foreach ($this->pendingContentTranslations as $locale => $content) {
            if ($content === null) {
                $this->contents()
                    ->where('lang', $locale)
                    ->delete();

                continue;
            }

            $this->contents()->updateOrCreate(
                ['lang' => $locale],
                ['content' => $content]
            );
        }

        $this->pendingContentTranslations = [];
        $this->unsetRelation('contents');
    }

    /**
     * Provide translation diagnostics for Article content blocks.
     *
     * @return array<string, array{filled: bool, length: int}>
     */
    public function getContentTranslationLocalesState(): array
    {
        $available = $this->getAvailableLocales();
        if (is_array($available) && $available !== []) {
            $locales = array_keys($available);
        } else {
            $locales = $this->getTranslatableLocaleKeys();
        }

        $this->loadMissing('contents');
        $contents = $this->relationLoaded('contents') ? $this->contents : collect();

        $state = [];

        foreach ($locales as $locale) {
            $entry = $contents->firstWhere('lang', $locale);
            $value = $entry->content ?? null;
            $length = $this->calculateTranslationValueLength($value);

            $state[$locale] = [
                'filled' => $length > 0,
                'length' => $length,
            ];
        }

        return $state;
    }

    /*
    |--------------------------------------------------------------------------
    | SERVICE OPERATION
    |--------------------------------------------------------------------------
    */
    public function getServiceMergeConfiguration(): array
    {
        return [
            'label' => 'Слияние статьи',
            'description' => 'Перенесите переводы и связанные данные текущей статьи в другую запись.',
            'delete_source_default' => true,
            'candidate_search' => ['title', 'slug', 'id'],
            'fields' => [
                'title' => [
                    'label' => 'Название',
                    'strategy' => 'translations',
                    'default' => true,
                    'help' => 'Дополняет переводы и перезаписывает их при включённом Force.',
                ],
                'excerpt' => [
                    'label' => 'Краткое описание',
                    'strategy' => 'translations',
                    'default' => true,
                ],
                'seo' => [
                    'label' => 'SEO',
                    'strategy' => 'translations',
                    'default' => true,
                    'help' => 'Объединяет локализованные SEO-блоки.',
                ],
                'countries' => [
                    'label' => 'Страны',
                    'strategy' => 'append',
                    'help' => 'Список стран объединяется без дубликатов.',
                ],
                'content' => [
                    'label' => 'Контент',
                    'handler' => 'mergeContentFromServiceOperation',
                    'default' => true,
                    'help' => 'Переносит переводы блоков контента (ArticleContent).',
                ],
            ],
        ];
    }

    public function mergeContentFromServiceOperation(self $source, array $payload = []): void
    {
        $force = (bool) ($payload['force'] ?? false);

        $this->loadMissing('contents');
        $source->loadMissing('contents');

        $targetContents = $this->relationLoaded('contents') ? $this->contents : collect();
        $sourceContents = $source->relationLoaded('contents') ? $source->contents : collect();

        foreach ($sourceContents as $translation) {
            $locale = $translation->lang ?? null;
            $value = $translation->content ?? null;

            if (! is_string($locale) || trim($locale) === '') {
                continue;
            }

            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            $existing = $targetContents->firstWhere('lang', $locale);
            $hasValue = $existing && trim((string) $existing->content) !== '';

            if ($hasValue && ! $force) {
                continue;
            }

            $this->setContentTranslation($locale, $value);
        }
    }
}
