<?php

namespace App\Http\Controllers\Photo;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        // Для администратора баланс не имеет смысла, для фотографа - его баланс
        $balance = Auth::user()->isAdmin() ? 0 : Auth::user()->balance;

        return view('photo.withdrawals.index', compact('withdrawals', 'balance'));
    }

    /**
     * Создать заявку на вывод средств
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $balance = $user->balance;

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
            $request->validate([
                'transfer_type' => 'required|in:sbp,card,account',
                'phone' => 'required_if:transfer_type,sbp|string|max:20',
                'card_number' => 'required_if:transfer_type,card|string|max:20',
                'account_number' => 'required_if:transfer_type,account|string|max:50',
                'bank_name' => 'required_if:transfer_type,sbp|string|max:255',
                'account_comment' => 'nullable|string|max:500',
            ]);
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
}
