<?php

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

Route::prefix('api/articles')
    ->middleware([SetLocaleFromHeader::class])
    ->controller(ArticleController::class)
    ->group(function () {
        Route::get('', 'index');
        Route::get('/grouped-by-tags', 'groupedByTags');
        Route::get('/random', 'random');
        Route::get('{slug}', 'show');
    });
