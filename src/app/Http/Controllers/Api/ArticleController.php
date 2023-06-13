<?php

namespace Backpack\Articles\app\Http\Controllers\Api;

use Illuminate\Http\Request;
use \Illuminate\Database\Eloquent\ModelNotFoundException;

use Backpack\Articles\app\Models\Article;
use Backpack\Articles\app\Http\Resources\ArticleSmallResource;

class ArticleController extends \App\Http\Controllers\Controller
{ 

  public function index(Request $request) {
    $per_page = request('per_page')? request('per_page'): config('backpack.articles.per_page', 12);
    
    $articles = Article::published()->where('lang', request('lang'))->orderBy('date', 'desc');

    $articles = $articles->paginate($per_page);
    $articles = ArticleSmallResource::collection($articles);

    return $articles;
  }

  public function show(Request $request, $slug) {
    try{
      $article = Article::published()->where('slug', $slug)->firstOrFail();
    }catch(ModelNotFoundException $e) {
      return response()->json($e->getMessage(), 404);
    }

    return $article;
  }

  public function random(Request $request) {
    $limit = request('limit') ?? 4;
    
    $articles = Article::published()
                  ->where('lang', request('lang'))
                  ->when(request('not_id'), function($query) {
                    $query->where('id', '!=', request('not_id'));
                  })
                  ->inRandomOrder()
                  ->limit($limit)
                  ->get();

    $articles = ArticleSmallResource::collection($articles);

    return $articles;
  }

}
