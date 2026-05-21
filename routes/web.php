<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DealFeedController;


// Join/Onboarding
Route::get('/join', function() { return require public_path('join.php'); })->name('join');

Route::get('/', [DealFeedController::class, 'index'])->name('home');
Route::get('/deal/{id}', [DealFeedController::class, 'show'])->name('deal.show');
Route::get('/deal/{id}/go', [DealFeedController::class, 'clickOut'])->name('deal.click');


// Forum Routes
use App\Http\Controllers\ForumController;
Route::get('/forum', [ForumController::class, 'index'])->name('forum.index');
Route::get('/forum/post/{id}', [ForumController::class, 'show'])->name('forum.show');

Route::get('/deals', [App\Http\Controllers\DealFeedController::class, 'dealsOnly'])->name('deals.only');
