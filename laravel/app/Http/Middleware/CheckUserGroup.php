<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserGroup
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response  $next
     * @param  string  ...$groups
     */
    public function handle(Request $request, Closure $next, string ...$groups): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Администратор имеет доступ ко всем маршрутам
        if ($user->isAdmin()) {
            return $next($request);
        }

        // Проверяем, входит ли группа пользователя в разрешенные
        if (!in_array($user->group, $groups)) {
            abort(403, 'Доступ запрещен');
        }

        return $next($request);
    }
}
