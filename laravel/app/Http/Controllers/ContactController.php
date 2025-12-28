<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    /**
     * Показать форму обратной связи
     */
    public function index()
    {
        return view('contacts');
    }

    /**
     * Отправить сообщение
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        // Здесь можно добавить отправку email или сохранение в БД
        // Пока просто редирект с сообщением

        return back()->with('success', 'Ваше сообщение отправлено! Мы свяжемся с вами в ближайшее время.');
    }
}
