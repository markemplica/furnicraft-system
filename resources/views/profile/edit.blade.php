@extends('layouts.app')
@section('title','Profile settings')
@section('content')
<header class="page-heading">
    <div>
        <p class="section-kicker">Customer account</p>
        <h1>Profile settings</h1>
        <p>Keep your contact information current and protect your FurniCraft account with a secure password.</p>
    </div>
    <a class="btn btn-outline-dark" href="{{ route('catalog.index') }}">Return to catalog</a>
</header>

<div class="row g-4 align-items-start">
    <div class="col-lg-8">
        <section class="panel" aria-labelledby="personal-information-heading">
            <p class="section-kicker mb-1">Account details</p>
            <h2 id="personal-information-heading" class="h3">Personal information</h2>
            <p class="text-muted">Your email is used to sign in and receive reservation notifications.</p>

            <form method="POST" action="{{ route('profile.update') }}" class="row g-3 mt-1">
                @csrf
                @method('PATCH')
                <div class="col-md-6">
                    <label class="form-label" for="name">Full name</label>
                    <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="100" autocomplete="name" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="phone">Phone number</label>
                    <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" type="tel" value="{{ old('phone', $user->phone) }}" maxlength="32" autocomplete="tel" placeholder="e.g. 0917 123 4567">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="email">Email address</label>
                    <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email', $user->email) }}" maxlength="255" autocomplete="email" required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">Save profile</button>
                </div>
            </form>
        </section>

        <section class="panel mt-4" aria-labelledby="password-heading">
            <p class="section-kicker mb-1">Account security</p>
            <h2 id="password-heading" class="h3">Change password</h2>
            <p class="text-muted">Enter your current password before choosing a new password with at least eight characters.</p>

            <form method="POST" action="{{ route('profile.password') }}" class="row g-3 mt-1">
                @csrf
                @method('PUT')
                <div class="col-12">
                    <label class="form-label" for="current_password">Current password</label>
                    <input class="form-control @error('current_password') is-invalid @enderror" id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                    @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password">New password</label>
                    <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password_confirmation">Confirm new password</label>
                    <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
                </div>
                <div class="col-12">
                    <button class="btn btn-dark" type="submit">Change password</button>
                </div>
            </form>
        </section>
    </div>

    <aside class="col-lg-4">
        <div class="panel">
            <p class="section-kicker mb-1">Account summary</p>
            <h2 class="h4 mb-3">{{ $user->name }}</h2>
            <dl class="vstack gap-3 mb-0">
                <div><dt class="small text-uppercase text-muted">Account type</dt><dd class="mb-0">Customer</dd></div>
                <div><dt class="small text-uppercase text-muted">Status</dt><dd class="mb-0"><span class="status status-{{ $user->is_active ? 'active' : 'inactive' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span></dd></div>
                <div><dt class="small text-uppercase text-muted">Member since</dt><dd class="mb-0">{{ $user->created_at->format('F j, Y') }}</dd></div>
            </dl>
        </div>
        <div class="alert alert-info fc-alert mt-4" role="note"><strong>Privacy reminder</strong><br>Never share your password or reset code. FurniCraft staff will not ask for them.</div>
    </aside>
</div>
@endsection
