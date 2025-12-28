<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GroupChangeRequest;
use App\Models\User;
use Illuminate\Http\Request;

class GroupRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('group:admin');
    }

    /**
     * Список заявок на смену группы
     */
    public function index()
    {
        $requests = GroupChangeRequest::where('status', true)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.group-requests.index', compact('requests'));
    }

    /**
     * Одобрить заявку
     */
    public function approve(string $id)
    {
        $request = GroupChangeRequest::findOrFail($id);

        $user = $request->user;
        $user->update([
            'group' => $request->grp_chg,
        ]);

        // Генерируем hash_login для фотографа
        if ($request->grp_chg === 'photo') {
            $hashLogin = $user->email . '-' . substr(md5($user->id), 0, 5);
            $user->update(['hash_login' => $hashLogin]);
        }

        $request->update(['status' => false]);

        // Создаем уведомление для пользователя
        \App\Services\NotificationService::groupChangeApproved($user->id);

        return back()->with('success', 'Заявка одобрена');
    }

    /**
     * Отклонить заявку
     */
    public function reject(string $id)
    {
        $request = GroupChangeRequest::findOrFail($id);
        $request->update(['status' => false]);

        // Создаем уведомление для пользователя
        \App\Services\NotificationService::groupChangeRejected($request->user_id);

        return back()->with('success', 'Заявка отклонена');
    }
}
