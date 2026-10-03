@extends('layouts.app')
@section('title',$product->name)
@section('content')
<nav aria-label="Breadcrumb" class="mb-3"><a href="{{ route('catalog.index') }}">Catalog</a> <span aria-hidden="true">/</span> {{ $product->name }}</nav>
<div class="detail-grid">
    <div><img class="detail-image" src="{{ $product->image_url }}" alt="{{ $product->name }}"><p class="small text-muted mt-2">Product preview is a 2D representation. Final wood grain and finish can vary.</p></div>
    <div><p class="section-kicker">{{ $product->category }}</p><h1>{{ $product->name }}</h1><p class="lead">{{ $product->description }}</p><p class="product-price fs-3">PHP {{ number_format($product->price, 2) }}</p>
        <dl class="spec-list"><div><dt>Wood</dt><dd><span class="swatch {{ strtolower($product->wood_type) }}"></span>{{ $product->wood_type }}</dd></div><div><dt>Finish</dt><dd>{{ $product->varnish_finish }}</dd></div><div><dt>Style</dt><dd>{{ $product->style }}</dd></div><div><dt>Dimensions</dt><dd>{{ $product->width_cm }} × {{ $product->depth_cm }} × {{ $product->height_cm }} cm</dd></div></dl>
        <form method="POST" action="{{ route('catalog.favorite',$product) }}" class="my-3">@csrf<button class="btn {{ $isFavorite ? 'btn-dark' : 'btn-outline-dark' }}" type="submit">{{ $isFavorite ? 'Remove from favorites' : 'Save to favorites' }}</button></form>
        <div class="panel mt-4"><h2 class="h4">Request a reservation</h2><p class="small text-muted">This submits a request for branch review; it does not confirm physical stock or hold the item. Shop staff must confirm item readiness with you. No purchase or payment is made here.</p><form method="POST" action="{{ route('reservations.store') }}" class="vstack gap-3">@csrf<input type="hidden" name="furniture_product_id" value="{{ $product->id }}"><div><label class="form-label" for="branch">Preferred branch</label><select class="form-select" id="branch" name="branch" required><option value="">Choose a branch</option>@foreach(['Lingayen','Calasiao','San Jacinto','Sta. Barbara','Pozorrubio','Tarlac'] as $branch)<option @selected(old('branch')===$branch)>{{ $branch }}</option>@endforeach</select></div><div><label class="form-label" for="notes">Notes <span class="text-muted">optional</span></label><textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Questions or preferred viewing schedule">{{ old('notes') }}</textarea></div><button class="btn btn-primary" type="submit">Send reservation request</button></form></div>
    </div>
</div>
@endsection
