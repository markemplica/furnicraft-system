@props(['step' => 1])
@php
    $steps = [
        1 => ['Account', 'Signed in'],
        2 => ['Preferences', 'Budget, style, materials'],
        3 => ['Room analysis', 'Image and dimensions'],
        4 => ['AI matches', 'Results and chatbot'],
        5 => ['Reservation', 'Administrator review'],
    ];
@endphp
<nav class="workflow-progress" aria-label="Furniture planning progress">
    <ol>
        @foreach($steps as $number => [$label, $description])
            <li class="{{ $step > $number ? 'is-complete' : ($step === $number ? 'is-current' : '') }}" @if($step === $number) aria-current="step" @endif>
                <span>{{ $number }}</span>
                <div><strong>{{ $label }}</strong><small>{{ $description }}</small></div>
            </li>
        @endforeach
    </ol>
</nav>
