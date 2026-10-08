@extends('layouts.app')
@section('title','Verify your email')
@section('content')
<div class="auth-layout compact">
    <section class="auth-story">
        <a class="guest-brand" href="{{ route('home') }}"><span class="brand-mark">F</span> FurniCraft</a>
        <div><p class="section-kicker">Email verification</p><h1>Confirm your email to activate your account.</h1><p>Enter the 6-digit code sent to your email. It expires in 15 minutes.</p></div>
    </section>
    <section class="auth-panel">
        <h2>Verify your email</h2>
        <form method="POST" action="{{ route('verification.verify') }}" class="vstack gap-3">
            @csrf
            <div><label class="form-label" for="email">Email address</label><input class="form-control" id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email"></div>
            <div><label class="form-label" for="code">6-digit verification code</label><input class="form-control code-input" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code" autofocus></div>
            <button class="btn btn-primary btn-lg" type="submit">Verify email and create account</button>
        </form>
        <form method="POST" action="{{ route('verification.send') }}" class="mt-3" id="resend-code-form" data-resend-available-at="{{ $resendAvailableAt ?? '' }}">
            @csrf
            <input type="hidden" name="email" value="{{ old('email', $email) }}">
            <button class="btn btn-outline-primary w-100" id="resend-code-button" type="submit" aria-live="polite">Send a new code</button>
        </form>
        <p class="mt-4 mb-0">Already verified? <a href="{{ route('login') }}">Sign in</a></p>
    </section>
</div>
<script>
    (() => {
        const form = document.getElementById('resend-code-form');
        const button = document.getElementById('resend-code-button');
        if (!form || !button) return;

        const deadline = Number(form.dataset.resendAvailableAt || 0) * 1000;
        const updateCountdown = () => {
            const secondsLeft = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
            button.disabled = secondsLeft > 0;
            button.textContent = secondsLeft > 0 ? 'Resend code in ' + secondsLeft + 's' : 'Send a new code';
            return secondsLeft;
        };

        if (updateCountdown() > 0) {
            const timer = window.setInterval(() => {
                if (updateCountdown() === 0) window.clearInterval(timer);
            }, 1000);
        }
    })();
</script>
@endsection