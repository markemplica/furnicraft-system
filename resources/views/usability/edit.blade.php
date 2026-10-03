@extends('layouts.app')
@section('title', 'System usability survey')
@section('content')
<header class="page-heading"><div><p class="section-kicker">Usability evaluation · SUS</p><h1>Tell us how FurniCraft was to use.</h1><p>This 10-item System Usability Scale survey measures usability only. It does not measure recommendation relevance or room-placement accuracy. Your response is voluntary and may be updated.</p></div></header>

@php
    $questions = [
        'I think that I would like to use this system frequently.',
        'I found the system unnecessarily complex.',
        'I thought the system was easy to use.',
        'I think that I would need the support of a technical person to be able to use this system.',
        'I found the various functions in this system were well integrated.',
        'I thought there was too much inconsistency in this system.',
        'I would imagine that most people would learn to use this system very quickly.',
        'I found the system very cumbersome to use.',
        'I felt very confident using the system.',
        'I needed to learn a lot of things before I could get going with this system.',
    ];
    $answers = $response?->answers ?? [];
@endphp
<form method="POST" action="{{ route('usability.store') }}" class="panel">
    @csrf
    <p class="small text-muted">For each statement, choose one response from strongly disagree to strongly agree.</p>
    @foreach($questions as $index => $question)
        @php($number = $index + 1)
        <fieldset class="border-bottom py-3">
            <legend class="h6">{{ $number }}. {{ $question }}</legend>
            <div class="d-flex flex-wrap gap-3" role="radiogroup" aria-label="Response for statement {{ $number }}">
                @foreach([1 => 'Strongly disagree', 2 => 'Disagree', 3 => 'Neither', 4 => 'Agree', 5 => 'Strongly agree'] as $value => $label)
                    <label class="form-check"><input class="form-check-input" type="radio" name="q{{ $number }}" value="{{ $value }}" @checked(($answers[$index] ?? null) == $value) required> <span class="form-check-label">{{ $label }}</span></label>
                @endforeach
            </div>
        </fieldset>
    @endforeach
    <button class="btn btn-primary mt-3" type="submit">{{ $response ? 'Update my response' : 'Submit usability survey' }}</button>
</form>
@endsection
