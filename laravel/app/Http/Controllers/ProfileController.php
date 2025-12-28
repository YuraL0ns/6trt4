<?php

namespace App\Http\Controllers;

use App\Models\GroupChangeRequest;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Показать профиль пользователя
     */
    public function index()
    {
        $user = Auth::user();
        
        // Получаем покупки пользователя (по user_id или email)
        $orders = Order::where(function($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->orWhere('email', $user->email);
        })
        ->with(['items.photo', 'payment'])
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get();
        
        return view('profile.index', compact('user', 'orders'));
    }

    /**
     * Показать форму редактирования профиля
     */
    public function edit()
    {
        $user = Auth::user();
        return view('profile.edit', compact('user'));
    }

    /**
     * Обновить профиль пользователя
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'last_name' => 'required|string|max:255',
            'second_name' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:255',
            'gender' => 'nullable|in:male,female',
            'password' => 'nullable|string|min:8|confirmed',
        ]);
        
        // Обновляем только разрешенные поля (кроме login и first_name)
        $user->last_name = $validated['last_name'];
        $user->second_name = $validated['second_name'] ?? null;
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? null;
        $user->city = $validated['city'] ?? null;
        $user->gender = $validated['gender'] ?? null;
        
        // Обновляем пароль, если указан
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        
        $user->save();
        
        return redirect()->route('profile.index')
            ->with('success', 'Профиль успешно обновлен');
    }

    /**
     * Показать форму заявки на смену группы
     */
    public function photoMe()
    {
        // Проверяем, нет ли уже активной заявки
        $existingRequest = GroupChangeRequest::where('user_id', Auth::id())
            ->where('status', true)
            ->first();

        if ($existingRequest) {
            return redirect()->route('home')
                ->with('info', 'У вас уже есть активная заявка на смену группы');
        }

        return view('profile.settings.photo_me');
    }

    /**
     * Сохранить заявку на смену группы
     */
    public function photoMeStore(Request $request)
    {
        $request->validate([
            'reason' => 'required|string|min:50|max:5000',
        ]);

        // Проверяем, нет ли уже активной заявки
        $existingRequest = GroupChangeRequest::where('user_id', Auth::id())
            ->where('status', true)
            ->first();

        if ($existingRequest) {
            return back()->with('error', 'У вас уже есть активная заявка');
        }

        GroupChangeRequest::create([
            'user_id' => Auth::id(),
            'grp_now' => Auth::user()->group,
            'grp_chg' => 'photo',
            'reason' => $request->reason,
            'status' => true,
        ]);

        // Создаем уведомление для всех администраторов
        $admins = \App\Models\User::where('group', 'admin')->get();
        foreach ($admins as $admin) {
            \App\Services\NotificationService::groupChangeRequestCreated(
                $admin->id,
                Auth::user()->full_name
            );
        }

        return redirect()->route('home')
            ->with('success', 'Заявка отправлена! Мы рассмотрим её в ближайшее время.');
    }
}
