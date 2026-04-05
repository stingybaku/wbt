<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ChangePasswordController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'current_password'          => 'required|string',
            'new_password'              => 'required|string|min:8',
            'new_password_confirmation' => 'required|string|same:new_password',
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $request->user()->update([
            'password'             => $data['new_password'],
            'must_change_password' => false,
        ]);

        return response()->json(['ok' => true]);
    }
}
