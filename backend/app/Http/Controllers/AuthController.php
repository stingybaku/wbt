<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|email|unique:users,email',
            'password'              => 'required|string|min:8|confirmed',
        ]);

        $userRole = Role::where('slug', 'user')->firstOrFail();

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role_id'  => $userRole->id,
        ]);

        event(new Registered($user));

        return response()->json(['message' => 'Account created. Please check your email to verify your address.'], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = Auth::user();

        if (!$user->hasVerifiedEmail()) {
            Auth::logout();
            return response()->json([
                'message' => 'Please verify your email address before logging in.',
                'code'    => 'email_unverified',
            ], 403);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token'               => $token,
            'must_change_password' => (bool) $user->must_change_password,
            'user'                => [
                'id'    => $user->id,
                'email' => $user->email,
                'name'  => $user->name,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }

    public function verifyEmail(Request $request, int $id, string $hash)
    {
        $user = User::findOrFail($id);

        if (!hash_equals(sha1($user->email), $hash)) {
            return response()->json(['message' => 'Invalid verification link.'], 403);
        }

        if (!$request->hasValidSignature()) {
            return response()->json(['message' => 'Verification link has expired.'], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect(config('app.frontend_url') . '/login?verified=already');
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return redirect(config('app.frontend_url') . '/login?verified=1');
    }

    public function resendVerification(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);

        $user = User::where('email', $data['email'])->first();

        if (!$user) {
            // Don't reveal whether the email exists
            return response()->json(['message' => 'If that email is registered, a verification link has been sent.']);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.'], 422);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification email sent.']);
    }
}
