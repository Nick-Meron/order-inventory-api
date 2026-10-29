<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    // Customers see only their own orders. Admins see all orders.
    public function index(Request $request): JsonResponse
    {
        $query = Order::with('items.product')->latest('id');

        if ($request->user()->isAdmin()) {
            $query->with('user:id,name,email');
        } else {
            $query->where('user_id', $request->user()->id);
        }

        return response()->json(['orders' => $query->get()]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        // A customer asking for someone else's order gets 404, not 403,
        // so we don't reveal that the order exists.
        if (! $request->user()->isAdmin() && $order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        return response()->json(['order' => $order->load('items.product', 'user:id,name,email')]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        // Everything inside this function is ALL-OR-NOTHING.
        // If anything throws an error, the database undoes every change.
        $order = DB::transaction(function () use ($data, $request) {
            $ids = collect($data['items'])->pluck('product_id')->sort()->values();

            // lockForUpdate: no other order can change these products
            // until we finish. This stops two customers buying the last item.
            $products = Product::whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $total = 0;
            $lines = [];

            foreach ($data['items'] as $item) {
                $product = $products[$item['product_id']];
                $quantity = (int) $item['quantity'];

                if (! $product->active) {
                    throw ValidationException::withMessages([
                        'items' => "{$product->name} is not available.",
                    ]);
                }

                if ($product->stock < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Not enough stock for {$product->name}. Available: {$product->stock}.",
                    ]);
                }

                $total += (float) $product->price * $quantity;
                $lines[] = ['product' => $product, 'quantity' => $quantity];
            }

            // user_id comes from the logged-in user, never from the request.
            $order = Order::create([
                'user_id' => $request->user()->id,
                'status' => Order::PENDING,
                'total' => round($total, 2),
            ]);

            foreach ($lines as $line) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $line['product']->id,
                    'quantity' => $line['quantity'],
                    // Price saved at this moment. Later price changes don't touch it.
                    'unit_price' => $line['product']->price,
                ]);

                $line['product']->decrement('stock', $line['quantity']);
            }

            return $order;
        });

        return response()->json(['order' => $order->load('items.product')], 201);
    }
}