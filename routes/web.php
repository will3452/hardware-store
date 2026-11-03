<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\CashReportController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::get('invitations/{token}', [InvitationController::class, 'show'])->middleware('throttle:30,1')->name('invitation.show');
Route::post('invitations/{token}', [InvitationController::class, 'accept'])->middleware('throttle:10,1')->name('invitation.accept');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::prefix('admin')->middleware('platform.admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('dashboard');
        Route::post('stores', [AdminController::class, 'create'])->name('stores.create');
        Route::get('stores/{store}', [AdminController::class, 'show'])->name('stores.show');
        Route::post('stores/{store}', [AdminController::class, 'update'])->name('stores.update');
        Route::post('stores/{store}/recovery', [AdminController::class, 'recovery'])->middleware('throttle:5,1')->name('stores.recovery');
        Route::post('stores/{store}/invitation', [AdminController::class, 'invite'])->middleware('throttle:5,1')->name('stores.invite');
        Route::post('stores/{store}/modules', [AdminController::class, 'modules'])->name('stores.modules');
        Route::get('announcements', [AdminController::class, 'announcements'])->name('announcements');
        Route::post('announcements', [AdminController::class, 'announcement'])->name('announcements.create');
        Route::post('announcements/{announcement}', [AdminController::class, 'announcementStatus'])->name('announcements.status');
        Route::get('audit', [AdminController::class, 'audit'])->name('audit');
        Route::get('email', [EmailController::class, 'index'])->name('email');
        Route::post('email/test', [EmailController::class, 'test'])->middleware('throttle:3,10')->name('email.test');
        Route::post('email/{delivery}/retry', [EmailController::class, 'retry'])->middleware('throttle:10,1')->name('email.retry');
        Route::get('health', [AdminController::class, 'health'])->name('health');
    });
    Route::get('store', [PortalController::class, 'stores'])->name('store.index');
    Route::prefix('store/{store}')->middleware('store.context')->name('store.')->group(function () {
        Route::get('/', [OperationsController::class, 'page'])->name('dashboard');
        Route::get('workspace/{page}', [OperationsController::class, 'page'])->name('page');
        Route::get('catalog', [OperationsController::class, 'catalog'])->name('catalog');
        Route::post('products', [OperationsController::class, 'product'])->name('products.save');
        Route::post('units', [OperationsController::class, 'unit'])->name('units.save');
        Route::post('post/{kind}', [OperationsController::class, 'post'])->name('post');
        Route::post('documents/{document}/attachments', [AttachmentController::class, 'upload'])->name('attachments.upload');
        Route::get('attachments/{attachment}', [AttachmentController::class, 'download'])->name('attachments.download');
        Route::get('documents/{document}', [OperationsController::class, 'document'])->name('document');
        Route::post('cash/{action}', [OperationsController::class, 'cash'])->name('cash');
        Route::get('cash-sessions/{cashSession}', [CashReportController::class, 'show'])->name('cash.show');
        Route::post('suppliers', [OperationsController::class, 'supplier'])->name('suppliers.save');
        Route::post('purchases', [OperationsController::class, 'purchase'])->name('purchases.save');
        Route::post('purchases/{order}/draft', [OperationsController::class, 'purchase'])->name('purchases.update');
        Route::get('purchases/{order}', [OperationsController::class, 'order'])->name('order');
        Route::post('purchases/{order}/status', [OperationsController::class, 'orderStatus'])->name('order.status');
        Route::post('profile', [OperationsController::class, 'profile'])->name('profile.save');
        Route::post('payment-settings', [OperationsController::class, 'paymentSettings'])->name('payments.settings');
    });
});

require __DIR__.'/settings.php';
