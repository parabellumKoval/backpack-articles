<?php

namespace Backpack\Articles\app\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;
use Backpack\Helpers\Traits\FormatsUniqAttribute;
use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ArticleCategory extends Model
{
    use CrudTrait;
    use HasTranslations;
    use Sluggable;
    use SluggableScopeHelpers;
    use FormatsUniqAttribute;

    protected $table = 'ak_article_categories';

    protected $guarded = ['id'];

    protected $casts = [
        'storefronts' => 'array',
        'is_active' => 'boolean',
    ];

    protected $translatable = ['name'];

    protected static function booted(): void
    {
        static::saving(function (self $category) {
            if ((int) ($category->parent_id ?? 0) === (int) $category->getKey()) {
                $category->parent_id = null;
            }
        });

        static::saved(function () {
            static::rebuildTree();
        });

        static::deleting(function (self $category) {
            $replacementParentId = $category->parent_id ?: null;

            static::withoutEvents(function () use ($category, $replacementParentId) {
                static::query()
                    ->where('parent_id', $category->getKey())
                    ->update(['parent_id' => $replacementParentId]);

                $fallbackCategoryId = static::fallbackCategoryId($category->getKey(), $replacementParentId);
                if ($fallbackCategoryId !== null) {
                    Article::query()
                        ->where('category_id', $category->getKey())
                        ->update(['category_id' => $fallbackCategoryId]);
                }
            });
        });

        static::deleted(function () {
            static::rebuildTree();
        });
    }

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'slug_or_name',
            ],
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'category_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1);
    }

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function getSlugOrNameAttribute()
    {
        if ($this->slug != '') {
            return $this->slug;
        }

        return $this->name;
    }

    public function getStorefrontsListAttribute(): ?array
    {
        $codes = $this->prepareStorefrontCodes($this->storefronts ?? []);
        if ($codes === []) {
            return null;
        }

        $options = static::storefrontOptions();

        return array_values(array_map(function ($code) use ($options) {
            return $options[$code] ?? $code;
        }, $codes));
    }

    public function getAdminStorefrontsLabel(): string
    {
        $explicit = $this->storefrontsList;
        if ($explicit && $explicit !== []) {
            return implode(', ', $explicit);
        }

        $effective = $this->effective_storefronts;
        if (empty($effective)) {
            return 'Все';
        }

        $options = static::storefrontOptions();
        $labels = array_map(function ($code) use ($options) {
            return $options[$code] ?? $code;
        }, $effective);

        return implode(', ', $labels).' (inherited)';
    }

    public function getEffectiveStorefrontsAttribute(): ?array
    {
        return $this->resolveEffectiveStorefronts();
    }

    public function getUniqTitleAttribute(): string
    {
        $chain = $this->parentChain()->pluck('name')->filter()->values()->all();
        $label = implode(' -> ', $chain);

        return trim(sprintf('id: %d %s', $this->id, $label !== '' ? '-> '.$label : ''));
    }

    public function getUniqStringAttribute(): string
    {
        return $this->formatUniqString([
            $this->uniqTitle,
            $this->slug,
            sprintf('status: %s', $this->is_active ? 'active' : 'hidden'),
            $this->getAdminStorefrontsLabel(),
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        return $this->formatUniqHtml($this->uniqTitle, [
            $this->slug,
            sprintf('status: %s', $this->is_active ? 'active' : 'hidden'),
            $this->getAdminStorefrontsLabel(),
        ]);
    }

    public function setStorefrontsAttribute($value): void
    {
        $codes = $this->prepareStorefrontCodes($value);
        $this->attributes['storefronts'] = $codes === [] ? null : json_encode($codes);
    }

    public function parentChain()
    {
        $chain = collect([$this]);
        $current = $this;

        while ($current->parent) {
            $current = $current->parent;
            $chain->push($current);
        }

        return $chain->reverse()->values();
    }

    public function collectNodeIds(): array
    {
        $ids = [$this->getKey()];

        foreach ($this->children()->orderBy('lft')->orderBy('id')->get() as $child) {
            $ids = array_merge($ids, $child->collectNodeIds());
        }

        return array_values(array_unique(array_filter($ids)));
    }

    public function resolveEffectiveStorefronts(): ?array
    {
        if (!static::isStorefrontEnabled()) {
            return null;
        }

        $current = $this;

        while ($current instanceof self) {
            $explicit = $current->prepareStorefrontCodes($current->storefronts ?? []);
            if ($explicit !== []) {
                return $explicit;
            }

            $current = $current->relationLoaded('parent')
                ? ($current->getRelation('parent') instanceof self ? $current->getRelation('parent') : null)
                : $current->parent()->first();
        }

        if (static::applyUnassignedToDefaultStorefront()) {
            return [static::defaultStorefront()];
        }

        return null;
    }

    public function isAvailableForStorefront(?string $storefront = null): bool
    {
        $storefront = static::resolveStorefront($storefront);

        if ($storefront === null) {
            return true;
        }

        $allowed = $this->resolveEffectiveStorefronts();

        return $allowed === null || in_array($storefront, $allowed, true);
    }

    public static function visibleIdsForStorefront(?string $storefront = null): array
    {
        $storefront = static::resolveStorefront($storefront);

        if (!Schema::hasTable('ak_article_categories')) {
            return [];
        }

        if ($storefront === null) {
            return static::query()->active()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $rows = static::query()
            ->select(['id', 'parent_id', 'storefronts', 'is_active', 'lft'])
            ->active()
            ->orderBy('lft')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $rowsById = [];
        $childrenByParent = [];
        $visibleIds = [];
        $visited = [];

        foreach ($rows as $row) {
            $rowId = (int) $row->id;
            $parentId = $row->parent_id !== null ? (int) $row->parent_id : null;
            $rowsById[$rowId] = $row;
            $childrenByParent[$parentId ?? 0][] = $rowId;
        }

        $roots = collect($rowsById)
            ->keys()
            ->filter(function ($rowId) use ($rowsById) {
                $parentId = $rowsById[$rowId]->parent_id;

                return $parentId === null || !isset($rowsById[(int) $parentId]);
            })
            ->values()
            ->all();

        $walk = function (int $rowId, ?array $parentStorefronts) use (&$walk, &$visited, &$visibleIds, $rowsById, $childrenByParent, $storefront): void {
            if (isset($visited[$rowId]) || !isset($rowsById[$rowId])) {
                return;
            }

            $visited[$rowId] = true;
            /** @var self $row */
            $row = $rowsById[$rowId];

            $explicit = $row->prepareStorefrontCodes($row->storefronts ?? []);

            if ($explicit !== []) {
                $allowed = $explicit;
            } elseif ($row->parent_id !== null) {
                $allowed = $parentStorefronts;
            } elseif (static::applyUnassignedToDefaultStorefront()) {
                $allowed = [static::defaultStorefront()];
            } else {
                $allowed = null;
            }

            if ($allowed === null || in_array($storefront, $allowed, true)) {
                $visibleIds[] = $rowId;
            }

            foreach ($childrenByParent[$rowId] ?? [] as $childId) {
                $walk($childId, $allowed);
            }
        };

        foreach ($roots as $rootId) {
            $walk((int) $rootId, null);
        }

        foreach (array_keys($rowsById) as $rowId) {
            if (!isset($visited[$rowId])) {
                $walk((int) $rowId, null);
            }
        }

        return array_values(array_unique($visibleIds));
    }

    public static function expandIdsToSubtree(array $ids = [], array $slugs = []): array
    {
        if (!Schema::hasTable('ak_article_categories')) {
            return array_values(array_unique(array_map('intval', $ids)));
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($id) => $id > 0)));
        $slugs = array_values(array_unique(array_filter(array_map(function ($slug) {
            return is_string($slug) ? trim($slug) : null;
        }, $slugs))));

        if ($ids === [] && $slugs === []) {
            return [];
        }

        $query = static::query();

        if ($ids !== [] && $slugs !== []) {
            $query->where(function (Builder $builder) use ($ids, $slugs) {
                $builder->whereIn('id', $ids)->orWhereIn('slug', $slugs);
            });
        } elseif ($ids !== []) {
            $query->whereIn('id', $ids);
        } else {
            $query->whereIn('slug', $slugs);
        }

        $nodeIds = [];

        foreach ($query->get() as $category) {
            $nodeIds = array_merge($nodeIds, $category->collectNodeIds());
        }

        return array_values(array_unique(array_filter(array_map('intval', $nodeIds))));
    }

    public static function optionsForSelect(?int $exceptId = null): array
    {
        if (!Schema::hasTable('ak_article_categories')) {
            return [];
        }

        $query = static::query()->orderBy('lft')->orderBy('id');

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->get()->mapWithKeys(function (self $category) {
            return [$category->id => $category->uniqTitle];
        })->all();
    }

    public static function storefrontOptions(): array
    {
        if (class_exists(\Backpack\Store\app\Services\Store::class)
            && \Backpack\Store\app\Services\Store::isStorefrontEnabled()) {
            return \Backpack\Store\app\Services\Store::storefrontOptions();
        }

        $options = config('articles.storefront.values', []);
        if (!is_array($options)) {
            return [];
        }

        $normalized = [];

        foreach ($options as $key => $value) {
            if (is_array($value)) {
                $code = static::normalizeStorefrontCode($value['code'] ?? $key);
                $label = (string) ($value['label'] ?? $code);
            } else {
                $code = static::normalizeStorefrontCode(is_int($key) ? (string) $value : (string) $key);
                $label = (string) $value;
            }

            if ($code === null) {
                continue;
            }

            $normalized[$code] = $label !== '' ? $label : ucfirst($code);
        }

        $default = static::defaultStorefront();
        if ($default !== null && !isset($normalized[$default])) {
            $normalized[$default] = ucfirst($default);
        }

        return $normalized;
    }

    protected static function resolveStorefront(?string $storefront = null): ?string
    {
        if (!static::isStorefrontEnabled()) {
            return null;
        }

        if ($storefront !== null) {
            return static::normalizeStorefrontCode($storefront);
        }

        if (app()->bound('request')) {
            $requestStorefront = request()->get(static::storefrontRequestKey())
                ?? request()->header(static::storefrontHeaderName());

            if (is_string($requestStorefront) && $requestStorefront !== '') {
                return static::normalizeStorefrontCode($requestStorefront);
            }
        }

        return static::defaultStorefront();
    }

    protected static function isStorefrontEnabled(): bool
    {
        if (class_exists(\Backpack\Store\app\Services\Store::class)
            && method_exists(\Backpack\Store\app\Services\Store::class, 'isStorefrontEnabled')) {
            return \Backpack\Store\app\Services\Store::isStorefrontEnabled();
        }

        return (bool) config('articles.storefront.enabled', false);
    }

    protected static function storefrontRequestKey(): string
    {
        if (class_exists(\Backpack\Store\app\Services\Store::class)
            && method_exists(\Backpack\Store\app\Services\Store::class, 'storefrontRequestKey')) {
            return \Backpack\Store\app\Services\Store::storefrontRequestKey();
        }

        return 'storefront';
    }

    protected static function storefrontHeaderName(): string
    {
        if (class_exists(\Backpack\Store\app\Services\Store::class)
            && method_exists(\Backpack\Store\app\Services\Store::class, 'storefrontHeaderName')) {
            return \Backpack\Store\app\Services\Store::storefrontHeaderName();
        }

        return 'X-Storefront';
    }

    protected static function defaultStorefront(): ?string
    {
        if (class_exists(\Backpack\Store\app\Services\Store::class)
            && method_exists(\Backpack\Store\app\Services\Store::class, 'defaultStorefront')
            && \Backpack\Store\app\Services\Store::isStorefrontEnabled()) {
            return \Backpack\Store\app\Services\Store::defaultStorefront();
        }

        return static::normalizeStorefrontCode(config('articles.storefront.default', 'main')) ?? 'main';
    }

    protected static function applyUnassignedToDefaultStorefront(): bool
    {
        if (class_exists(\Backpack\Store\app\Services\Store::class)
            && method_exists(\Backpack\Store\app\Services\Store::class, 'applyUnassignedCategoriesToDefaultStorefront')
            && \Backpack\Store\app\Services\Store::isStorefrontEnabled()) {
            return \Backpack\Store\app\Services\Store::applyUnassignedCategoriesToDefaultStorefront();
        }

        return (bool) config('articles.storefront.apply_unassigned_to_default', true);
    }

    protected static function normalizeStorefrontCode(?string $storefront): ?string
    {
        if (class_exists(\Backpack\Store\app\Services\Store::class)) {
            return \Backpack\Store\app\Services\Store::normalizeStorefrontCode($storefront);
        }

        if (!is_string($storefront)) {
            return null;
        }

        $normalized = strtolower(trim($storefront));
        $normalized = preg_replace('/[^a-z0-9_-]/', '', $normalized);

        return $normalized !== '' ? $normalized : null;
    }

    protected function prepareStorefrontCodes($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = $decoded === null && json_last_error() !== JSON_ERROR_NONE ? [$value] : $decoded;
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($code) => static::normalizeStorefrontCode(is_scalar($code) ? (string) $code : null),
            Arr::wrap($value)
        ))));
    }

    protected static function fallbackCategoryId(?int $deletedId = null, ?int $preferredId = null): ?int
    {
        $query = static::query();

        if ($deletedId !== null) {
            $query->where('id', '!=', $deletedId);
        }

        if ($preferredId !== null) {
            $preferred = (clone $query)->where('id', $preferredId)->value('id');
            if ($preferred !== null) {
                return (int) $preferred;
            }
        }

        $fallback = $query
            ->orderByRaw("CASE WHEN slug = 'main' THEN 0 ELSE 1 END")
            ->orderBy('lft')
            ->orderBy('id')
            ->value('id');

        return $fallback !== null ? (int) $fallback : null;
    }

    protected static function rebuildTree(): void
    {
        if (!Schema::hasTable('ak_article_categories')) {
            return;
        }

        $rows = static::query()
            ->select(['id', 'parent_id', 'lft'])
            ->orderBy('lft')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $rowsById = [];
        $childrenByParent = [];

        foreach ($rows as $row) {
            $id = (int) $row->id;
            $parentId = $row->parent_id !== null ? (int) $row->parent_id : null;
            $rowsById[$id] = $row;
            $childrenByParent[$parentId ?? 0][] = $id;
        }

        $roots = collect($rowsById)->keys()->filter(function ($rowId) use ($rowsById) {
            $parentId = $rowsById[$rowId]->parent_id;

            return $parentId === null || !isset($rowsById[(int) $parentId]);
        })->values()->all();

        $updates = [];
        $counter = 1;
        $visited = [];

        $walk = function (int $id, int $depth) use (&$walk, &$updates, &$counter, &$visited, $childrenByParent): void {
            if (isset($visited[$id])) {
                return;
            }

            $visited[$id] = true;
            $left = $counter++;

            foreach ($childrenByParent[$id] ?? [] as $childId) {
                $walk($childId, $depth + 1);
            }

            $updates[$id] = [
                'lft' => $left,
                'rgt' => $counter++,
                'depth' => $depth,
            ];
        };

        foreach ($roots as $rootId) {
            $walk((int) $rootId, 0);
        }

        foreach (array_keys($rowsById) as $rowId) {
            if (!isset($visited[$rowId])) {
                $walk((int) $rowId, 0);
            }
        }

        static::withoutEvents(function () use ($updates) {
            foreach ($updates as $id => $payload) {
                DB::table('ak_article_categories')
                    ->where('id', $id)
                    ->update($payload);
            }
        });
    }
}
