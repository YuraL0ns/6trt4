<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;
use YooKassa\Client;
use YooKassa\Model\Payment\PaymentInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Exception;

class YooKassaService
{
    protected Client $client;
    protected string $shopId;
    protected string $secretKey;
    protected bool $testMode;

    public function __construct()
    {
        $this->shopId = config('services.yookassa.shop_id');
        $this->secretKey = config('services.yookassa.secret_key');
        $this->testMode = config('services.yookassa.test_mode', true);

        if (empty($this->shopId) || empty($this->secretKey)) {
            Log::error("YooKassaService: shop_id or secret_key is empty", [
                'shop_id_set' => !empty($this->shopId),
                'secret_key_set' => !empty($this->secretKey)
            ]);
            throw new \Exception('YooKassa credentials not configured. Please check services.yookassa.shop_id and services.yookassa.secret_key in config/services.php');
        }

        $this->client = new Client();
        $this->client->setAuth($this->shopId, $this->secretKey);
        
        Log::info("YooKassaService: Initialized", [
            'shop_id' => $this->shopId,
            'test_mode' => $this->testMode
        ]);
    }

    /**
     * Создать платеж в YooKassa
     */
    public function createPayment(Order $order, string $returnUrl): array
    {
        try {
            // Получаем webhook URL для уведомлений
            $webhookUrl = route('payment.webhook');
            
            $payment = $this->client->createPayment(
                [
                    'amount' => [
                        'value' => number_format($order->total_amount, 2, '.', ''),
                        'currency' => 'RUB',
                    ],
                    'confirmation' => [
                        'type' => 'redirect',
                        'return_url' => $returnUrl,
                    ],
                    'capture' => true,
                    'description' => "Заказ #{$order->id} - Hunter-Photo.Ru",
                    'metadata' => [
                        'order_id' => $order->id,
                        'user_id' => $order->user_id,
                    ],
                    // Примечание: Webhook URL нужно настроить в личном кабинете YooKassa
                    // Не передаем receipt, так как он не требуется для нашего случая
                ],
                uniqid('', true)
            );
            
            // Примечание: Webhook URL нужно настроить в личном кабинете YooKassa:
            // Настройки -> Уведомления -> URL для уведомлений: {$webhookUrl}
            Log::info("YooKassa createPayment: Webhook URL", [
                'webhook_url' => $webhookUrl,
                'payment_id' => $payment->getId()
            ]);

            // Сохраняем платеж в БД
            $paymentRecord = Payment::create([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'yookassa_payment_id' => $payment->getId(),
                'amount' => $order->total_amount,
                'status' => $payment->getStatus(),
            ]);

            // Обновляем статус заказа
            $order->update([
                'payment_id' => $payment->getId(),
                'status' => 'pending',
            ]);

            return [
                'success' => true,
                'payment_id' => $payment->getId(),
                'confirmation_url' => $payment->getConfirmation()->getConfirmationUrl(),
            ];
        } catch (Exception $e) {
            Log::error("YooKassa createPayment error", [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Проверить статус платежа и обновить в БД если изменился
     */
    public function checkPaymentStatus(string $paymentId): ?PaymentInterface
    {
        try {
            $paymentInfo = $this->client->getPaymentInfo($paymentId);
            
            if (!$paymentInfo) {
                return null;
            }
            
            // Находим платеж в БД и обновляем статус
            $payment = Payment::where('yookassa_payment_id', $paymentId)->first();
            if ($payment) {
                $newStatus = $paymentInfo->getStatus();
                $isPaid = $paymentInfo->getPaid();
                
                // Обновляем статус платежа если изменился
                if ($payment->status !== $newStatus) {
                    $payment->update(['status' => $newStatus]);
                    Log::info("YooKassa checkPaymentStatus: Payment status updated", [
                        'payment_id' => $paymentId,
                        'old_status' => $payment->getOriginal('status'),
                        'new_status' => $newStatus
                    ]);
                }
                
                // Если платеж оплачен, обновляем статус заказа
                if ($isPaid && $payment->order && $payment->order->status !== 'paid') {
                    $this->handlePaymentSucceeded($payment, $payment->order, $paymentInfo);
                    Log::info("YooKassa checkPaymentStatus: Order status updated to paid", [
                        'payment_id' => $paymentId,
                        'order_id' => $payment->order->id
                    ]);
                }
            }
            
            return $paymentInfo;
        } catch (Exception $e) {
            Log::error("YooKassa checkPaymentStatus error", [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Обработать webhook от YooKassa
     * YooKassa отправляет уведомления через HTTP POST (не вебсокеты)
     */
    public function handleWebhook(array $data): bool
    {
        try {
            Log::info("YooKassa webhook: Received data", ['data' => $data]);
            
            $event = $data['event'] ?? null;
            $paymentId = $data['object']['id'] ?? null;

            if (!$event || !$paymentId) {
                Log::warning("YooKassa webhook: Missing event or payment_id", [
                    'event' => $event,
                    'payment_id' => $paymentId,
                    'data' => $data
                ]);
                return false;
            }
            
            Log::info("YooKassa webhook: Processing event", [
                'event' => $event,
                'payment_id' => $paymentId
            ]);

            // Валидация типов событий
            $allowedEvents = ['payment.succeeded', 'payment.canceled', 'payment.waiting_for_capture', 'refund.succeeded'];
            if (!in_array($event, $allowedEvents)) {
                Log::warning("YooKassa webhook: Unknown event type", ['event' => $event, 'payment_id' => $paymentId]);
                return false;
            }

            // Получаем информацию о платеже напрямую от YooKassa для проверки
            $paymentInfo = $this->checkPaymentStatus($paymentId);
            if (!$paymentInfo) {
                Log::warning("YooKassa webhook: Payment not found in YooKassa", ['payment_id' => $paymentId]);
                return false;
            }

            // Проверяем, что сумма платежа совпадает
            $paymentAmount = (float)$paymentInfo->getAmount()->getValue();

            // Находим платеж в БД
            $payment = Payment::where('yookassa_payment_id', $paymentId)->first();
            if (!$payment) {
                Log::warning("YooKassa webhook: Payment not found in DB", ['payment_id' => $paymentId]);
                return false;
            }

            // Проверяем сумму платежа
            if (abs($payment->amount - $paymentAmount) > 0.01) {
                Log::error("YooKassa webhook: Amount mismatch", [
                    'payment_id' => $paymentId,
                    'db_amount' => $payment->amount,
                    'yookassa_amount' => $paymentAmount
                ]);
                return false;
            }

            $order = $payment->order;
            if (!$order) {
                Log::error("YooKassa webhook: Order not found", ['payment_id' => $paymentId, 'order_id' => $payment->order_id]);
                return false;
            }

            // Обрабатываем событие
            switch ($event) {
                case 'payment.succeeded':
                    $this->handlePaymentSucceeded($payment, $order, $paymentInfo);
                    break;

                case 'payment.canceled':
                    $this->handlePaymentCanceled($payment, $order);
                    break;

                case 'payment.waiting_for_capture':
                    $this->handlePaymentWaiting($payment, $order);
                    break;
                    
                case 'refund.succeeded':
                    Log::info("YooKassa webhook: Refund succeeded", [
                        'payment_id' => $paymentId,
                        'order_id' => $order->id
                    ]);
                    // Обработка возврата средств (если требуется)
                    break;
            }

            // Обновляем статус платежа
            $payment->update([
                'status' => $paymentInfo->getStatus(),
            ]);

            Log::info("YooKassa webhook: Successfully processed", [
                'event' => $event,
                'payment_id' => $paymentId,
                'order_id' => $order->id,
                'order_status' => $order->status,
                'payment_status' => $payment->status
            ]);

            return true;
        } catch (Exception $e) {
            Log::error("YooKassa handleWebhook error", [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Обработка успешного платежа
     */
    protected function handlePaymentSucceeded(Payment $payment, Order $order, PaymentInterface $paymentInfo): void
    {
        Log::info("YooKassa handlePaymentSucceeded: Starting", [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'current_order_status' => $order->status
        ]);
        
        if ($order->status === 'paid') {
            Log::info("YooKassa handlePaymentSucceeded: Order already paid, skipping", [
                'order_id' => $order->id
            ]);
            return; // Уже обработан
        }

        // Обновляем статус заказа
        $order->update([
            'status' => 'paid',
        ]);
        
        Log::info("YooKassa handlePaymentSucceeded: Order status updated to paid", [
            'order_id' => $order->id,
            'new_status' => $order->fresh()->status
        ]);

        // Распределяем средства
        try {
            $this->distributeFunds($order);
            Log::info("YooKassa handlePaymentSucceeded: Funds distributed", ['order_id' => $order->id]);
        } catch (\Exception $e) {
            Log::error("YooKassa handlePaymentSucceeded: Error distributing funds", [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }

        // Формируем ZIP архив с фотографиями
        try {
            $this->generateZipArchive($order);
            Log::info("YooKassa handlePaymentSucceeded: ZIP archive generated", ['order_id' => $order->id]);
        } catch (\Exception $e) {
            Log::error("YooKassa handlePaymentSucceeded: Error generating ZIP archive", [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }

        Log::info("YooKassa handlePaymentSucceeded: Completed successfully", [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'final_order_status' => $order->fresh()->status
        ]);
    }

    /**
     * Обработка отмененного платежа
     */
    protected function handlePaymentCanceled(Payment $payment, Order $order): void
    {
        $order->update([
            'status' => 'canceled',
        ]);

        Log::info("Payment canceled", [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
        ]);
    }

    /**
     * Обработка ожидающего платежа
     */
    protected function handlePaymentWaiting(Payment $payment, Order $order): void
    {
        // Платеж ожидает подтверждения
        Log::info("Payment waiting for capture", [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
        ]);
    }

    /**
     * Распределение средств после оплаты
     * 
     * Логика: цена фото 100₽, комиссия 20% = 20₽, итоговая цена для пользователя 120₽
     * Фотограф получает 100₽ на баланс
     */
    protected function distributeFunds(Order $order): void
    {
        foreach ($order->items as $item) {
            $photo = $item->photo;
            $event = $photo->event;
            $author = $event->author; // Автор события (может быть фотограф или администратор)

            // Базовая цена фотографии (цена, за которую фотограф выставил фото)
            // Если у фото есть своя цена, используем её, иначе цену события
            $basePrice = $photo->price ?: $event->price;
            
            // Цена с комиссией (что платит покупатель)
            $priceWithCommission = $item->price;
            
            // Комиссия платформы = разница между ценой с комиссией и базовой ценой
            $commission = $priceWithCommission - $basePrice;
            
            // Автор события (фотограф или администратор) получает базовую цену
            $authorEarning = $basePrice;

            // КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Перезагружаем автора из базы данных перед зачислением средств
            $author = $author->fresh();
            
            // Зачисляем средства автору события (фотографу или администратору)
            $author->increment('balance', $authorEarning);
            
            // КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Перезагружаем автора после зачисления для получения актуального баланса
            $author->refresh();
            $newBalance = $author->balance;

            Log::info("Funds distributed", [
                'order_id' => $order->id,
                'photo_id' => $photo->id,
                'author_id' => $author->id,
                'author_type' => $author->group,
                'total_paid' => $priceWithCommission,
                'base_price' => $basePrice,
                'commission' => $commission,
                'author_earning' => $authorEarning,
                'balance_before' => $newBalance - $authorEarning,
                'balance_after' => $newBalance,
            ]);
        }
    }

    /**
     * Генерация ZIP архива с купленными фотографиями
     * ВАЖНО: Используются файлы из original_photo (без водяного знака), а не custom_photo
     */
    public function generateZipArchive(Order $order): void
    {
        try {
            $zipPath = storage_path('app/public/orders/' . $order->id . '.zip');
            $zipDir = dirname($zipPath);
            
            if (!file_exists($zipDir)) {
                mkdir($zipDir, 0755, true);
            }

            $zip = new \ZipArchive();
            $zipOpenResult = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            if ($zipOpenResult !== TRUE) {
                $errorMsg = "Cannot create ZIP archive. Error code: {$zipOpenResult}";
                Log::error("ZIP archive creation failed", [
                    'order_id' => $order->id,
                    'zip_path' => $zipPath,
                    'error_code' => $zipOpenResult
                ]);
                throw new Exception($errorMsg);
            }

            $tempFiles = []; // Для временных файлов с S3
            $addedFilesCount = 0;
            $failedFilesCount = 0;

            foreach ($order->items as $item) {
                $photo = $item->photo;
                
                if (!$photo) {
                    Log::warning("Photo not found for order item", [
                        'order_id' => $order->id,
                        'item_id' => $item->id
                    ]);
                    $failedFilesCount++;
                    continue;
                }
                
                // ВАЖНО: Используем оригинальный файл (original_photo), а не custom_photo
                $filePath = null;
                $fileName = $photo->original_name ?? basename($photo->original_path ?? 'photo.jpg');
                
                // Очищаем имя файла от недопустимых символов
                $fileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
                
                if ($photo->s3_original_url) {
                    // Если файл на S3, скачиваем его временно
                    try {
                        $tempPath = storage_path('app/temp/' . uniqid('s3_') . '_' . $fileName);
                        $tempDir = dirname($tempPath);
                        
                        if (!file_exists($tempDir)) {
                            mkdir($tempDir, 0755, true);
                        }
                        
                        // Скачиваем файл с S3
                        $response = Http::timeout(60)->get($photo->s3_original_url);
                        
                        if ($response->successful()) {
                            file_put_contents($tempPath, $response->body());
                            $filePath = $tempPath;
                            $tempFiles[] = $tempPath; // Сохраняем для удаления после создания ZIP
                            
                            Log::info("Downloaded photo from S3 for ZIP", [
                                'order_id' => $order->id,
                                'photo_id' => $photo->id,
                                's3_url' => $photo->s3_original_url,
                                'temp_path' => $tempPath
                            ]);
                        } else {
                            Log::warning("Failed to download photo from S3", [
                                'order_id' => $order->id,
                                'photo_id' => $photo->id,
                                's3_url' => $photo->s3_original_url,
                                'status' => $response->status()
                            ]);
                            $failedFilesCount++;
                            continue;
                        }
                    } catch (Exception $e) {
                        Log::error("Error downloading photo from S3", [
                            'order_id' => $order->id,
                            'photo_id' => $photo->id,
                            's3_url' => $photo->s3_original_url,
                            'error' => $e->getMessage()
                        ]);
                        $failedFilesCount++;
                        continue;
                    }
                } else if ($photo->original_path) {
                    // Используем локальный файл из original_path
                    $filePath = storage_path('app/public/' . $photo->original_path);
                } else {
                    Log::warning("Photo has no original_path or s3_original_url", [
                        'order_id' => $order->id,
                        'photo_id' => $photo->id
                    ]);
                    $failedFilesCount++;
                    continue;
                }

                if ($filePath && file_exists($filePath)) {
                    if ($zip->addFile($filePath, $fileName)) {
                        $addedFilesCount++;
                        Log::debug("Added photo to ZIP", [
                            'order_id' => $order->id,
                            'photo_id' => $photo->id,
                            'file_path' => $filePath,
                            'file_name' => $fileName
                        ]);
                    } else {
                        Log::warning("Failed to add photo to ZIP", [
                            'order_id' => $order->id,
                            'photo_id' => $photo->id,
                            'file_path' => $filePath
                        ]);
                        $failedFilesCount++;
                    }
                } else {
                    Log::warning("Photo file not found for ZIP", [
                        'order_id' => $order->id,
                        'photo_id' => $photo->id,
                        'file_path' => $filePath,
                        's3_original_url' => $photo->s3_original_url,
                        'original_path' => $photo->original_path
                    ]);
                    $failedFilesCount++;
                }
            }

            // КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Закрываем архив только если добавлен хотя бы один файл
            if ($addedFilesCount === 0) {
                $zip->close();
                // Удаляем пустой архив
                if (file_exists($zipPath)) {
                    unlink($zipPath);
                }
                throw new Exception("No photos were added to ZIP archive. Added: {$addedFilesCount}, Failed: {$failedFilesCount}");
            }

            $zip->close();

            // Проверяем, что архив действительно создан
            if (!file_exists($zipPath)) {
                throw new Exception("ZIP archive file was not created at path: {$zipPath}");
            }

            // Удаляем временные файлы с S3
            foreach ($tempFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    try {
                        unlink($tempFile);
                    } catch (Exception $e) {
                        Log::warning("Failed to delete temp file", [
                            'temp_file' => $tempFile,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            // КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Сохраняем путь к архиву в заказе ТОЛЬКО если архив успешно создан
            $order->update([
                'zip_path' => 'orders/' . $order->id . '.zip',
            ]);

            Log::info("ZIP archive created successfully", [
                'order_id' => $order->id,
                'zip_path' => $zipPath,
                'zip_exists' => file_exists($zipPath),
                'zip_size' => file_exists($zipPath) ? filesize($zipPath) : 0,
                'photos_count' => $order->items->count(),
                'added_files' => $addedFilesCount,
                'failed_files' => $failedFilesCount,
                'temp_files_cleaned' => count($tempFiles)
            ]);
        } catch (Exception $e) {
            Log::error("ZIP archive creation error", [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Пробрасываем исключение, чтобы вызывающий код знал об ошибке
            throw $e;
        }
    }
}

