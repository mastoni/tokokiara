<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Contact;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class CartService
{
    private $cartId;
    private $cart;

    public function __construct($cartId = null)
    {
        $this->cartId = $cartId ?: $this->generateCartId();
        $this->cart = $this->loadCart();
    }

    /**
     * Add item to cart
     */
    public function addItem($productId, $quantity = 1, $price = null, $discount = 0)
    {
        $product = Product::find($productId);

        if (!$product) {
            throw new \Exception('Product not found');
        }

        if (!$product->is_active) {
            throw new \Exception('Product is not active');
        }

        if ($product->is_stock_managed && $product->quantity < $quantity) {
            throw new \Exception("Insufficient stock. Available: {$product->quantity}");
        }

        $cartItem = [
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'quantity' => $quantity,
            'unit_price' => $price ?: $product->price,
            'unit_cost' => $product->cost,
            'discount' => $discount,
            'tax_rate' => $product->tax_rate,
            'is_stock_managed' => $product->is_stock_managed,
            'product_type' => $product->product_type,
            'image_url' => $product->image_url,
        ];

        // Check if item already exists in cart
        $existingItemIndex = $this->findItemIndex($productId);

        if ($existingItemIndex !== false) {
            // Update existing item
            $existingItem = $this->cart['items'][$existingItemIndex];
            $newQuantity = $existingItem['quantity'] + $quantity;

            if ($product->is_stock_managed && $product->quantity < $newQuantity) {
                throw new \Exception("Insufficient stock. Available: {$product->quantity}");
            }

            $this->cart['items'][$existingItemIndex]['quantity'] = $newQuantity;
            $this->cart['items'][$existingItemIndex]['updated_at'] = now();
        } else {
            // Add new item
            $cartItem['created_at'] = now();
            $cartItem['updated_at'] = now();
            $cartItem['line_id'] = uniqid();
            $this->cart['items'][] = $cartItem;
        }

        $this->recalculateTotals();
        $this->saveCart();

        return $this->getCart();
    }

    /**
     * Update item quantity
     */
    public function updateItem($lineId, $quantity)
    {
        $itemIndex = $this->findItemIndexByLineId($lineId);

        if ($itemIndex === false) {
            throw new \Exception('Item not found in cart');
        }

        $item = $this->cart['items'][$itemIndex];
        $product = Product::find($item['product_id']);

        if ($product->is_stock_managed && $product->quantity < $quantity) {
            throw new \Exception("Insufficient stock. Available: {$product->quantity}");
        }

        if ($quantity <= 0) {
            return $this->removeItem($lineId);
        }

        $this->cart['items'][$itemIndex]['quantity'] = $quantity;
        $this->cart['items'][$itemIndex]['updated_at'] = now();

        $this->recalculateTotals();
        $this->saveCart();

        return $this->getCart();
    }

    /**
     * Remove item from cart
     */
    public function removeItem($lineId)
    {
        $itemIndex = $this->findItemIndexByLineId($lineId);

        if ($itemIndex !== false) {
            unset($this->cart['items'][$itemIndex]);
            $this->cart['items'] = array_values($this->cart['items']);
            $this->recalculateTotals();
            $this->saveCart();
        }

        return $this->getCart();
    }

    /**
     * Clear cart
     */
    public function clear()
    {
        $this->cart['items'] = [];
        $this->cart['customer_id'] = null;
        $this->cart['discount'] = 0;
        $this->cart['shipping_amount'] = 0;
        $this->cart['notes'] = '';
        $this->cart['recalculate'] = true;

        $this->recalculateTotals();
        $this->saveCart();

        return $this->getCart();
    }

    /**
     * Set customer
     */
    public function setCustomer($customerId)
    {
        $customer = Contact::find($customerId);

        if (!$customer || !$customer->isCustomer()) {
            throw new \Exception('Invalid customer');
        }

        $this->cart['customer_id'] = $customerId;
        $this->cart['customer'] = [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'balance' => $customer->balance,
            'loyalty_points' => $customer->loyalty_points,
        ];

        $this->saveCart();

        return $this->getCart();
    }

    /**
     * Apply discount
     */
    public function applyDiscount($amount, $type = 'fixed')
    {
        if ($type === 'percentage') {
            if ($amount < 0 || $amount > 100) {
                throw new \Exception('Percentage discount must be between 0 and 100');
            }
            $this->cart['discount_percentage'] = $amount;
            $this->cart['discount'] = 0;
        } else {
            if ($amount < 0 || $amount > $this->cart['subtotal']) {
                throw new \Exception('Invalid discount amount');
            }
            $this->cart['discount'] = $amount;
            $this->cart['discount_percentage'] = 0;
        }

        $this->recalculateTotals();
        $this->saveCart();

        return $this->getCart();
    }

    /**
     * Set shipping amount
     */
    public function setShipping($amount)
    {
        if ($amount < 0) {
            throw new \Exception('Shipping amount cannot be negative');
        }

        $this->cart['shipping_amount'] = $amount;
        $this->recalculateTotals();
        $this->saveCart();

        return $this->getCart();
    }

    /**
     * Set notes
     */
    public function setNotes($notes)
    {
        $this->cart['notes'] = $notes;
        $this->saveCart();

        return $this->getCart();
    }

    /**
     * Get cart data
     */
    public function getCart()
    {
        $this->recalculateTotals();
        return $this->cart;
    }

    /**
     * Get cart items
     */
    public function getItems()
    {
        return $this->cart['items'];
    }

    /**
     * Get cart totals
     */
    public function getTotals()
    {
        $this->recalculateTotals();
        return [
            'subtotal' => $this->cart['subtotal'],
            'discount' => $this->cart['total_discount'],
            'tax' => $this->cart['tax'],
            'shipping' => $this->cart['shipping_amount'],
            'total' => $this->cart['total'],
            'items_count' => $this->cart['items_count'],
            'total_quantity' => $this->cart['total_quantity'],
        ];
    }

    /**
     * Validate cart
     */
    public function validate()
    {
        $errors = [];

        if (empty($this->cart['items'])) {
            $errors[] = 'Cart is empty';
        }

        foreach ($this->cart['items'] as $item) {
            $product = Product::find($item['product_id']);

            if (!$product || !$product->is_active) {
                $errors[] = "Product {$item['name']} is no longer available";
                continue;
            }

            if ($product->is_stock_managed && $product->quantity < $item['quantity']) {
                $errors[] = "Insufficient stock for {$item['name']}. Available: {$product->quantity}";
            }
        }

        if ($this->cart['customer_id']) {
            $customer = Contact::find($this->cart['customer_id']);
            if (!$customer || !$customer->isCustomer()) {
                $errors[] = 'Invalid customer selected';
            }
        }

        return $errors;
    }

    /**
     * Check if cart is valid
     */
    public function isValid()
    {
        return empty($this->validate());
    }

    /**
     * Get cart summary for quick display
     */
    public function getSummary()
    {
        $this->recalculateTotals();

        return [
            'items_count' => count($this->cart['items']),
            'total_quantity' => $this->cart['total_quantity'],
            'subtotal' => $this->cart['subtotal'],
            'total' => $this->cart['total'],
            'customer_name' => $this->cart['customer']['name'] ?? null,
            'has_items' => !empty($this->cart['items']),
        ];
    }

    /**
     * Load cart from cache or database
     */
    private function loadCart()
    {
        if (Auth::check()) {
            // Load from cache for authenticated users
            $cart = Cache::get('cart_' . Auth::id() . '_' . $this->cartId);
        } else {
            // Load from session for guests
            $cart = session('cart_' . $this->cartId);
        }

        if (!$cart) {
            $cart = $this->createNewCart();
        }

        return $cart;
    }

    /**
     * Save cart to cache or database
     */
    private function saveCart()
    {
        $this->cart['updated_at'] = now();

        if (Auth::check()) {
            Cache::put('cart_' . Auth::id() . '_' . $this->cartId, $this->cart, now()->addDays(7));
        } else {
            session(['cart_' . $this->cartId => $this->cart]);
        }
    }

    /**
     * Create new cart structure
     */
    private function createNewCart()
    {
        return [
            'id' => $this->cartId,
            'items' => [],
            'customer_id' => null,
            'customer' => null,
            'subtotal' => 0,
            'discount' => 0,
            'discount_percentage' => 0,
            'total_discount' => 0,
            'tax' => 0,
            'shipping_amount' => 0,
            'total' => 0,
            'items_count' => 0,
            'total_quantity' => 0,
            'notes' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Recalculate cart totals
     */
    private function recalculateTotals()
    {
        $subtotal = 0;
        $tax = 0;
        $itemsCount = 0;
        $totalQuantity = 0;

        foreach ($this->cart['items'] as $item) {
            $itemTotal = $item['quantity'] * $item['unit_price'];
            $itemDiscount = min($item['discount'], $itemTotal);
            $itemSubtotal = $itemTotal - $itemDiscount;
            $itemTax = $itemSubtotal * $item['tax_rate'];

            $subtotal += $itemSubtotal;
            $tax += $itemTax;
            $itemsCount++;
            $totalQuantity += $item['quantity'];
        }

        $this->cart['subtotal'] = $subtotal;
        $this->cart['tax'] = $tax;

        // Apply discounts
        if ($this->cart['discount_percentage'] > 0) {
            $this->cart['total_discount'] = $subtotal * ($this->cart['discount_percentage'] / 100);
        } else {
            $this->cart['total_discount'] = $this->cart['discount'];
        }

        $this->cart['total'] = max(0, $subtotal - $this->cart['total_discount'] + $tax + $this->cart['shipping_amount']);
        $this->cart['items_count'] = $itemsCount;
        $this->cart['total_quantity'] = $totalQuantity;
    }

    /**
     * Find item index by product ID
     */
    private function findItemIndex($productId)
    {
        foreach ($this->cart['items'] as $index => $item) {
            if ($item['product_id'] == $productId) {
                return $index;
            }
        }
        return false;
    }

    /**
     * Find item index by line ID
     */
    private function findItemIndexByLineId($lineId)
    {
        foreach ($this->cart['items'] as $index => $item) {
            if ($item['line_id'] === $lineId) {
                return $index;
            }
        }
        return false;
    }

    /**
     * Generate unique cart ID
     */
    private function generateCartId()
    {
        return uniqid('cart_');
    }

    /**
     * Apply coupon code
     */
    public function applyCoupon($code)
    {
        // This would integrate with a coupon/discount system
        // For now, just a placeholder
        throw new \Exception('Coupon system not implemented');
    }

    /**
     * Remove coupon
     */
    public function removeCoupon()
    {
        $this->cart['discount'] = 0;
        $this->cart['discount_percentage'] = 0;
        $this->recalculateTotals();
        $this->saveCart();

        return $this->getCart();
    }

    /**
     * Merge with another cart (useful for cart recovery)
     */
    public function mergeWith($otherCartId)
    {
        $otherCartService = new self($otherCartId);
        $otherCart = $otherCartService->getCart();

        foreach ($otherCart['items'] as $item) {
            try {
                $this->addItem($item['product_id'], $item['quantity'], $item['unit_price'], $item['discount']);
            } catch (\Exception $e) {
                // Skip items that can't be added (out of stock, etc.)
                continue;
            }
        }

        // Clear the other cart
        $otherCartService->clear();

        return $this->getCart();
    }

    /**
     * Get cart as array for API response
     */
    public function toArray()
    {
        $this->recalculateTotals();
        return $this->cart;
    }
}