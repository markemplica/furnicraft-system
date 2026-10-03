<?php

use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ReservationController as AdminReservationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\RecommendationFeedbackController;
use App\Http\Controllers\SystemUsabilityController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomPhotoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('home');
Route::view('/how-it-works', 'how-it-works')->name('how-it-works');
Route::view('/privacy-policy', 'privacy-policy')->name('privacy-policy');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store')->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store')->middleware('throttle:5,1');
    Route::get('/forgot-password', [PasswordResetController::class, 'showRequest'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendCode'])->name('password.email')->middleware('throttle:3,1');
    Route::get('/reset-password', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update')->middleware('throttle:8,1');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/room-images/{roomImage}', [RoomPhotoController::class, 'original'])->name('room-images.original');
    Route::get('/room-images/{roomImage}/previews/{product}', [RoomPhotoController::class, 'preview'])->whereNumber('product')->name('room-images.preview');

    Route::middleware('role:customer')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::delete('/profile/account', [ProfileController::class, 'destroy'])->name('profile.destroy');
        Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
        Route::get('/catalog/search', [CatalogController::class, 'search'])->name('catalog.search');
        Route::get('/catalog/{furnitureProduct}', [CatalogController::class, 'show'])->name('catalog.show');
        Route::post('/catalog/{furnitureProduct}/favorite', [CatalogController::class, 'favorite'])->name('catalog.favorite');
        Route::get('/preferences', [PreferenceController::class, 'edit'])->name('preferences.edit');
        Route::post('/preferences', [PreferenceController::class, 'update'])->name('preferences.update');
        Route::post('/recommendations', [RecommendationController::class, 'store'])->name('recommendations.store');
        Route::get('/recommendations', [RecommendationController::class, 'index'])->name('recommendations.index');
        Route::get('/system-usability', [SystemUsabilityController::class, 'edit'])->name('usability.edit');
        Route::post('/system-usability', [SystemUsabilityController::class, 'store'])->name('usability.store');
        Route::get('/recommendations/{recommendation}', [RecommendationController::class, 'show'])->name('recommendations.show');
        Route::post('/recommendations/{recommendation}/items/{recommendationItem}/feedback', [RecommendationFeedbackController::class, 'update'])->name('recommendations.items.feedback');
        Route::post('/recommendations/{recommendation}/chat', [ChatController::class, 'store'])->name('recommendations.chat');
        Route::get('/room', [RoomController::class, 'index'])->name('room.index');
        Route::post('/room', [RoomController::class, 'store'])->name('room.store');
        Route::delete('/room/{roomImage}', [RoomController::class, 'destroy'])->name('room.destroy');
        Route::delete('/room/{roomImage}/ai-consent', [RoomController::class, 'revokeAiConsent'])->name('room.ai-consent.revoke');
        Route::get('/room/{roomImage}/visualize', [RoomController::class, 'visualize'])->name('room.visualize');
        Route::get('/room/{roomImage}/analysis-status', [RoomController::class, 'analysisStatus'])->name('room.analysis-status');
        Route::get('/room/{roomImage}/preview-status/{product}', [RoomController::class, 'previewStatus'])->whereNumber('product')->name('room.preview-status');
        Route::post('/room/{roomImage}/reanalyze', [RoomController::class, 'reanalyze'])->name('room.reanalyze')->middleware('throttle:3,1');
        Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
        Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
        Route::patch('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');
    });

    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::resource('products', AdminProductController::class)->except('show');
        Route::get('/customers', [AdminCustomerController::class, 'index'])->name('customers.index');
        Route::patch('/customers/{user}', [AdminCustomerController::class, 'update'])->name('customers.update');
        Route::get('/reservations', [AdminReservationController::class, 'index'])->name('reservations.index');
        Route::get('/reports', [App\Http\Controllers\Admin\ReportsController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [App\Http\Controllers\Admin\ReportsController::class, 'export'])->name('reports.export');
        Route::get('/recommendation-evaluation', [App\Http\Controllers\Admin\RecommendationEvaluationController::class, 'index'])->name('recommendation-evaluation');
        Route::get('/system-usability-results', [App\Http\Controllers\Admin\UsabilityResultsController::class, 'index'])->name('system-usability-results');
        Route::get('/privacy-audit', [App\Http\Controllers\Admin\PrivacyAuditController::class, 'index'])->name('privacy-audit');
        Route::get('/ai-service-health', [App\Http\Controllers\Admin\AiServiceHealthController::class, 'index'])->name('ai-service-health');
        Route::get('/deployment-readiness', [App\Http\Controllers\Admin\ProductionReadinessController::class, 'index'])->name('deployment-readiness');
        Route::get('/room-placement-evaluation', [App\Http\Controllers\Admin\RoomPlacementEvaluationController::class, 'index'])->name('room-placement-evaluation');
        Route::post('/room-placement-evaluation/{roomImage}', [App\Http\Controllers\Admin\RoomPlacementEvaluationController::class, 'store'])->name('room-placement-evaluation.store');
        Route::get('/room-placement-evaluation/{roomImage}/preview', [RoomController::class, 'visualize'])->name('room-placement-evaluation.preview');
        Route::patch('/reservations/{reservation}', [AdminReservationController::class, 'update'])->name('reservations.update');
    });
});
