<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FurnitureProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = FurnitureProduct::query()->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->q.'%')->orWhere('category', 'like', '%'.$request->q.'%'))->latest()->paginate(15)->withQueryString();

        return view('admin.products.index', compact('products'));
    }

    public function create()
    {
        return view('admin.products.create');
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
        return view('admin.products.edit', compact('product'));
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

    public function destroy(FurnitureProduct $product)
    {
        $product->update(['status' => 'inactive']);

        return back()->with('success', 'The item was removed from the customer catalog and its records were preserved.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'], 'category' => ['required', 'string', 'max:80'], 'description' => ['required', 'string', 'max:3000'],
            'wood_type' => ['required', Rule::in(['Gmelina', 'Mahogany'])], 'varnish_finish' => ['required', Rule::in(['Natural', 'Narra Look', 'Mahogany', 'Chocolate Brown'])],
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

    private function tags(string $tags): array
    {
        return collect(explode(',', $tags))->map(fn ($tag) => trim($tag))->filter()->unique()->values()->all();
    }
}
