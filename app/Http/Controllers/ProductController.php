<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    // Admin only
    public function destroy(Product $product): JsonResponse
    {
        // A product that was ordered must stay, or old orders would break.
        if (OrderItem::where('product_id', $product->id)->exists()) {
            return response()->json([
                'message' => 'This product is in existing orders. Set it to inactive instead of deleting it.',
            ], 409);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted.']);
    }
}