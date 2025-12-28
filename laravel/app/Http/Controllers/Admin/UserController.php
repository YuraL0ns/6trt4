<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('group:admin');
    }

    /**
     * Список всех пользователей
     */
    public function index()
    {
        try {
            $users = User::orderBy('created_at', 'desc')
                ->paginate(20);

            return view('admin.users.index', compact('users'));
        } catch (\Exception $e) {
            // Безопасное логирование - оборачиваем в try-catch на случай проблем с конфигурацией логирования
            try {
                \Log::error("Admin/UserController::index error", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
            } catch (\Exception $logException) {
                // Если логирование не работает, просто продолжаем
            }
            
            // Возвращаем более информативную ошибку для разработки
            if (config('app.debug')) {
                return response()->json([
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ], 500);
            }
            
            abort(500, 'Ошибка при загрузке пользователей. Проверьте логи для деталей.');
        }
    }

    /**
     * Изменить группу пользователя
     */
    public function changeGroup(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'group' => 'required|in:user,photo,admin,blocked',
        ]);

        $user->update(['group' => $request->group]);

        return back()->with('success', 'Группа пользователя изменена');
    }

    /**
     * Изменить пароль пользователя
     */
    public function changePassword(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('success', 'Пароль изменен');
    }

    /**
     * Заблокировать/разблокировать пользователя
     */
    public function toggleBlock(string $id)
    {
        $user = User::findOrFail($id);

        $user->update([
            'status' => $user->status === 'active' ? 'blocked' : 'active',
        ]);

        return back()->with('success', 'Статус пользователя изменен');
    }

    /**
     * Просмотр профиля пользователя
     */
    public function show(string $id)
    {
        $user = User::with(['orders.items.photo', 'events', 'withdrawals'])
            ->findOrFail($id);

        // Если запрос JSON (для модального окна)
        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'second_name' => $user->second_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'city' => $user->city,
                'gender' => $user->gender,
            ]);
        }

        // Статистика для пользователя
        $userStats = [
            'orders_count' => $user->orders()->count(),
            'total_spent' => $user->orders()->where('status', 'paid')->sum('total_amount'),
        ];

        // Статистика для фотографа
        $photographerStats = null;
        if ($user->isPhotographer()) {
            $photographerStats = [
                'events_count' => $user->events()->count(),
                'balance' => $user->balance,
                'withdrawals_count' => $user->withdrawals()->count(),
                'total_earned' => $user->withdrawals()->where('status', 'approved')->sum('amount'),
            ];
        }

        return view('admin.users.show', compact('user', 'userStats', 'photographerStats'));
    }

    /**
     * Обновить информацию о пользователе
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'second_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:255',
            'gender' => 'nullable|in:male,female',
        ]);

        $user->update($request->only([
            'first_name', 'last_name', 'second_name', 'email', 'phone', 'city', 'gender'
        ]));

        return back()->with('success', 'Информация о пользователе обновлена');
    }
}
