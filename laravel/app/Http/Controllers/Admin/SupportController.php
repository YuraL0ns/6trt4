<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('group:admin');
    }

    /**
     * Список тикетов техподдержки
     */
    public function index()
    {
        $tickets = SupportTicket::with(['user', 'messages'])
            ->orderBy('updated_at', 'desc')
            ->paginate(20);

        return view('admin.support.index', compact('tickets'));
    }

    /**
     * Показать тикет
     */
    public function show(string $id)
    {
        $ticket = SupportTicket::with(['user', 'messages.user'])
            ->findOrFail($id);

        return view('admin.support.show', compact('ticket'));
    }

    /**
     * Добавить ответ в тикет
     */
    public function addMessage(Request $request, string $id)
    {
        $ticket = SupportTicket::findOrFail($id);

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
            'admin_id' => Auth::id(),
        ]);

        return back()->with('success', 'Ответ отправлен');
    }

    /**
     * Закрыть тикет
     */
    public function close(string $id)
    {
        $ticket = SupportTicket::findOrFail($id);
        $ticket->update(['status' => 'closed']);

        return back()->with('success', 'Тикет закрыт');
    }
}
