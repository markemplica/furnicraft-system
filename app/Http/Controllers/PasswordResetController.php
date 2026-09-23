<?php

namespace App\Http\Controllers;

use App\Models\NotificationLog;
use App\Models\PasswordResetCode;
use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function showRequest()
    {
        return view('auth.forgot-password');
    }

    public function showReset(Request $request)
    {
        return view('auth.reset-password', ['email' => $request->session()->get('reset_email')]);
    }

    public function sendCode(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', $data['email'])->where('is_active', true)->first();
        if ($user) {
            if (PasswordResetCode::where('email', $user->email)->where('created_at', '>', now()->subMinute())->exists()) {
                return back()->withErrors(['email' => 'Please wait one minute before requesting another code.']);
            }
            PasswordResetCode::where('email', $user->email)->whereNull('used_at')->update(['used_at' => now()]);
            $code = (string) random_int(100000, 999999);
            PasswordResetCode::create(['email' => $user->email, 'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(15), 'requested_ip' => $request->ip()]);
            $log = NotificationLog::create(['user_id' => $user->id, 'type' => 'password_reset_code', 'recipient' => $user->email, 'subject' => 'Your FurniCraft password reset code', 'status' => 'queued']);
            $user->notify(new PasswordResetCodeNotification($code));
            if (config('queue.default') === 'sync') {
                $log->update(['status' => 'sent', 'sent_at' => now()]);
            }
        }
        $request->session()->put('reset_email', $data['email']);

        return redirect()->route('password.reset')->with('success', 'If that email belongs to an active account, a reset code has been sent.');
    }

    public function reset(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'code' => ['required', 'digits:6'], 'password' => ['required', 'confirmed', 'min:8']]);
        $record = PasswordResetCode::where('email', $data['email'])->whereNull('used_at')->where('expires_at', '>', now())->latest()->first();
        if (! $record || ! Hash::check($data['code'], $record->code_hash)) {
            return back()->withErrors(['code' => 'The code is incorrect or has expired.'])->withInput($request->only('email'));
        }
        $user = User::where('email', $data['email'])->where('is_active', true)->firstOrFail();
        $user->update(['password' => Hash::make($data['password']), 'remember_token' => Str::random(60)]);
        $record->update(['used_at' => now()]);
        $request->session()->forget('reset_email');

        return redirect()->route('login')->with('success', 'Your password has been changed. You can now sign in.');
    }
}
