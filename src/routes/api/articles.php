<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use Backpack\Articles\app\Http\Controllers\Api\ArticleController;
use Backpack\Articles\app\Http\Middleware\SetLocaleFromHeader;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('api/articles')->controller(ArticleController::class)->group(function () {
  
  Route::get('', 'index')->middleware([SetLocaleFromHeader::class]);
  Route::get('/grouped-by-tags', 'groupedByTags')->middleware([SetLocaleFromHeader::class]);

  Route::get('/random', 'random');
  
  Route::get('{slug}', 'show');

});
