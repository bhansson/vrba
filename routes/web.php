<?php

use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::view('/products', 'products.index')
        ->name('products.index');

    Route::get('/products/{product}', function (Product $product) {
        $team = Auth::user()->currentTeam;

        abort_if(! $team || $product->team_id !== $team->id, 404);

        return view('products.show', [
            'product' => $product,
        ]);
    })->name('products.show');

    Route::view('/ai-jobs', 'ai-jobs.index')
        ->name('ai-jobs.index');

    Route::view('/ai-templates', 'ai-templates.index')
        ->name('ai-templates.index');
});
