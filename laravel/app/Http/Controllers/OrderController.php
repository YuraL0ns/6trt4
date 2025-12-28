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
}
