@props(['product','saved' => false])
<article class="product-card">
    <a href="{{ route('catalog.show', $product) }}"><img src="{{ $product->image_url }}" alt="{{ $product->name }}"></a>
    <div class="product-card-body">
        <div class="d-flex justify-content-between gap-2"><span class="product-meta">{{ $product->category }}</span>@if($saved)<span class="status status-confirmed">Saved</span>@endif</div>
        <h3 class="mt-2"><a class="text-reset text-decoration-none" href="{{ route('catalog.show', $product) }}">{{ $product->name }}</a></h3>
        <p class="product-meta"><span class="swatch {{ strtolower($product->wood_type) }}"></span>{{ $product->wood_type }} · {{ $product->varnish_finish }} · {{ $product->style }}</p>
        <p class="product-price">PHP {{ number_format($product->price, 2) }}</p>
    </div>
</article>
