@extends('layouts.app')
@section('title','Sign in')
@section('content')
<div class="auth-layout">
    <section class="auth-story">
        <a class="guest-brand" href="{{ route('login') }}"><span class="brand-mark">F</span> FurniCraft</a>
        <div>
            <p class="section-kicker">Lance Furniture Shop</p>
            <h1>Furniture choices that begin with your room.</h1>
            <p>Compare locally crafted pieces by size, budget, wood, finish, and style. Your catalog and planning tools are protected behind sign-in.</p>
        </div>
        <p class="small mb-0">Web-based planning only. No payments, delivery tracking, 3D, or AR.</p>
    </section>
    <section class="auth-panel">
        <h2>Sign in</h2>
        <p class="text-muted">Use your customer or administrator account.</p>
        @if($redirect)<div class="alert alert-info fc-alert"><strong>Log in to access.</strong> Sign in to open the catalog, recommendations, room preview, or featured furniture.</div>@endif
        <form method="POST" action="{{ route('login.store') }}" class="vstack gap-3">@csrf
            <input type="hidden" name="redirect" value="{{ old('redirect', $redirect) }}">
            <div><label class="form-label" for="email">Email address</label><input class="form-control" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"></div>
            <div><div class="d-flex justify-content-between"><label class="form-label" for="password">Password</label><a href="{{ route('password.request') }}">Forgot password?</a></div><input class="form-control" id="password" type="password" name="password" required autocomplete="current-password"></div>
            <label class="form-check"><input class="form-check-input" type="checkbox" name="remember" value="1"><span class="form-check-label">Keep me signed in</span></label>
            <button class="btn btn-primary btn-lg" type="submit">Sign in</button>
        </form>
        <p class="mt-4 mb-0">New to FurniCraft? <a href="{{ route('register') }}">Create a customer account</a></p>
        <div class="demo-credentials"><strong>Demo access</strong><br>Customer: demo@furnicraft.local / password<br>Admin: admin@furnicraft.local / password</div>
    </section>
</div>
@endsection
