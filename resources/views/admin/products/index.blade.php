@extends('layouts.app')
@section('title', 'Manage catalog')
@section('content')
<header class="page-heading">
    <div>
        <p class="section-kicker">Catalog records</p>
        <h1>Furniture catalog</h1>
        <p>Deactivate items to hide them from customers while keeping history. Deleting removes catalog activity and previews; items with reservations must be deactivated.</p>
    </div>
    <a class="btn btn-primary" href="{{ route('admin.products.create') }}">Add furniture</a>
</header>

<form class="d-flex gap-2 mb-3" method="GET">
    <input class="form-control" name="q" value="{{ request('q') }}" placeholder="Search name or category">
    <button class="btn btn-dark">Search</button>
</form>

<div class="table-responsive">
    <table class="table align-middle">
        <thead><tr><th>Product</th><th>Material</th><th>Price</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse($products as $product)
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <img src="{{ $product->image_url }}" alt="" width="64" height="48" style="object-fit:cover">
                        <div><strong>{{ $product->name }}</strong><div class="small text-muted">{{ $product->category }}</div></div>
                    </div>
                </td>
                <td>{{ $product->wood_type }}<br><span class="small text-muted">{{ $product->varnish_finish }}</span></td>
                <td>PHP {{ number_format($product->price, 2) }}</td>
                <td><span class="status status-{{ $product->status }}">{{ $product->status }}</span></td>
                <td class="text-end">
                    <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                        <a class="btn btn-sm btn-outline-dark" href="{{ route('admin.products.edit', $product) }}">Edit</a>
                        @if($product->status === 'active')
                            <form method="POST" action="{{ route('admin.products.destroy', $product) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-warning" onclick="return confirm('Deactivate this furniture? Customers will no longer see it, and its history will be kept.')">Deactivate</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.products.status', $product) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="active">
                                <button class="btn btn-sm btn-outline-success">Activate</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.products.destroy-permanently', $product) }}">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"
                                @disabled($product->reservations_count > 0)
                                title="{{ $product->reservations_count > 0 ? 'Reservation history exists; deactivate this item instead.' : 'Delete this item and its catalog activity.' }}"
                                onclick="return confirm('Delete this furniture and its catalog activity, favorites, recommendation history, views, and previews? This cannot be undone.')">
                                Delete
                            </button>
                        </form>
                    </div>
                    @if($product->reservations_count > 0)
                        <div class="small text-muted mt-1">Has reservation history; deactivate instead of deleting.</div>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5">No catalog items found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

{{ $products->links() }}
@endsection
