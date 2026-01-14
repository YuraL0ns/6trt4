<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EventController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PhotographerController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CheckoutController;

// Маршрут для отдачи файлов из storage (для исправления 403 ошибок)
Route::get('/storage/{path}', function ($path) {
    $filePath = storage_path('app/public/' . $path);
    
    if (!file_exists($filePath)) {
        abort(404);
    }
    
    $mimeType = mime_content_type($filePath);
    $headers = [
        'Content-Type' => $mimeType,
        'Content-Disposition' => 'inline',
    ];
    
    return response()->file($filePath, $headers);
})->where('path', '.*')->name('storage.file');

// Главная страница
Route::get('/', function () {
    return view('home');
})->name('home');

// Публичные маршруты
Route::get('/events', [EventController::class, 'index'])->name('events.index');
Route::get('/events/{slug}', [EventController::class, 'show'])->name('events.show');
Route::get('/events/{slug}/photo/{photoId}', [EventController::class, 'getPhoto'])->name('events.photo');
Route::get('/events/{slug}/photo/{photoId}/proxy', [EventController::class, 'getPhotoProxy'])->name('events.photo.proxy');
Route::post('/events/{slug}/find-similar', [EventController::class, 'findSimilar'])->name('events.find-similar');
Route::get('/api/search-task/{taskId}/status', [EventController::class, 'getSearchTaskStatus'])->name('api.search-task.status');

Route::get('/photographers', [PhotographerController::class, 'index'])->name('photographers.index');
Route::get('/photographer/{hashLogin}', [PhotographerController::class, 'show'])->name('photographers.show');

Route::get('/contacts', [ContactController::class, 'index'])->name('contacts');

// Страницы, созданные через админ-панель
Route::get('/pages/{url}', [App\Http\Controllers\PageController::class, 'show'])
    ->where('url', '.*')
    ->name('pages.show');
Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');

// Корзина (доступна для всех)
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
Route::delete('/cart/{id}', [CartController::class, 'destroy'])->name('cart.destroy');

// Оформление заказа
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

// Платежи YooKassa
Route::post('/payment/webhook', [App\Http\Controllers\PaymentController::class, 'webhook'])->name('payment.webhook');
Route::get('/payment/status/{paymentId}', [App\Http\Controllers\PaymentController::class, 'checkStatus'])->name('payment.status');

// Поиск заказов (доступен всем)
Route::get('/orders/search', [OrderController::class, 'search'])->name('orders.search');

// Скачивание архива заказа (доступно всем, но с проверкой доступа в контроллере)
Route::get('/orders/{id}/download', [OrderController::class, 'download'])->name('orders.download');

// Аутентификация
Auth::routes();

