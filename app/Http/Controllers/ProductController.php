<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    // Everyone logged in can view. Customers only see active products.
    public function index(Request $request): JsonResponse
    {
        $query = Product::orderBy('name');

        if (! $request->user()->isAdmin()) {
            $query->where('active', true);
        }

        return response()->json(['products' => $query->get()]);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        if (! $product->active && ! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        return response()->json(['product' => $product]);
    }

    // Admin only (enforced in routes/api.php)
    public function lowStock(Request $request): JsonResponse
    {
        $data = $request->validate([
            'threshold' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ]);

        $threshold = $data['threshold'] ?? 5;

        $products = Product::where('active', true)
            ->where('stock', '<=', $threshold)
            ->orderBy('stock')
            ->orderBy('name')
            ->get();

        return response()->json([
            'threshold' => (int) $threshold,
            'products' => $products,
        ]);
    }

    // Admin only
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $product = Product::create($data);
        $product->refresh();

        return response()->json(['product' => $product], 201);
    }

    // Admin only
    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999.99'],
            'stock' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000000'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $product->update($data);

        return response()->json(['product' => $product->fresh()]);
    }

    // Admin only. Only real image files are accepted, 4 MB at most.
    public function uploadImage(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $file = $request->file('image');
        // A random file name, so nobody can overwrite or guess another file.
        $name = Str::uuid()->toString().'.'.$file->extension();
        $file->move(public_path('product-images'), $name);

        // Remove the old photo so unused files don't pile up.
        if ($product->image_path) {
            File::delete(public_path('product-images/'.$product->image_path));
        }

        $product->image_path = $name;
        $product->save();

        return response()->json(['product' => $product->fresh()]);
    }

    // Admin only
    public function destroy(Product $product): JsonResponse
    {
        // A product that was ordered must stay, or old orders would break.
        if (OrderItem::where('product_id', $product->id)->exists()) {
            return response()->json([
                'message' => 'This product is in existing orders. Set it to inactive instead of deleting it.',
            ], 409);
        }

        if ($product->image_path) {
            File::delete(public_path('product-images/'.$product->image_path));
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted.']);
    }
}