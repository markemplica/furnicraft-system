<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'The email address or password is incorrect.'])->onlyInput('email');
        }
        if (! $request->user()->is_active) {
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
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:255', 'unique:users'], 'phone' => ['nullable', 'string', 'max:32'], 'password' => ['required', 'confirmed', 'min:8']]);
        $user = User::create(array_merge($data, ['role' => 'customer', 'password' => Hash::make($data['password'])]));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('catalog.index')->with('success', 'Your FurniCraft account is ready. Browse the Lance catalog, then set your preferences.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'You have been signed out.');
    }
}
