<?php

namespace App\Http\Controllers;

use App\Models\NotificationLog;
use App\Models\PasswordResetCode;
use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class PasswordResetController extends Controller
{
    public function showRequest()
    {
        return view('auth.forgot-password');
    }

    public function showReset(Request $request)
    {
        $email = $request->session()->get('reset_email');
        $latestCode = $email
            ? PasswordResetCode::where('email', $email)->latest()->first()
            : null;

        return view('auth.reset-password', [
            'email' => $email,
            'resendAvailableAt' => $latestCode?->created_at?->copy()->addMinute()->timestamp,
        ]);
    }

    public function sendCode(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $email = strtolower($data['email']);
        $request->session()->put('reset_email', $email);
        $user = User::where('email', $email)->where('is_active', true)->first();

        if (! $user) {
            return redirect()->route('password.reset')
                ->with('success', 'If that email belongs to an active account, a reset code has been sent.');
        }

        if ($this->codeIsCoolingDown($email)) {
            return redirect()->route('password.reset')
                ->with('warning', 'Please wait for the 60-second countdown before requesting another code.');
        }

        if (! $this->deliverCode($user, $request)) {
            return redirect()->route('password.reset')->withErrors([
                'email' => 'The code could not be sent because FurniCraft could not connect to Gmail SMTP. Check the computer’s internet connection or firewall, then try again.',
            ]);
        }

        return redirect()->route('password.reset')
            ->with('success', 'The reset code was sent. Check your inbox and spam folder.');
    }

    public function resendCode(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $email = strtolower($data['email']);
        $sessionEmail = strtolower((string) $request->session()->get('reset_email', ''));

        if ($sessionEmail === '' || ! hash_equals($sessionEmail, $email)) {
            return redirect()->route('password.request')
                ->withErrors(['email' => 'Request a reset code for this email first.']);
        }

        if ($this->codeIsCoolingDown($email)) {
            return redirect()->route('password.reset')
                ->with('warning', 'Please wait for the 60-second countdown before requesting another code.');
        }

        $user = User::where('email', $email)->where('is_active', true)->first();
        if (! $user) {
            return redirect()->route('password.reset')
                ->with('success', 'If that email belongs to an active account, a reset code has been sent.');
        }

        if (! $this->deliverCode($user, $request)) {
            return redirect()->route('password.reset')->withErrors([
                'email' => 'The code could not be sent because FurniCraft could not connect to Gmail SMTP. Check the computer’s internet connection or firewall, then try again.',
            ]);
        }

        return redirect()->route('password.reset')
            ->with('success', 'A new reset code was sent. Check your inbox and spam folder.');
    }

    public function reset(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'code' => ['required', 'digits:6'], 'password' => ['required', 'confirmed', 'min:8']]);
        $record = PasswordResetCode::where('email', strtolower($data['email']))->whereNull('used_at')->where('expires_at', '>', now())->latest()->first();
        if (! $record || ! Hash::check($data['code'], $record->code_hash)) {
            return back()->withErrors(['code' => 'The code is incorrect or has expired.'])->withInput($request->only('email'));
        }
        $user = User::where('email', strtolower($data['email']))->where('is_active', true)->firstOrFail();
        $user->update(['password' => Hash::make($data['password']), 'remember_token' => Str::random(60)]);
        PasswordResetCode::where('email', $record->email)->whereNull('used_at')->update(['used_at' => now()]);
        $request->session()->forget('reset_email');

        return redirect()->route('login')->with('success', 'Your password has been changed. You can now sign in.');
    }

    private function codeIsCoolingDown(string $email): bool
    {
        return PasswordResetCode::where('email', $email)
            ->where('created_at', '>', now()->subSeconds(60))
            ->exists();
    }

    private function deliverCode(User $user, Request $request): bool
    {
        $code = (string) random_int(100000, 999999);
        $record = PasswordResetCode::create([
            'email' => $user->email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(15),
            'requested_ip' => $request->ip(),
        ]);
        $log = NotificationLog::create([
            'user_id' => $user->id,
            'type' => 'password_reset_code',
            'recipient' => $user->email,
            'subject' => 'Your FurniCraft password reset code',
            'status' => 'queued',
        ]);

        try {
            $user->notify(new PasswordResetCodeNotification($code));
        } catch (Throwable $exception) {
            $record->update(['used_at' => now()]);
            $log->update(['status' => 'failed', 'payload' => ['error_type' => class_basename($exception)]]);
            report($exception);

            return false;
        }

        PasswordResetCode::where('email', $user->email)
            ->whereNull('used_at')
            ->whereKeyNot($record->getKey())
            ->update(['used_at' => now()]);
        $log->update(['status' => 'sent', 'sent_at' => now()]);

        return true;
    }
}