// Защищенные маршруты (требуют авторизации)
Route::middleware('auth')->group(function () {
    // Заказы
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{id}', [OrderController::class, 'show'])->name('orders.show');
    
    // Поиск заказов (для неавторизованных, но доступен и авторизованным)
    Route::get('/orders/search', [OrderController::class, 'search'])->name('orders.search');
    
    // Техподдержка
    Route::get('/support', [SupportController::class, 'index'])->name('support.index');
    Route::get('/support/{id}', [SupportController::class, 'show'])->name('support.show');
    Route::post('/support', [SupportController::class, 'store'])->name('support.store');
    Route::post('/support/{id}/message', [SupportController::class, 'addMessage'])->name('support.add-message');
    
    // Профиль пользователя
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    
    // Заявка на смену группы на фотографа
    Route::get('/profile/settings/photo_me', [ProfileController::class, 'photoMe'])->name('profile.settings.photo_me');
    Route::post('/profile/settings/photo_me', [ProfileController::class, 'photoMeStore'])->name('profile.settings.photo_me.store');
    
    // Оповещения (для всех авторизованных пользователей)
    Route::get('/notifications', [App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
});

// Маршруты для фотографов
Route::middleware(['auth', 'group:photo'])->prefix('photo')->name('photo.')->group(function () {
    Route::get('/events', [App\Http\Controllers\Photo\EventController::class, 'index'])->name('events.index');
    Route::get('/events/create', [App\Http\Controllers\Photo\EventController::class, 'create'])->name('events.create');
    Route::post('/events', [App\Http\Controllers\Photo\EventController::class, 'store'])->name('events.store');
    Route::get('/events/{event:slug}', [App\Http\Controllers\Photo\EventController::class, 'show'])->name('events.show');
    Route::post('/events/{event:slug}/upload', [App\Http\Controllers\Photo\EventController::class, 'uploadPhotos'])->name('events.upload');
    Route::post('/events/{event:slug}/upload-cover', [App\Http\Controllers\Photo\EventController::class, 'uploadCover'])->name('events.upload-cover');
    Route::get('/events/{event:slug}/upload-progress', [App\Http\Controllers\Photo\EventController::class, 'uploadProgress'])->name('events.upload-progress');
    Route::post('/events/{event:slug}/start-analysis', [App\Http\Controllers\Photo\EventController::class, 'startAnalysis'])->name('events.start-analysis');
    Route::get('/events/{event:slug}/status', [App\Http\Controllers\Photo\EventController::class, 'analysisStatus'])->name('events.status');
    Route::get('/analytics', [App\Http\Controllers\Photo\AnalyticsController::class, 'index'])->name('analytics');
    Route::get('/analytics/event/{eventId}/download-purchased-photos', [App\Http\Controllers\Photo\AnalyticsController::class, 'downloadPurchasedPhotos'])->name('analytics.download-purchased-photos');
    Route::get('/messages', [App\Http\Controllers\Photo\MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/{userId}', [App\Http\Controllers\Photo\MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/{userId}', [App\Http\Controllers\Photo\MessageController::class, 'store'])->name('messages.store');
    Route::get('/withdrawals', [App\Http\Controllers\Photo\WithdrawalController::class, 'index'])->name('withdrawals.index');
    Route::get('/withdrawals/balance', [App\Http\Controllers\Photo\WithdrawalController::class, 'getBalance'])->name('withdrawals.balance');
    Route::post('/withdrawals', [App\Http\Controllers\Photo\WithdrawalController::class, 'store'])->name('withdrawals.store');
    Route::post('/withdrawals/{id}/upload-receipt', [App\Http\Controllers\Photo\WithdrawalController::class, 'uploadReceipt'])->name('withdrawals.upload-receipt');
    Route::get('/withdrawals/{id}/receipt/{type}', [App\Http\Controllers\Photo\WithdrawalController::class, 'showReceipt'])->name('withdrawals.receipt');
});

// Маршруты для администраторов (включая создание событий)
Route::middleware(['auth', 'group:admin'])->prefix('admin')->name('admin.')->group(function () {
    // События (администратор может создавать события как фотограф)
    Route::get('/events/create', [App\Http\Controllers\Photo\EventController::class, 'create'])->name('events.create');
    Route::post('/events', [App\Http\Controllers\Photo\EventController::class, 'store'])->name('events.store');
    Route::get('/events/{event:slug}', [App\Http\Controllers\Photo\EventController::class, 'show'])->name('events.show');
    Route::post('/events/{event:slug}/upload', [App\Http\Controllers\Photo\EventController::class, 'uploadPhotos'])->name('events.upload');
    Route::get('/events/{event:slug}/upload-progress', [App\Http\Controllers\Photo\EventController::class, 'uploadProgress'])->name('events.upload-progress');
    Route::post('/events/{event:slug}/start-analysis', [App\Http\Controllers\Photo\EventController::class, 'startAnalysis'])->name('events.start-analysis');
    Route::get('/events/{event:slug}/status', [App\Http\Controllers\Photo\EventController::class, 'analysisStatus'])->name('events.status');
    
    // Оповещения
    Route::get('/notifications', [App\Http\Controllers\Admin\NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/{id}/read', [App\Http\Controllers\Admin\NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [App\Http\Controllers\Admin\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    
    // Остальные маршруты администратора
    Route::get('/dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/events', [App\Http\Controllers\Admin\EventController::class, 'index'])->name('events.index');
    Route::get('/events/{id}/edit', [App\Http\Controllers\Admin\EventController::class, 'edit'])->name('events.edit');
    Route::put('/events/{id}', [App\Http\Controllers\Admin\EventController::class, 'update'])->name('events.update');
    Route::delete('/events/{id}', [App\Http\Controllers\Admin\EventController::class, 'destroy'])->name('events.destroy');
    Route::post('/events/{id}/archive', [App\Http\Controllers\Admin\EventController::class, 'archive'])->name('events.archive');
    Route::post('/events/{id}/unarchive', [App\Http\Controllers\Admin\EventController::class, 'unarchive'])->name('events.unarchive');
    Route::post('/events/{id}/delete-photos', [App\Http\Controllers\Admin\EventController::class, 'deleteUserPhotos'])->name('events.delete-photos');
    Route::get('/users', [App\Http\Controllers\Admin\UserController::class, 'index'])->name('users.index');
    Route::get('/users/{id}', [App\Http\Controllers\Admin\UserController::class, 'show'])->name('users.show');
    Route::put('/users/{id}', [App\Http\Controllers\Admin\UserController::class, 'update'])->name('users.update');
    Route::post('/users/{id}/change-group', [App\Http\Controllers\Admin\UserController::class, 'changeGroup'])->name('users.change-group');
    Route::post('/users/{id}/change-password', [App\Http\Controllers\Admin\UserController::class, 'changePassword'])->name('users.change-password');
    Route::post('/users/{id}/toggle-block', [App\Http\Controllers\Admin\UserController::class, 'toggleBlock'])->name('users.toggle-block');
    Route::get('/analytics', [App\Http\Controllers\Admin\AnalyticsController::class, 'index'])->name('analytics');
    Route::get('/celery', [App\Http\Controllers\Admin\CeleryController::class, 'index'])->name('celery.index');
    Route::get('/celery/events/{eventId}', [App\Http\Controllers\Admin\CeleryController::class, 'show'])->name('celery.show');
    Route::get('/photos', [App\Http\Controllers\Admin\PhotoController::class, 'list'])->name('photos.list');
    Route::get('/photos/{id}', [App\Http\Controllers\Admin\PhotoController::class, 'show'])->name('photos.show');
    Route::get('/celery/events/{eventId}/logs', [App\Http\Controllers\Admin\CeleryController::class, 'logs'])->name('celery.logs');
    Route::post('/celery/events/{eventId}/restart', [App\Http\Controllers\Admin\CeleryController::class, 'restart'])->name('celery.restart');
    Route::post('/celery/tasks/{taskId}/restart', [App\Http\Controllers\Admin\CeleryController::class, 'restartTask'])->name('celery.tasks.restart');
    Route::delete('/celery/tasks/{taskId}', [App\Http\Controllers\Admin\CeleryController::class, 'deleteTask'])->name('celery.tasks.delete');
    Route::get('/celery/tasks/{taskId}/log', [App\Http\Controllers\Admin\CeleryController::class, 'getTaskLog'])->name('celery.tasks.log');
    Route::post('/celery/events/{eventId}/start-task', [App\Http\Controllers\Admin\CeleryController::class, 'startTask'])->name('celery.tasks.start');
    Route::get('/photos', [App\Http\Controllers\Admin\PhotoController::class, 'list'])->name('photos.list');
    Route::get('/photos/{id}', [App\Http\Controllers\Admin\PhotoController::class, 'show'])->name('photos.show');
    Route::get('/photos/{photoId}/faces', [App\Http\Controllers\Admin\PhotoController::class, 'showWithFaces'])->name('photos.show-with-faces');
    Route::get('/withdrawals', [App\Http\Controllers\Admin\WithdrawalController::class, 'index'])->name('withdrawals.index');
    Route::get('/withdrawals/{id}', [App\Http\Controllers\Admin\WithdrawalController::class, 'show'])->name('withdrawals.show');
    Route::get('/withdrawals/{id}/receipt/{type}', [App\Http\Controllers\Admin\WithdrawalController::class, 'showReceipt'])->name('withdrawals.receipt');
    Route::post('/withdrawals/{id}/approve', [App\Http\Controllers\Admin\WithdrawalController::class, 'approve'])->name('withdrawals.approve');
    Route::post('/withdrawals/{id}/reject', [App\Http\Controllers\Admin\WithdrawalController::class, 'reject'])->name('withdrawals.reject');
    Route::get('/group-requests', [App\Http\Controllers\Admin\GroupRequestController::class, 'index'])->name('group-requests.index');
    Route::post('/group-requests/{id}/approve', [App\Http\Controllers\Admin\GroupRequestController::class, 'approve'])->name('group-requests.approve');
    Route::post('/group-requests/{id}/reject', [App\Http\Controllers\Admin\GroupRequestController::class, 'reject'])->name('group-requests.reject');
    Route::get('/support', [App\Http\Controllers\Admin\SupportController::class, 'index'])->name('support.index');
    Route::get('/support/{id}', [App\Http\Controllers\Admin\SupportController::class, 'show'])->name('support.show');
    Route::post('/support/{id}/message', [App\Http\Controllers\Admin\SupportController::class, 'addMessage'])->name('support.add-message');
    Route::post('/support/{id}/close', [App\Http\Controllers\Admin\SupportController::class, 'close'])->name('support.close');
    Route::get('/pages', [App\Http\Controllers\Admin\PageController::class, 'index'])->name('pages.index');
    Route::post('/pages', [App\Http\Controllers\Admin\PageController::class, 'store'])->name('pages.store');
    Route::get('/pages/{id}/edit-data', [App\Http\Controllers\Admin\PageController::class, 'editData'])->name('pages.edit-data');
    Route::put('/pages/{id}', [App\Http\Controllers\Admin\PageController::class, 'update'])->name('pages.update');
    Route::delete('/pages/{id}', [App\Http\Controllers\Admin\PageController::class, 'destroy'])->name('pages.destroy');
    Route::get('/settings', [App\Http\Controllers\Admin\SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [App\Http\Controllers\Admin\SettingController::class, 'update'])->name('settings.update');
    Route::get('/search-analytics', [App\Http\Controllers\Admin\SearchAnalyticsController::class, 'index'])->name('search-analytics.index');
    Route::get('/search-analytics/{id}', [App\Http\Controllers\Admin\SearchAnalyticsController::class, 'show'])->name('search-analytics.show');
});
