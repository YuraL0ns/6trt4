<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Payment\YooKassaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckoutController extends Controller
{
    protected YooKassaService $yooKassaService;

    public function __construct(YooKassaService $yooKassaService)
    {
        $this->yooKassaService = $yooKassaService;
    }

    /**
     * Показать страницу оформления заказа
     */
    public function index()
    {
        $cartItems = Cart::with(['photo.event'])
            ->where(function ($query) {
                if (Auth::check()) {
                    $query->where('user_id', Auth::id());
                } else {
                    $query->where('session_id', session()->getId());
                }
            })
            ->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index')
                ->with('error', 'Корзина пуста');
        }

        $total = $cartItems->sum(function ($item) {
            return $item->photo->getPriceWithCommission();
        });
        
        // Округляем сумму до целого числа для отображения
        $totalRounded = round($total);

        return view('checkout.index', compact('cartItems', 'total', 'totalRounded'));
    }

    /**
     * Создать заказ и перенаправить на оплату
     */
    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'phone' => 'nullable|string|max:20',
        ]);

        $cartItems = Cart::with(['photo.event'])
            ->where(function ($query) {
                if (Auth::check()) {
                    $query->where('user_id', Auth::id());
                } else {
                    $query->where('session_id', session()->getId());
                }
            })
            ->get();

        if ($cartItems->isEmpty()) {
            return back()->with('error', 'Корзина пуста');
        }

        $total = $cartItems->sum(function ($item) {
            return $item->photo->getPriceWithCommission();
        });
        
        // Округляем сумму до целого числа, чтобы она совпадала с отображаемой в корзине
        // Это гарантирует, что пользователь платит ту сумму, которую видит
        $totalRounded = round($total);

        // Создаем заказ
        // Для авторизованных пользователей используем данные из профиля, если не указаны
        $email = $request->email;
        $phone = $request->phone;
        
        if (Auth::check()) {
            // Если пользователь авторизован, используем данные из профиля как fallback
            if (empty($email)) {
                $email = Auth::user()->email;
            }
            if (empty($phone)) {
                $phone = Auth::user()->phone;
            }
        }
        
        $order = Order::create([
            'user_id' => Auth::id(),
            'email' => $email,
            'phone' => $phone,
            'total_amount' => $totalRounded, // Используем округленную сумму
            'status' => 'pending',
        ]);

        // Создаем элементы заказа (цена с комиссией, округленная)
        // Округляем цены элементов, чтобы сумма совпадала с общей суммой заказа
        $itemsTotal = 0;
        $items = [];
        foreach ($cartItems as $cartItem) {
            $itemPrice = round($cartItem->photo->getPriceWithCommission());
            $items[] = [
                'order_id' => $order->id,
                'photo_id' => $cartItem->photo_id,
                'price' => $itemPrice,
            ];
            $itemsTotal += $itemPrice;
        }
        
        // Если есть разница из-за округления, корректируем последний элемент
        if ($itemsTotal != $totalRounded && count($items) > 0) {
            $difference = $totalRounded - $itemsTotal;
            $items[count($items) - 1]['price'] += $difference;
        }
        
        // Создаем элементы заказа
        foreach ($items as $item) {
            OrderItem::create($item);
        }

        // Создаем платеж в YooKassa
        try {
            \Log::info("CheckoutController::store: Starting payment creation", [
                'order_id' => $order->id,
                'total_amount' => $total,
                'cart_items_count' => $cartItems->count(),
                'user_id' => Auth::id()
            ]);
            
            $returnUrl = route('orders.show', $order->id);
            \Log::info("CheckoutController::store: Return URL", ['return_url' => $returnUrl]);
            
            $payment = $this->yooKassaService->createPayment($order, $returnUrl);
            
            \Log::info("CheckoutController::store: Payment created successfully", [
                'order_id' => $order->id,
                'payment_id' => $payment['payment_id'] ?? null,
                'confirmation_url' => $payment['confirmation_url'] ?? null,
                'payment_array' => $payment
            ]);

            // Очищаем корзину ТОЛЬКО после успешного создания платежа
            $cartItems->each->delete();
            \Log::info("CheckoutController::store: Cart cleared", ['order_id' => $order->id]);

            // Редирект на страницу оплаты YooKassa
            if (!empty($payment['confirmation_url'])) {
                \Log::info("CheckoutController::store: Redirecting to payment", [
                    'confirmation_url' => $payment['confirmation_url']
                ]);
                return redirect()->away($payment['confirmation_url']);
            } else {
                \Log::error("CheckoutController::store: Confirmation URL is empty", [
                    'payment' => $payment
                ]);
                throw new \Exception('Confirmation URL не получен от YooKassa');
            }
        } catch (\Exception $e) {
            \Log::error("CheckoutController::store: Payment creation failed", [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Если ошибка при создании платежа, НЕ очищаем корзину
            // Удаляем созданный заказ, так как платеж не создан
            $order->items()->delete();
            $order->delete();
            
            return back()->with('error', 'Ошибка при создании платежа: ' . $e->getMessage())->withInput();
        }
    }
}
