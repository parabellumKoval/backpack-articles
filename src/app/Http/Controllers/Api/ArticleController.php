<?php

namespace Backpack\Articles\app\Http\Controllers\Api;

use Backpack\Articles\app\Http\Resources\ArticleResource;
use Backpack\Articles\app\Http\Resources\ArticleSmallResource;
use Backpack\Articles\app\Models\Article;
use Backpack\Articles\app\Models\ArticleCategory;
use Backpack\Tag\app\Models\Tag;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ArticleController extends \App\Http\Controllers\Controller
{
    public function index(Request $request)
    {
        $perPage = (int) ($request->input('per_page') ?? config('backpack.articles.per_page', 12));
        $perPage = max(1, min($perPage, 100));

        $region = is_string($request->input('country')) ? $request->input('country') : null;
        $categoryIds = $this->resolveCategoryNodeIds($request);

        $articles = Article::published()
            ->availableInRegion($region)
            ->availableInStorefront()
            ->with(['tags', 'category'])
            ->orderBy('published_at', 'desc');

        $this->applyCategoryFilter($articles, $categoryIds);

        $tagIds = $this->extractTagIds($request);
        if ($tagIds !== []) {
            $articles->whereHasTags($tagIds);
        }

        $tagValues = $this->extractTagValues($request);
        if ($tagValues !== []) {
            $articles->whereHas('tags', function ($relation) use ($tagValues) {
                $relation->whereIn('ak_tags.value', $tagValues);
            });
        }

        return ArticleSmallResource::collection(
            $articles->paginate($perPage)->appends($request->query())
        );
    }

    public function show(Request $request, $slug)
    {
        $locale = app()->getLocale();
        $region = is_string($request->input('country')) ? $request->input('country') : null;

        try {
            $article = Article::published()
                ->availableInRegion($region)
                ->availableInStorefront()
                ->with(['tags', 'category'])
                ->withContentLocales([$locale, config('app.fallback_locale')])
                ->where('slug', $slug)
                ->firstOrFail();
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Article Not Found',
                'error' => 'Not Found',
            ], 404);
        }

        return new ArticleResource($article);
    }

    public function random(Request $request)
    {
        $limit = max(1, min((int) ($request->input('limit') ?? 4), 24));
        $region = is_string($request->input('country')) ? $request->input('country') : null;
        $categoryIds = $this->resolveCategoryNodeIds($request);

        $articles = Article::published()
            ->availableInRegion($region)
            ->availableInStorefront()
            ->when($request->input('not_id'), function ($query) use ($request) {
                $query->where('id', '!=', $request->input('not_id'));
            })
            ->with(['tags', 'category'])
            ->inRandomOrder()
            ->limit($limit);

        $this->applyCategoryFilter($articles, $categoryIds);

        return ArticleSmallResource::collection($articles->get());
    }

    public function groupedByTags(Request $request)
    {
        $perTag = (int) ($request->input('per_tag') ?? $request->input('per_page') ?? config('backpack.articles.per_tag', 4));
        $perTag = max(1, min($perTag, 50));

        $region = is_string($request->input('country')) ? $request->input('country') : null;
        $tagIds = $this->extractTagIds($request);
        $tagValues = $this->extractTagValues($request);
        $categoryIds = $this->resolveCategoryNodeIds($request);

        $tagsQuery = Tag::query()
            ->whereExists(function ($query) use ($region, $categoryIds) {
                $query->select('id')
                    ->from('ak_taggables')
                    ->whereColumn('ak_taggables.tag_id', 'ak_tags.id')
                    ->where('ak_taggables.taggable_type', (new Article())->getMorphClass())
                    ->whereExists(function ($subQuery) use ($region, $categoryIds) {
                        $subQuery->select('id')
                            ->from('ak_articles')
                            ->whereColumn('ak_articles.id', 'ak_taggables.taggable_id')
                            ->where('ak_articles.status', 'PUBLISHED');

                        Article::addRegionAvailabilityClause($subQuery, 'ak_articles.countries', $region);
                        Article::addStorefrontAvailabilityClause($subQuery, 'ak_articles.category_id');

                        if ($categoryIds !== []) {
                            $subQuery->whereIn('ak_articles.category_id', $categoryIds);
                        }
                    });
            })
            ->orderBy('value');

        if ($tagIds !== []) {
            $tagsQuery->whereIn('id', $tagIds);
        }

        if ($tagValues !== []) {
            $tagsQuery->whereIn('value', $tagValues);
        }

        $tags = $tagsQuery->get();

        return $tags->mapWithKeys(function ($tag) use ($request, $perTag, $region, $categoryIds) {
            $articles = $tag->getTaggedModels(Article::class)
                ->published()
                ->availableInRegion($region)
                ->availableInStorefront()
                ->with(['tags', 'category'])
                ->orderBy('published_at', 'desc');

            $this->applyCategoryFilter($articles, $categoryIds);

            return [
                $tag->value => ArticleSmallResource::collection(
                    $articles->limit($perTag)->get()
                )->toArray($request),
            ];
        })->toArray();
    }

    protected function extractTagIds(Request $request): array
    {
        return $this->extractValuesFromRequest($request, ['tag_id', 'tag_ids', 'tag', 'tags'])
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();
    }

    protected function extractTagValues(Request $request): array
    {
        return $this->extractValuesFromRequest($request, ['tag_text', 'tag_texts', 'tag_name', 'tag', 'tags'])
            ->filter(fn ($value) => !is_numeric($value))
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    protected function resolveCategoryNodeIds(Request $request): array
    {
        $allValues = $this->extractValuesFromRequest($request, [
            'category_id',
            'category_ids',
            'category_slug',
            'category_slugs',
            'category',
            'categories',
        ]);

        $ids = $allValues
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->unique()
            ->values()
            ->all();

        $slugs = $allValues
            ->filter(fn ($value) => !is_numeric($value))
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();

        return ArticleCategory::expandIdsToSubtree($ids, $slugs);
    }

    protected function extractValuesFromRequest(Request $request, array $keys): Collection
    {
        return collect($keys)->flatMap(function ($key) use ($request) {
            if (!$request->has($key)) {
                return [];
            }

            $value = $request->input($key);

            if (is_array($value)) {
                return collect($value)->flatMap(function ($item) {
                    if ($item === null) {
                        return [];
                    }

                    if (is_array($item)) {
                        return $item;
                    }

                    if (is_string($item)) {
                        return array_map('trim', explode(',', $item));
                    }

                    return [$item];
                });
            }

            if (is_string($value)) {
                return array_map('trim', explode(',', $value));
            }

            return [$value];
        })->filter(function ($value) {
            if (is_string($value)) {
                return $value !== '';
            }

            return $value !== null;
        })->values();
    }

    protected function applyCategoryFilter($query, array $categoryIds): void
    {
        if ($categoryIds === []) {
            return;
        }

        $query->whereIn('category_id', $categoryIds);
    }
}
