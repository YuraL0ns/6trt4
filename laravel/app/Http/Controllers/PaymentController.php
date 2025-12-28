<?php

namespace App\Http\Controllers;

use App\Services\Payment\YooKassaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected YooKassaService $yooKassaService;

    public function __construct(YooKassaService $yooKassaService)
    {
        $this->yooKassaService = $yooKassaService;
    }

    /**
     * Webhook от YooKassa
     * YooKassa отправляет уведомления через HTTP POST запросы (не вебсокеты)
     */
    public function webhook(Request $request)
    {
        // Проверяем IP адрес отправителя (для безопасности)
        $allowedIps = [
            '185.71.76.0/27',
            '185.71.77.0/27',
            '77.75.153.0/25',
            '77.75.156.11',
            '77.75.156.35',
            '77.75.154.128/25',
            '2a02:5180::/32',
        ];
        
        $clientIp = $request->ip();
        $ipAllowed = false;
        
        foreach ($allowedIps as $allowedIp) {
            if (strpos($allowedIp, '/') !== false) {
                // CIDR notation
                list($subnet, $mask) = explode('/', $allowedIp);
                if ($this->ipInRange($clientIp, $subnet, $mask)) {
                    $ipAllowed = true;
                    break;
                }
            } else {
                // Single IP
                if ($clientIp === $allowedIp) {
                    $ipAllowed = true;
                    break;
                }
            }
        }
        
        // В тестовом режиме разрешаем все IP (для разработки)
        if (config('services.yookassa.test_mode', true)) {
            $ipAllowed = true;
        }
        
        if (!$ipAllowed) {
            Log::warning("YooKassa webhook: IP not allowed", [
                'ip' => $clientIp,
                'user_agent' => $request->userAgent()
            ]);
            return response()->json(['status' => 'forbidden'], 403);
        }
        
        // Получаем данные из запроса
        $data = $request->all();
        
        // Валидация структуры данных
        if (!isset($data['event']) || !isset($data['object']['id'])) {
            Log::warning("YooKassa webhook: Invalid data structure", ['data' => $data]);
            return response()->json(['status' => 'invalid_data'], 400);
        }
        
        Log::info("YooKassa webhook received", [
            'event' => $data['event'],
            'payment_id' => $data['object']['id'] ?? null,
            'ip' => $clientIp
        ]);

        $result = $this->yooKassaService->handleWebhook($data);

        if ($result) {
            return response()->json(['status' => 'ok'], 200);
        }

        return response()->json(['status' => 'error'], 400);
    }
    
    /**
     * Проверка, находится ли IP в диапазоне CIDR
     */
    private function ipInRange(string $ip, string $subnet, int $mask): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskLong = -1 << (32 - $mask);
            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }
        return false;
    }

    /**
     * Проверка статуса платежа (для polling)
     * Автоматически обновляет статус заказа если платеж оплачен
     */
    public function checkStatus(string $paymentId)
    {
        try {
            $paymentInfo = $this->yooKassaService->checkPaymentStatus($paymentId);
            
            if (!$paymentInfo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment not found'
                ], 404);
            }

            // Получаем обновленный статус заказа
            $payment = \App\Models\Payment::where('yookassa_payment_id', $paymentId)->first();
            $orderStatus = $payment && $payment->order ? $payment->order->status : null;

            return response()->json([
                'status' => $paymentInfo->getStatus(),
                'paid' => $paymentInfo->getPaid(),
                'amount' => $paymentInfo->getAmount()->getValue(),
                'order_status' => $orderStatus,
            ]);
        } catch (\Exception $e) {
            Log::error("PaymentController::checkStatus error", [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
