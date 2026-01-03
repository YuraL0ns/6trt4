<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    /**
     * Показать корзину
     */
    public function index()
    {
        $cartItems = Cart::with(['photo.event'])
            ->where(function ($query) {
                if (Auth::check()) {
                    $query->where('user_id', Auth::id());
                } else {
                    $query->where('session_id', session()->getId());
                }
            })
            ->get();

        $total = $cartItems->sum(function ($item) {
            return $item->photo->getPriceWithCommission();
        });
        
        // Округляем сумму до целого числа для отображения
        $totalRounded = round($total);

        return view('cart.index', compact('cartItems', 'total', 'totalRounded'));
    }

    /**
     * Добавить фотографию в корзину
     */
    public function store(Request $request)
    {
        $request->validate([
            'photo_id' => 'required|uuid|exists:photos,id',
        ]);

        $photo = Photo::findOrFail($request->photo_id);

        // Проверяем, не добавлена ли уже фотография
        $exists = Cart::where('photo_id', $request->photo_id)
            ->where(function ($query) {
                if (Auth::check()) {
                    $query->where('user_id', Auth::id());
                } else {
                    $query->where('session_id', session()->getId());
                }
            })
            ->exists();

        if ($exists) {
            // Если это AJAX запрос, возвращаем JSON
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Фотография уже в корзине'
                ], 400);
            }
            return back()->with('error', 'Фотография уже в корзине');
        }

        Cart::create([
            'user_id' => Auth::id(),
            'session_id' => session()->getId(),
            'photo_id' => $request->photo_id,
        ]);

        // Если это AJAX запрос, возвращаем JSON
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Фотография добавлена в корзину'
            ]);
        }

        return back()->with('success', 'Фотография добавлена в корзину');
    }

    /**
     * Удалить фотографию из корзины
     */
    public function destroy(string $id)
    {
        $cart = Cart::where(function ($query) {
            if (Auth::check()) {
                $query->where('user_id', Auth::id());
            } else {
                $query->where('session_id', session()->getId());
            }
        })
            ->findOrFail($id);

        $cart->delete();

        return back()->with('success', 'Фотография удалена из корзины');
    }
}
