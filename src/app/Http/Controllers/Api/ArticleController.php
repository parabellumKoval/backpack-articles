<?php

namespace Backpack\Articles\app\Http\Controllers\Api;

use Illuminate\Http\Request;
use \Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

use Backpack\Articles\app\Models\Article;
use Backpack\Articles\app\Http\Resources\ArticleSmallResource;
use Backpack\Tag\app\Models\Tag;

class ArticleController extends \App\Http\Controllers\Controller
{ 

  public function index(Request $request) {
    $per_page = (int) ($request->input('per_page') ?? config('backpack.articles.per_page', 12));
    $per_page = max(1, min($per_page, 100));
    
    $locale = app()->getLocale();
    $articles = Article::published()
      ->where('lang', $locale)
      ->with('tags')
      ->orderBy('published_at', 'desc');

    $tagIds = $this->extractTagIds($request);
    if (!empty($tagIds)) {
      $articles->whereHasTags($tagIds);
    }

    $tagTexts = $this->extractTagTexts($request);
    if (!empty($tagTexts)) {
      $articles->whereHas('tags', function ($relation) use ($tagTexts) {
        $relation->whereIn('ak_tags.text', $tagTexts);
      });
    }

    $articles = $articles->paginate($per_page)->appends($request->query());
    $articles = ArticleSmallResource::collection($articles);

    return $articles;
  }

  public function show(Request $request, $slug) {
    try{
      $article = Article::published()
        ->with('tags')
        ->where('slug', $slug)
        ->firstOrFail();
    }catch(ModelNotFoundException $e) {
      return response()->json($e->getMessage(), 404);
    }

    return $article;
  }

  public function random(Request $request) {
    $limit = request('limit') ?? 4;
    $locale = app()->getLocale();
    
    $articles = Article::published()
                  ->where('lang', $locale)
                  ->when($request->input('not_id'), function($query) use ($request) {
                    $query->where('id', '!=', $request->input('not_id'));
                  })
                  ->with('tags')
                  ->inRandomOrder()
                  ->limit($limit)
                  ->get();

    $articles = ArticleSmallResource::collection($articles);

    return $articles;
  }

  public function groupedByTags(Request $request)
  {
    $perTag = (int) ($request->input('per_tag') ?? $request->input('per_page') ?? config('backpack.articles.per_tag', 4));
    $perTag = max(1, min($perTag, 50));

    $locale = app()->getLocale();
    $tagIds = $this->extractTagIds($request);
    $tagTexts = $this->extractTagTexts($request);

    $tagsQuery = Tag::query()
      ->whereHas('articles', function ($query) use ($locale) {
        $query->published()->where('lang', $locale);
      })
      ->with(['articles' => function ($query) use ($locale, $perTag) {
        $query->published()
          ->where('lang', $locale)
          ->with('tags')
          ->orderBy('published_at', 'desc')
          ->limit($perTag);
      }])
      ->orderBy('text');


    if (!empty($tagIds)) {
      $tagsQuery->whereIn('id', $tagIds);
    }

    if (!empty($tagTexts)) {
      $tagsQuery->whereIn('text', $tagTexts);
    }

    $tags = $tagsQuery->get();

    $grouped = $tags->mapWithKeys(function ($tag) use ($request) {
      return [
        $tag->text => ArticleSmallResource::collection($tag->articles)->toArray($request),
      ];
    })->toArray();

    return $grouped;
  }

  protected function extractTagIds(Request $request): array
  {
    return $this->extractValuesFromRequest($request, ['tag_id', 'tag_ids', 'tags', 'tag'])
      ->filter(fn ($value) => is_numeric($value))
      ->map(fn ($value) => (int) $value)
      ->unique()
      ->values()
      ->all();
  }

  protected function extractTagTexts(Request $request): array
  {
    return $this->extractValuesFromRequest($request, ['tag_text', 'tag_texts', 'tag_name', 'tag', 'tags'])
      ->map(fn ($value) => (string) $value)
      ->map(fn ($value) => trim($value))
      ->filter(fn ($value) => $value !== '')
      ->unique()
      ->values()
      ->all();
  }

  protected function extractValuesFromRequest(Request $request, array $keys): Collection
  {
    // dd($request->all());
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

}
