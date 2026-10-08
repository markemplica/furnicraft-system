<?php

namespace App\Http\Controllers;

use App\Models\NotificationLog;
use App\Models\RegistrationVerificationCode;
use App\Models\User;
use App\Notifications\EmailVerificationCodeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        return view('auth.login', ['redirect' => $request->query('redirect')]);
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function showVerification(Request $request)
    {
        $email = $request->session()->get('registration_email', $request->query('email'));
        $user = $email
            ? User::where('email', Str::lower(trim($email)))->whereNull('email_verified_at')->first()
            : null;
        $latestCode = $user
            ? RegistrationVerificationCode::where('user_id', $user->id)->latest('id')->first()
            : null;

        return view('auth.verify-email', [
            'email' => $email,
            'resendAvailableAt' => $latestCode?->created_at?->copy()->addMinute()->timestamp,
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'The email address or password is incorrect.'])->onlyInput('email');
        }

        $user = $request->user();
        if (! $user->email_verified_at) {
            Auth::logout();
            $request->session()->put('registration_email', $user->email);

            return redirect()->route('verification.notice')->withErrors(['email' => 'Verify your email with the code we sent before signing in.']);
        }
        if (! $user->is_active) {
            Auth::logout();

            return back()->withErrors(['email' => 'This account is inactive. Please contact Lance Furniture Shop.']);
        }

        $request->session()->regenerate();
        $redirect = (string) $request->input('redirect');
        $sameHost = filled($redirect) && parse_url($redirect, PHP_URL_HOST) === $request->getHost();
        $relativePath = str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//');
        $fallback = $request->user()->isAdmin() ? route('admin.dashboard') : route('home');
        $target = ($sameHost || $relativePath) ? $redirect : $fallback;

        return redirect()->intended($target)->with('success', 'Welcome back, '.$request->user()->name.'.');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:50'],
            'middle_name' => ['nullable', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'birthdate' => ['required', 'date_format:Y-m-d', 'before:today'],
            'gender' => ['required', 'in:male,female,non_binary,prefer_not_to_say'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $name = implode(' ', array_filter([
            trim($data['first_name']),
            trim($data['middle_name'] ?? ''),
            trim($data['last_name']),
        ], fn ($part) => $part !== ''));

        if (Str::length($name) > 100) {
            return back()->withErrors(['last_name' => 'Your combined name must not exceed 100 characters.'])->withInput();
        }

        unset($data['first_name'], $data['middle_name'], $data['last_name']);
        $data['name'] = $name;
        $data['email'] = Str::lower(trim($data['email']));
        $user = User::create(array_merge($data, [
            'role' => 'customer',
            'password' => Hash::make($data['password']),
            'is_active' => false,
        ]));
        $this->issueRegistrationCode($user, $request);
        $request->session()->put('registration_email', $user->email);

        return redirect()->route('verification.notice')->with('success', 'We sent a verification code to your email. Enter it to activate your account.');
    }

    public function verifyEmail(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);
        $email = Str::lower(trim($data['email']));
        $user = User::where('email', $email)->whereNull('email_verified_at')->where('is_active', false)->first();
        $record = $user
            ? RegistrationVerificationCode::where('user_id', $user->id)->whereNull('used_at')->where('expires_at', '>', now())->latest('id')->first()
            : null;

        if (! $record || ! Hash::check($data['code'], $record->code_hash)) {
            if ($record) {
                $attempts = $record->attempts + 1;
                $record->update(['attempts' => $attempts, 'used_at' => $attempts >= 6 ? now() : null]);
            }

            return back()->withErrors(['code' => 'The verification code is incorrect or has expired. Request a new code and try again.'])->withInput(['email' => $email]);
        }

        $user->update(['email_verified_at' => now(), 'is_active' => true]);
        $record->update(['used_at' => now()]);
        RegistrationVerificationCode::where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now()]);
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('registration_email');

        return redirect()->route('catalog.index')->with('success', 'Your email is verified and your FurniCraft account is ready.');
    }

    public function resendVerificationCode(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $email = Str::lower(trim($data['email']));
        $user = User::where('email', $email)->whereNull('email_verified_at')->where('is_active', false)->first();

        if ($user) {
            $recentCode = RegistrationVerificationCode::where('user_id', $user->id)
                ->where('created_at', '>', now()->subMinute())
                ->exists();
            if ($recentCode) {
                return back()->withErrors(['email' => 'Please wait one minute before requesting another code.'])->withInput(['email' => $email]);
            }

            $this->issueRegistrationCode($user, $request);
            $request->session()->put('registration_email', $email);
        }

        return redirect()->route('verification.notice')->with('success', 'If the account is waiting for verification, a new code has been sent.');
    }

    private function issueRegistrationCode(User $user, Request $request): void
    {
        RegistrationVerificationCode::where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now()]);
        $code = (string) random_int(100000, 999999);
        RegistrationVerificationCode::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(15),
            'requested_ip' => $request->ip(),
        ]);
        $log = NotificationLog::create([
            'user_id' => $user->id,
            'type' => 'email_verification_code',
            'recipient' => $user->email,
            'subject' => 'Verify your FurniCraft account',
            'status' => 'queued',
        ]);
        $user->notify(new EmailVerificationCodeNotification($code));
        if (config('queue.default') === 'sync') {
            $log->update(['status' => 'sent', 'sent_at' => now()]);
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'You have been signed out.');
    }
}