@extends('layouts.app')
@section('title','Create account')
@section('content')
<div class="auth-layout compact">
    <section class="auth-story"><a class="guest-brand" href="{{ route('login') }}"><span class="brand-mark">F</span> FurniCraft</a><div><p class="section-kicker">Customer account</p><h1>Plan with measurements, not guesswork.</h1><p>Save preferences, compare recommendations, preview placement, and send reservation requests to Lance Furniture Shop.</p></div></section>
    <section class="auth-panel"><h2>Create account</h2><p class="text-muted">All catalog features require an account.</p>
        <form method="POST" action="{{ route('register.store') }}" class="vstack gap-3">@csrf
            <div><label class="form-label" for="name">Full name</label><input class="form-control" id="name" name="name" value="{{ old('name') }}" required autocomplete="name"></div>
            <div><label class="form-label" for="email">Email address</label><input class="form-control" id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></div>
            <div><label class="form-label" for="phone">Phone <span class="text-muted">optional</span></label><input class="form-control" id="phone" name="phone" value="{{ old('phone') }}" autocomplete="tel"></div>
            <div class="row g-3"><div class="col-sm-6"><label class="form-label" for="password">Password</label><input class="form-control" id="password" type="password" name="password" required autocomplete="new-password"></div><div class="col-sm-6"><label class="form-label" for="password_confirmation">Confirm password</label><input class="form-control" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"></div></div>
            <button class="btn btn-primary btn-lg" type="submit">Create account</button>
        </form><p class="mt-4 mb-0">Already registered? <a href="{{ route('login') }}">Sign in</a></p>
    </section>
</div>
@endsection
