<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FurnitureProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = FurnitureProduct::query()->withCount('reservations')->when($request->filled('q'), fn ($q) => $q->where(fn ($search) => $search->where('name', 'like', '%'.$request->q.'%')->orWhere('category', 'like', '%'.$request->q.'%')))->latest()->paginate(15)->withQueryString();
        return view('admin.products.index', compact('products'));
    }

    public function create()
    {
        return view('admin.products.create', $this->materialOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['created_by'] = $request->user()->id;
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }
        $data['tags'] = $this->tags($data['tags'] ?? '');
        unset($data['image']);
        FurnitureProduct::create($data);

        return redirect()->route('admin.products.index')->with('success', 'Furniture item added to the catalog.');
    }

    public function edit(FurnitureProduct $product)
    {
        return view('admin.products.edit', array_merge(['product' => $product], $this->materialOptions()));
    }

    public function update(Request $request, FurnitureProduct $product)
    {
        $data = $this->validated($request);
        if ($product->name !== $data['name']) {
            $data['slug'] = $this->uniqueSlug($data['name'], $product->id);
        }
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }
        $data['tags'] = $this->tags($data['tags'] ?? '');
        unset($data['image']);
        $product->update($data);

        return redirect()->route('admin.products.index')->with('success', 'Furniture item updated.');
    }

    public function updateStatus(Request $request, FurnitureProduct $product)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'inactive'])]]);
        $product->update(['status' => $data['status']]);

        return back()->with('success', 'Furniture status updated to '.$data['status'].'.');
    }

    public function destroy(FurnitureProduct $product)
    {
        $product->update(['status' => 'inactive']);

        return back()->with('success', 'Furniture deactivated. It is hidden from customer browsing and its history is preserved.');
    }

    public function destroyPermanently(FurnitureProduct $product)
    {
        if ($product->reservations()->exists()) {
            return back()->with('warning', 'This item has reservation history and cannot be permanently deleted. Deactivate it to keep those records.');
        }

        $imagePath = $product->image_path;
        $product->delete();

        if ($imagePath && ! str_starts_with($imagePath, 'catalog:') && ! str_starts_with($imagePath, 'demo:')) {
            Storage::disk('public')->delete($imagePath);
        }

        return back()->with('success', 'Furniture permanently deleted along with its catalog activity and preview records.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'], 'category' => ['required', 'string', 'max:80'], 'description' => ['required', 'string', 'max:3000'],
            'wood_type' => ['required', 'string', 'max:100'], 'varnish_finish' => ['required', 'string', 'max:100'],
            'style' => ['required', 'string', 'max:80'], 'price' => ['required', 'numeric', 'min:0'], 'width_cm' => ['nullable', 'numeric', 'min:1'], 'depth_cm' => ['nullable', 'numeric', 'min:1'], 'height_cm' => ['nullable', 'numeric', 'min:1'],
            'tags' => ['nullable', 'string', 'max:500'], 'status' => ['required', Rule::in(['active', 'inactive'])], 'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);
    }

    private function uniqueSlug(string $name, ?int $ignore = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;
        while (FurnitureProduct::where('slug', $slug)->when($ignore, fn ($q) => $q->whereKeyNot($ignore))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function materialOptions(): array
    {
        return [
            'woodTypes' => FurnitureProduct::query()->distinct()->orderBy('wood_type')->pluck('wood_type'),
            'varnishFinishes' => FurnitureProduct::query()->distinct()->orderBy('varnish_finish')->pluck('varnish_finish'),
        ];
    }
    private function tags(string $tags): array
    {
        return collect(explode(',', $tags))->map(fn ($tag) => trim($tag))->filter()->unique()->values()->all();
    }
}
