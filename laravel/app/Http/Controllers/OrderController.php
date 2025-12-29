<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Services\Payment\YooKassaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    protected YooKassaService $yooKassaService;

    public function __construct(YooKassaService $yooKassaService)
    {
        $this->yooKassaService = $yooKassaService;
    }

    /**
     * Список заказов пользователя
     */
    public function index()
    {
        $orders = Order::where('user_id', Auth::id())
            ->with('items.photo')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('orders.index', compact('orders'));
    }

    /**
     * Показать заказ
     */
    public function show(string $id)
    {
        // Проверяем доступ (авторизованный пользователь или по email/телефону)
        $order = Order::with(['items.photo.event', 'payment'])
            ->findOrFail($id);

        // КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Если заказ оплачен, но архив не создан, пытаемся создать его
        if ($order->status === 'paid') {
            $zipPath = $order->zip_path;
            $zipExists = false;
            if ($zipPath) {
                $fullZipPath = storage_path('app/public/' . $zipPath);
                $zipExists = file_exists($fullZipPath);
            }
            
            if (!$zipExists) {
                try {
                    $this->yooKassaService->generateZipArchive($order);
                    // Перезагружаем заказ после создания архива
                    $order->refresh();
                } catch (\Exception $e) {
                    \Log::error("OrderController::show: Failed to regenerate ZIP archive", [
                        'order_id' => $order->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        // Если пользователь авторизован, проверяем что это его заказ
        if (Auth::check()) {
            if ($order->user_id && $order->user_id !== Auth::id()) {
                abort(403);
            }
        } else {
            // Для неавторизованных - проверка через email/телефон будет в middleware или отдельно
            // Пока разрешаем просмотр
        }

        // Проверяем статус платежа, если заказ еще не оплачен
        if ($order->status === 'pending' && $order->yookassa_payment_id) {
            try {
                // checkPaymentStatus теперь автоматически обновляет статус заказа
                $paymentInfo = $this->yooKassaService->checkPaymentStatus($order->yookassa_payment_id);
                if ($paymentInfo) {
                    // Обновляем заказ из БД
                    $order->refresh();
                }
            } catch (\Exception $e) {
                \Log::warning("OrderController::show: Error checking payment status", [
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return view('orders.show', compact('order'));
    }

    /**
     * Поиск заказов по email или телефону (для неавторизованных)
     */
    public function search(Request $request)
    {
        $request->validate([
            'email' => 'required_without:phone|email',
            'phone' => 'required_without:email|string',
        ]);

        $orders = Order::where(function ($query) use ($request) {
            if ($request->email) {
                $query->where('email', $request->email);
            }
            if ($request->phone) {
                $query->orWhere('phone', $request->phone);
            }
        })
            ->with('items.photo')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('orders.search', compact('orders'));
    }

    /**
     * Скачать архив заказа
     * КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Используем контроллер для скачивания вместо прямого доступа к storage
     */
    public function download(string $id)
    {
        $order = Order::findOrFail($id);

        // Проверяем доступ (аналогично методу show)
        if (Auth::check()) {
            if ($order->user_id && $order->user_id !== Auth::id()) {
                abort(403, 'Доступ запрещен');
            }
        }
        // Для неавторизованных - разрешаем скачивание (проверка доступа может быть добавлена позже)

        // Проверяем, что заказ оплачен
        if ($order->status !== 'paid') {
            abort(403, 'Заказ не оплачен');
        }

        // Проверяем наличие архива
        if (!$order->zip_path) {
            // Пытаемся создать архив, если его нет
            try {
                $this->yooKassaService->generateZipArchive($order);
                $order->refresh();
            } catch (\Exception $e) {
                \Log::error("OrderController::download: Failed to create ZIP archive", [
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
                abort(404, 'Не удалось создать архив');
            }
        }

        if (!$order->zip_path) {
            abort(404, 'Архив не найден');
        }

        $fullZipPath = storage_path('app/public/' . $order->zip_path);
        
        if (!file_exists($fullZipPath)) {
            // Пытаемся создать архив, если файл не существует
            try {
                $this->yooKassaService->generateZipArchive($order);
                $order->refresh();
                $fullZipPath = storage_path('app/public/' . $order->zip_path);
            } catch (\Exception $e) {
                \Log::error("OrderController::download: Failed to regenerate ZIP archive", [
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
                abort(404, 'Не удалось создать архив');
            }
        }

        if (!file_exists($fullZipPath)) {
            abort(404, 'Архив не найден');
        }

        // КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Используем response()->download() для правильной отдачи файла
        return response()->download($fullZipPath, 'order_' . substr($order->id, 0, 8) . '.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }
}
