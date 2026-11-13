<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_verification_and_login(): void
    {
        Notification::fake();
        $this->postJson('/api/auth/register', ['name' => 'Tester', 'email' => 'test@example.com',
            'password' => 'secret-password', 'password_confirmation' => 'secret-password'])->assertCreated();
        $user = User::where('email', 'test@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmailNotification::class);
        $credentials = ['email' => $user->email, 'password' => 'secret-password'];
        $this->postJson('/api/auth/login', $credentials)->assertForbidden();
        $this->get('/api/auth/verify-email?token='.$user->email_verification_token)->assertRedirect();
        $this->postJson('/api/auth/login', $credentials)->assertOk()->assertJsonStructure(['token']);
    }

    public function test_password_reset_notification_and_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();
        Notification::assertSentTo($user, ResetPasswordNotification::class);
        $token = Password::createToken($user);
        $this->postJson('/api/auth/reset-password', ['email' => $user->email, 'token' => $token,
            'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertOk();
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }
}
