<?php

namespace Backpack\Articles\app\Http\Controllers\Api;

use Illuminate\Http\Request;
use \Illuminate\Database\Eloquent\ModelNotFoundException;

use Backpack\Articles\app\Models\Article;
use Backpack\Articles\app\Http\Resources\ArticleSmallResource;

class ArticleController extends \App\Http\Controllers\Controller
{ 

  public function index(Request $request) {
    $per_page = config('backpack.articles.per_page', 12);
    
    $articles = Article::published()->where('lang', request('lang'))->orderBy('created_at', 'desc');

    $articles = $articles->paginate($per_page);
    $articles = ArticleSmallResource::collection($articles);

    return response()->json($articles);
  }

  public function show(Request $request, $slug) {
    try{
      $article = Article::published()->where('slug', $slug)->firstOrFail();
    }catch(ModelNotFoundException $e) {
      return response()->json($e->getMessage(), 404);
    }

    return $article;
  }

}
