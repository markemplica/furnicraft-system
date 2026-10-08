@extends('layouts.app')
@section('title','How it works')
@section('content')
<header class="page-heading">
    <div>
        <p class="section-kicker">How FurniCraft works</p>
        <h1>A clearer path to the right furniture.</h1>
        <p>Explore the tools, understand the planning process, and move from room details to a confident reservation.</p>
    </div>
    <a class="btn btn-primary" href="{{ route('preferences.edit') }}">Start planning</a>
</header>

<section class="home-services" aria-labelledby="explore-heading">
    <div class="home-services-heading">
        <p class="section-kicker">Explore FurniCraft</p>
        <h2 id="explore-heading">Everything you need to choose with confidence.</h2>
    </div>
    <div class="home-service-grid">
        <a class="home-service-card" href="{{ route('catalog.index') }}"><span>01</span><div><h3>Catalog</h3><p>Browse the complete Lance Furniture Shop collection by category, material, style, and budget.</p></div><b aria-hidden="true">&rarr;</b></a>
        <a class="home-service-card" href="{{ route('preferences.edit') }}"><span>02</span><div><h3>AI recommendations</h3><p>Share your room details and preferences to receive clear, ranked furniture matches.</p></div><b aria-hidden="true">&rarr;</b></a>
        <a class="home-service-card" href="{{ route('room.index') }}"><span>03</span><div><h3>Room preview</h3><p>Upload your space and compare recommended pieces in one connected planning workflow.</p></div><b aria-hidden="true">&rarr;</b></a>
    </div>
    @guest<p class="access-note">You can learn how FurniCraft works freely. <strong>Log in to access</strong> the catalog and planning tools.</p>@endguest
</section>

<section class="process-grid" aria-labelledby="how-heading">
    <div>
        <p class="section-kicker">A considered way to choose</p>
        <h2 id="how-heading">From your room to the right piece.</h2>
        <p>Every step keeps the decision practical, transparent, and grounded in the Lance Furniture Shop catalog.</p>
    </div>
    <ol>
        <li><strong>Define your space</strong><span>Add your budget, style, materials, measurements, and an optional room photo.</span></li>
        <li><strong>Review intelligent matches</strong><span>Compare ranked furniture with concise explanations and room-fit context.</span></li>
        <li><strong>Preview and reserve</strong><span>Use the 2D room comparison, ask questions, then request a reservation from Lance Furniture Shop.</span></li>
    </ol>
</section>
@endsection
