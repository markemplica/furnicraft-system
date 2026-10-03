@props(['step' => 1])
@php
    $steps = [
        1 => ['Preferences', 'Furniture type and details'],
        2 => ['Room & generate', 'Image analysis and matching'],
        3 => ['AI matches', 'Ranked results and chatbot'],
        4 => ['Reservation', 'Administrator review'],
    ];
@endphp
<nav class="workflow-progress" aria-label="Furniture planning progress">
    <ol>
        @foreach($steps as $number => [$label, $description])
            <li class="{{ $step > $number ? 'is-complete' : ($step === $number ? 'is-current' : '') }}" data-workflow-step="{{ $number }}" @if($step === $number) aria-current="step" @endif>
                <span>{{ $number }}</span>
                <div><strong>{{ $label }}</strong><small>{{ $description }}</small></div>
            </li>
        @endforeach
    </ol>
</nav>
