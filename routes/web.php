<?php

use App\Http\Controllers\InquiryController;
use App\Http\Controllers\OwnerAuthController;
use App\Http\Controllers\OwnerContentController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ServiceWorkerController;
use App\Http\Middleware\OwnerOnly;
use App\Http\Middleware\PrivateResponse;
use App\Support\Portfolio;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::withoutMiddleware([
    EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class,
    ShareErrorsFromSession::class, ValidateCsrfToken::class,
])->group(function () {
    Route::get('/', [PortfolioController::class, 'index'])->name('home');
    Route::get('/projects/{slug}', [PortfolioController::class, 'project'])->where('slug', '[a-z0-9-]+')->name('projects.show');
    Route::get('/images/{uuid}', [PortfolioController::class, 'image'])->whereUuid('uuid');
    Route::get('/documents/{uuid}', [PortfolioController::class, 'document'])->whereUuid('uuid');
    Route::get('/sitemap.xml', [PortfolioController::class, 'sitemap'])->name('sitemap');
    Route::get('/robots.txt', [PortfolioController::class, 'robots']);
    Route::get('/sw.js', ServiceWorkerController::class);
});

Route::middleware(PrivateResponse::class)->group(function () {
    Route::get('/contact/token', [InquiryController::class, 'token'])->middleware('throttle:20,1');
    Route::post('/contact', [InquiryController::class, 'store'])->middleware('throttle:5,10');
});

Route::prefix('admin')->name('admin.')->middleware(PrivateResponse::class)->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [OwnerAuthController::class, 'create'])->name('login');
        Route::post('login', [OwnerAuthController::class, 'store'])->middleware('throttle:20,1');
    });

    Route::middleware(['auth', OwnerOnly::class])->group(function () {
        Route::redirect('/', '/admin/dashboard');
        Route::post('logout', [OwnerAuthController::class, 'destroy'])->name('logout');
        Route::get('dashboard', [OwnerContentController::class, 'dashboard'])->name('dashboard');
        Route::get('audit-logs', [OwnerContentController::class, 'audits']);
        Route::get('profile', [OwnerContentController::class, 'profile']);
        Route::post('profile', [OwnerContentController::class, 'saveProfile']);
        Route::get('settings', [OwnerContentController::class, 'settings']);
        Route::post('settings', [OwnerContentController::class, 'saveSettings']);
        Route::get('media/{uuid}', [PortfolioController::class, 'privateMedia'])->whereUuid('uuid');
        Route::get('inquiries', [InquiryController::class, 'index']);
        Route::get('inquiries/{inquiry}', [InquiryController::class, 'show'])->whereNumber('inquiry')->name('inquiries.show');
        Route::post('inquiries/{inquiry}/reply', [InquiryController::class, 'reply'])->whereNumber('inquiry');
        Route::patch('inquiries/{inquiry}/status', [InquiryController::class, 'status'])->whereNumber('inquiry');
        Route::post('skill-categories/{id?}', [OwnerContentController::class, 'saveCategory'])->whereNumber('id');
        Route::delete('skill-categories/{id}', [OwnerContentController::class, 'destroyCategory'])->whereNumber('id');

        $sections = array_keys(Portfolio::SECTIONS);
        Route::get('{section}', [OwnerContentController::class, 'index'])->whereIn('section', $sections)->name('records');
        Route::post('{section}/{id?}', [OwnerContentController::class, 'save'])->whereIn('section', $sections)->whereNumber('id');
        Route::delete('{section}/{id}', [OwnerContentController::class, 'destroy'])->whereIn('section', $sections)->whereNumber('id');
    });
});
