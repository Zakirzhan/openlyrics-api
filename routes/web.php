<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});


Route::get('/getArtistSongs/{id}',[\App\Http\Controllers\ScraperController::class,'getArtistSongs'])->name('getArtistSongs');

Route::get('/getLyrics/{id}',[\App\Http\Controllers\ScraperController::class,'getLyrics'])->name('getLyrics');
Route::get('/search/{query}',[\App\Http\Controllers\ScraperController::class,'search'])->name('search');


