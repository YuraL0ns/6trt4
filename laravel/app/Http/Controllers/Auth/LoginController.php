<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    /**
     * Get the login username to be used by the controller.
     * Поддержка входа по email или логину
     *
     * @return string
     */
    public function username()
    {
        return 'login';
    }

    /**
     * Get the needed authorization credentials from the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function credentials(\Illuminate\Http\Request $request)
    {
        $login = $request->input('login');
        
        // Проверяем, является ли ввод email
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return [
                'email' => $login,
                'password' => $request->input('password'),
            ];
        }
        
        // Если не email, ищем по полю login
        return [
            'login' => $login,
            'password' => $request->input('password'),
        ];
    }
}
