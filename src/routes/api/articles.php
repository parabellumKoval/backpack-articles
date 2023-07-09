<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use Backpack\Articles\app\Http\Controllers\Api\ArticleController;

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
  Route::get('', 'index')->middleware('api');
  Route::get('/random', 'random')->middleware('api');
  Route::get('{slug}', 'show')->middleware('api');
});
