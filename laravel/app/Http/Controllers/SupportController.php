<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\SupportMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Список тикетов пользователя
     */
    public function index()
    {
        $tickets = SupportTicket::where('user_id', Auth::id())
            ->with('messages')
            ->orderBy('updated_at', 'desc')
            ->paginate(10);

        return view('support.index', compact('tickets'));
    }

    /**
     * Показать тикет
     */
    public function show(string $id)
    {
        $ticket = SupportTicket::where('user_id', Auth::id())
            ->with('messages.user')
            ->findOrFail($id);

        return view('support.show', compact('ticket'));
    }

    /**
     * Создать новый тикет
     */
    public function store(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'type' => 'required|in:technical,payment,photographer,other',
            'message' => 'required|string|max:5000',
        ]);

        $ticket = SupportTicket::create([
            'user_id' => Auth::id(),
            'subject' => $request->subject,
            'type' => $request->type,
            'status' => 'open',
        ]);

        SupportMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'message' => $request->message,
        ]);

        // Создаем уведомление для всех администраторов
        $admins = \App\Models\User::where('group', 'admin')->get();
        foreach ($admins as $admin) {
            \App\Services\NotificationService::newSupportTicket(
                $admin->id,
                Auth::user()->full_name,
                $ticket->id
            );
        }

        return redirect()->route('support.show', $ticket->id)
            ->with('success', 'Тикет создан');
    }

    /**
     * Добавить сообщение в тикет
     */
    public function addMessage(Request $request, string $id)
    {
        $ticket = SupportTicket::where('user_id', Auth::id())
            ->findOrFail($id);

        $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        SupportMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'message' => $request->message,
        ]);

        $ticket->update([
            'last_replied_by' => Auth::id(),
        ]);

        // Создаем уведомление для администраторов
        $admins = \App\Models\User::where('group', 'admin')->get();
        foreach ($admins as $admin) {
            \App\Services\NotificationService::supportMessageForAdmin(
                $admin->id,
                Auth::user()->full_name,
                $ticket->id
            );
        }

        return back()->with('success', 'Сообщение отправлено');
    }
}
