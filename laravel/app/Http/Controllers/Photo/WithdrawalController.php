<?php

namespace App\Http\Controllers\Photo;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class WithdrawalController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // Администратор также имеет доступ к заявкам на вывод
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->isAdmin() && !auth()->user()->isPhotographer()) {
                abort(403, 'Доступ запрещен');
            }
            return $next($request);
        });
    }

    /**
     * Список заявок на вывод средств
     */
    public function index()
    {
        // Администратор видит все заявки, фотограф - только свои
        $query = Withdrawal::query();
        if (Auth::user()->isPhotographer()) {
            $query->where('photographer_id', Auth::id());
        }
        
        $withdrawals = $query->orderBy('created_at', 'desc')
            ->paginate(15);

        // КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Перезагружаем пользователя из базы данных для получения актуального баланса
        $user = Auth::user()->fresh();
        
        // Получаем баланс пользователя (для всех групп: admin, photo, moderator)
        $balance = $user->balance ?? 0;
        
        // ДИАГНОСТИКА: Логируем баланс для отладки
        \Log::info("WithdrawalController::index", [
            'user_id' => $user->id,
            'user_group' => $user->group,
            'balance' => $balance,
            'balance_type' => gettype($user->balance),
                'balance_raw' => method_exists($user, 'getRawOriginal') ? ($user->getRawOriginal('balance') ?? 'NULL') : ($user->getAttributes()['balance'] ?? 'NULL'),
            'is_photographer' => $user->isPhotographer()
        ]);

        return view('photo.withdrawals.index', compact('withdrawals', 'balance'));
    }

    /**
     * Создать заявку на вывод средств
     */
    public function store(Request $request)
    {
        // КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Перезагружаем пользователя из базы данных для получения актуального баланса
        $user = Auth::user()->fresh();
        $balance = $user->balance ?? 0;

        $request->validate([
            'amount' => 'required|numeric|min:1|max:' . $balance,
            'type' => 'required|in:individual,legal',
        ]);

        // Валидация в зависимости от типа
        if ($request->type === 'legal') {
            $request->validate([
                'inn' => 'required|string|max:20',
                'kpp' => 'required|string|max:20',
                'account' => 'required|string|max:50',
                'bank' => 'required|string|max:255',
                'organization_name' => 'required|string|max:255',
            ]);
        } else {
            // Валидация для физ.лица в зависимости от типа перевода
            $request->validate([
                'transfer_type' => 'required|in:sbp,card,account',
            ]);
            
            // СБП: нужны телефон и наименование банка
            if ($request->transfer_type === 'sbp') {
                $request->validate([
                    'phone' => 'required|string|max:20',
                    'bank_name' => 'required|string|max:255',
                ]);
            }
            // Карта: нужны номер карты и наименование банка (телефон НЕ нужен)
            elseif ($request->transfer_type === 'card') {
                $request->validate([
                    'card_number' => 'required|string|max:20',
                    'bank_name' => 'required|string|max:255',
                ]);
            }
            // Лицевой счет: нужны номер счета и наименование банка, комментарий необязателен
            elseif ($request->transfer_type === 'account') {
                $request->validate([
                    'account_number' => 'required|string|max:50',
                    'bank_name' => 'required|string|max:255',
                    'account_comment' => 'nullable|string|max:500',
                ]);
            }
        }
        
        // Валидация чека от пользователя (необязательно при создании, можно загрузить позже)
        $request->validate([
            'receipt_photographer' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        // Сохраняем чек от пользователя, если загружен
        $receiptPathPhotographer = null;
        if ($request->hasFile('receipt_photographer')) {
            $userId = Auth::id();
            $receiptPathPhotographer = $request->file('receipt_photographer')
                ->store("withdrawals/{$userId}", 'public');
        }
        
        $withdrawal = Withdrawal::create([
            'photographer_id' => Auth::id(),
            'amount' => $request->amount,
            'type' => $request->type,
            'status' => 'pending',
            'balance_before' => $balance,
            'balance_after' => $balance - $request->amount,
            // Для юр.лица
            'inn' => $request->inn ?? null,
            'kpp' => $request->kpp ?? null,
            'account' => $request->account ?? null,
            'bank' => $request->bank ?? null,
            'organization_name' => $request->organization_name ?? null,
            // Для физ.лица
            'transfer_type' => $request->transfer_type ?? null,
            'phone' => $request->phone ?? null,
            'card_number' => $request->card_number ?? null,
            'account_number' => $request->account_number ?? null,
            'bank_name' => $request->bank_name ?? null,
            'account_comment' => $request->account_comment ?? null,
            // Чек от пользователя
            'receipt_path_photographer' => $receiptPathPhotographer,
        ]);

        // Создаем уведомление для фотографа
        \App\Services\NotificationService::withdrawalCreated(Auth::id(), $request->amount);

        // Создаем уведомление для всех администраторов
        $admins = \App\Models\User::where('group', 'admin')->get();
        foreach ($admins as $admin) {
            \App\Services\NotificationService::newWithdrawalRequest(
                $admin->id,
                Auth::user()->full_name,
                $request->amount
            );
        }

        return back()->with('success', 'Заявка на вывод средств создана');
    }

    /**
     * Загрузить чек от пользователя (после получения средств)
     */
    public function uploadReceipt(Request $request, string $id)
    {
        $withdrawal = Withdrawal::where('photographer_id', Auth::id())
            ->findOrFail($id);
        
        $request->validate([
            'receipt_photographer' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);
        
        // Удаляем старый чек, если есть
        if ($withdrawal->receipt_path_photographer) {
            Storage::disk('public')->delete($withdrawal->receipt_path_photographer);
        }
        
        // Сохраняем новый чек
        $userId = Auth::id();
        $receiptPath = $request->file('receipt_photographer')
            ->store("withdrawals/{$userId}", 'public');
        
        $withdrawal->update([
            'receipt_path_photographer' => $receiptPath,
        ]);
        
        return back()->with('success', 'Чек успешно загружен');
    }

    /**
     * Показать чек (с проверкой доступа)
     */
    public function showReceipt(string $id, string $type)
    {
        $withdrawal = Withdrawal::where('photographer_id', Auth::id())
            ->findOrFail($id);
        
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
     * Получить текущий баланс пользователя (AJAX)
     */
    public function getBalance()
    {
        try {
            // КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Перезагружаем пользователя из базы данных для получения актуального баланса
            $user = Auth::user()->fresh();
            // Получаем баланс пользователя (для всех групп: admin, photo, moderator)
            $balance = $user->balance ?? 0;

            \Log::info("WithdrawalController::getBalance", [
                'user_id' => $user->id,
                'user_group' => $user->group,
                'balance' => $balance,
                'balance_type' => gettype($user->balance),
                'balance_raw' => method_exists($user, 'getRawOriginal') ? ($user->getRawOriginal('balance') ?? 'NULL') : ($user->getAttributes()['balance'] ?? 'NULL')
            ]);

            return response()->json([
                'balance' => floatval($balance),
                'balance_formatted' => number_format($balance, 2, ',', ' ')
            ]);
        } catch (\Exception $e) {
            \Log::error("WithdrawalController::getBalance error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'balance' => 0,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
