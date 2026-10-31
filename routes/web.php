<?php

use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::view('admin', 'portals.admin')->middleware('platform.admin')->name('admin.dashboard');
    Route::get('store', [PortalController::class, 'stores'])->name('store.index');
    Route::get('store/{store}', [PortalController::class, 'store'])
        ->middleware('store.context')->name('store.dashboard');
});

require __DIR__.'/settings.php';
