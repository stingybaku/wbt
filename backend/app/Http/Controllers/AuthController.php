<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        return response()->json([
            'token' => Str::random(40),
            'user' => [
                'id' => 1,
                'email' => $data['email']
            ]
        ]);
    }

    public function logout()
    {
        return response()->json(['ok' => true]);
    }
}
