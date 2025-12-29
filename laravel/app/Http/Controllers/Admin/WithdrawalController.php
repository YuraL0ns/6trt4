<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WithdrawalController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('group:admin');
    }

    /**
     * Список заявок на вывод средств
     */
    public function index()
    {
        $withdrawals = Withdrawal::with('photographer')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.withdrawals.index', compact('withdrawals'));
    }

    /**
     * Показать чек (для администратора)
     */
    public function showReceipt(string $id, string $type)
    {
        $withdrawal = Withdrawal::findOrFail($id);
        
        // Определяем путь к чеку
        $receiptPath = null;
        if ($type === 'admin') {
            $receiptPath = $withdrawal->receipt_path_admin;
        } elseif ($type === 'photographer') {
            $receiptPath = $withdrawal->receipt_path_photographer;
        } else {
            abort(404);
        }
        
        if (!$receiptPath) {
            abort(404, 'Чек не найден');
        }
        
        $fullPath = storage_path('app/public/' . $receiptPath);
        
        if (!file_exists($fullPath)) {
            abort(404, 'Файл не найден');
        }
        
        $mimeType = mime_content_type($fullPath);
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($receiptPath) . '"',
        ];
        
        return response()->file($fullPath, $headers);
    }

    /**
     * Показать заявку на вывод
     */
    public function show(string $id)
    {
        $withdrawal = Withdrawal::with('photographer')
            ->findOrFail($id);

        // Статистика фотографа
        $photographerStats = [
            'total_earnings' => $withdrawal->photographer->balance + $withdrawal->amount,
            'withdrawals_history' => Withdrawal::where('photographer_id', $withdrawal->photographer_id)
                ->where('id', '!=', $id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(),
        ];

        return view('admin.withdrawals.show', compact('withdrawal', 'photographerStats'));
    }

    /**
     * Одобрить заявку на вывод
     */
    public function approve(Request $request, string $id)
    {
        $withdrawal = Withdrawal::findOrFail($id);

        $request->validate([
            'receipt' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        // Сохраняем чек администратора в папку storage/public/withdrawals/{userId}
        $userId = $withdrawal->photographer_id;
        $receiptPath = $request->file('receipt')->store("withdrawals/{$userId}", 'public');

        // Получаем процент налога с вывода
        $taxPercent = \App\Models\Setting::get('percent_for_salary', 6);
        
        // Рассчитываем сумму к выводу с учетом налога
        // Если фотограф запросил 100₽, а налог 6%, то:
        // - С баланса списываем 100₽ (полную сумму)
        // - Фотограф получает 94₽ (100 - 6%)
        $taxAmount = $withdrawal->amount * ($taxPercent / 100);
        $finalAmount = $withdrawal->amount - $taxAmount;

        // Списываем средства с баланса фотографа (полную сумму запроса)
        $photographer = $withdrawal->photographer;
        $photographer->decrement('balance', $withdrawal->amount);

        // Обновляем заявку (сохраняем информацию о налоге)
        $withdrawal->update([
            'status' => 'approved',
            'receipt_path_admin' => $receiptPath,
            'tax_percent' => $taxPercent,
            'tax_amount' => $taxAmount,
            'final_amount' => $finalAmount,
        ]);

        // Создаем уведомление для фотографа
        \App\Services\NotificationService::withdrawalApproved(
            $photographer->id,
            $withdrawal->amount,
            $finalAmount
        );

        return back()->with('success', 'Заявка одобрена');
    }

    /**
     * Отклонить заявку на вывод
     */
    public function reject(string $id)
    {
        $withdrawal = Withdrawal::findOrFail($id);

        $withdrawal->update([
            'status' => 'rejected',
        ]);

        // Возвращаем средства на баланс
        $photographer = $withdrawal->photographer;
        $photographer->increment('balance', $withdrawal->amount);

        // Создаем уведомление для фотографа
        \App\Services\NotificationService::withdrawalRejected(
            $photographer->id,
            $withdrawal->amount
        );

        return back()->with('success', 'Заявка отклонена');
    }
}
