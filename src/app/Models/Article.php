<?php

namespace Backpack\Articles\app\Models;

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

// FACTORY
use Backpack\Articles\database\factories\ArticleFactory;

class Article extends Model
{
    use CrudTrait;
    use HasFactory;
    use HasTranslations;
    use Sluggable;
    use SluggableScopeHelpers;
    use HasImages;
    use Taggable;

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
        $tags = $this->relationLoaded('tags')
            ? $this->tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'text' => $tag->text,
                ];
            })->toArray()
            : [];
	    return [
		    'id' => $this->id,
		    'title' => $this->title,
		    'slug' => $this->slug,
		    'excerpt' => $this->excerpt,
		    'content' => $this->content,
		    'image' => $this->getFirstImageForApi(),
            'seo' => $this->seo,
            'tags' => $tags,
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

    public function scopeAvailableInLocale(Builder $query, string $locale): Builder
    {
        if ($locale === '') {
            return $query;
        }

        static::addTranslationAvailabilityClause($query, $query->qualifyColumn('title'), $locale);

        return $query;
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
    public static function addTranslationAvailabilityClause($query, string $qualifiedColumn, string $locale): void
    {
        if ($locale === '') {
            return;
        }

        [$clause, $bindings] = static::translationAvailabilityClause($qualifiedColumn, $locale);
        $query->whereRaw($clause, $bindings);
    }

    protected static function translationAvailabilityClause(string $qualifiedColumn, string $locale): array
    {
        $path = static::jsonPathForLocale($locale);
        $clause = "(JSON_EXTRACT({$qualifiedColumn}, ?) IS NOT NULL"
            . " AND JSON_UNQUOTE(JSON_EXTRACT({$qualifiedColumn}, ?)) <> '')";

        return [$clause, [$path, $path]];
    }

    protected static function jsonPathForLocale(string $locale): string
    {
        $normalized = str_replace('"', '\"', $locale);

        return '$."'.$normalized.'"';
    }

    protected static function normalizeContentLocales(array|string|null $locales): array
    {
        if ($locales === null) {
            $locales = [app()->getLocale(), config('app.fallback_locale')];
        }

        return array_values(array_unique(array_filter(Arr::wrap($locales))));
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

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
        $locales = $this->resolveContentLocales();

        foreach ($locales as $locale) {
            $content = $this->getContentForLocale($locale, false);

            if ($content !== null) {
                return $content;
            }
        }

        return null;
    }

    public function getContentForLocale(?string $locale = null, bool $useFallback = true): ?string
    {
        $locale = $locale ?: $this->getLocale();

        if (! $locale) {
            $locale = app()->getLocale();
        }

        if (! $locale) {
            $locale = config('app.fallback_locale');
        }

        if (! $locale) {
            return null;
        }

        $entry = $this->findContentEntryForLocale($locale);

        if ($entry) {
            return $entry->content;
        }

        if (! $useFallback) {
            return null;
        }

        $fallback = config('app.fallback_locale');

        if ($fallback && $fallback !== $locale) {
            $entry = $this->findContentEntryForLocale($fallback);

            if ($entry) {
                return $entry->content;
            }
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

    // public function setCountriesAttribute($value): void
    // {
    //     if ($value === null) {
    //         $this->attributes['countries'] = null;

    //         return;
    //     }

    //     if (is_string($value)) {
    //         $decoded = json_decode($value, true);
    //         $value = is_array($decoded) ? $decoded : [$value];
    //     }

    //     if (! is_array($value)) {
    //         $value = [$value];
    //     }

    //     $normalized = array_values(array_unique(array_filter(array_map(function ($code) {
    //         return is_string($code) ? Str::lower(trim($code)) : null;
    //     }, $value))));

    //     $this->attributes['countries'] = $normalized === [] ? null : json_encode($normalized, JSON_UNESCAPED_UNICODE);
    // }

    // public function getCountriesAttribute($value): array
    // {
    //     if ($value === null) {
    //         return [];
    //     }

    //     if (is_array($value)) {
    //         return $value;
    //     }

    //     $decoded = json_decode($value, true);

    //     return is_array($decoded) ? $decoded : [];
    // }
}
