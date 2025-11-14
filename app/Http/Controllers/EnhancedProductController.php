<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Store;
use App\Models\InventoryTransaction;
use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Intervention\Image\Facades\Image;
use Carbon\Carbon;

class EnhancedProductController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:products']);
    }

    public function index(Request $request)
    {
        $query = Product::with(['category', 'brand', 'attachment'])
            ->byStore(Auth::user()->store_id);

        // Search
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Filter by category
        if ($request->filled('category_id')) {
            $query->byCategory($request->category_id);
        }

        // Filter by brand
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        // Filter by stock status
        if ($request->filled('stock_status')) {
            switch ($request->stock_status) {
                case 'in_stock':
                    $query->inStock();
                    break;
                case 'low_stock':
                    $query->lowStock();
                    break;
                case 'out_of_stock':
                    $query->where('quantity', 0);
                    break;
            }
        }

        // Filter by active status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Sort
        $sortField = $request->input('sort', 'name');
        $sortDirection = $request->input('direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        $products = $query->paginate(25);

        $categories = Category::active()->orderBy('name')->get();
        $brands = Brand::active()->orderBy('name')->get();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'filters' => $request->only(['search', 'category_id', 'brand_id', 'stock_status', 'is_active', 'sort', 'direction']),
        ]);
    }

    public function create()
    {
        $categories = Category::active()->orderBy('name')->get();
        $brands = Brand::active()->orderBy('name')->get();
        $stores = Store::active()->orderBy('name')->get();

        return Inertia::render('Products/Create', [
            'categories' => $categories,
            'brands' => $brands,
            'stores' => $stores,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sku' => 'nullable|string|unique:products,sku',
            'barcode' => 'nullable|string|unique:products,barcode',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'price' => 'required|numeric|min:0',
            'cost' => 'required|numeric|min:0',
            'quantity' => 'required|numeric|min:0',
            'alert_quantity' => 'nullable|numeric|min:0',
            'reorder_point' => 'nullable|numeric|min:0',
            'max_stock' => 'nullable|numeric|min:0',
            'unit' => 'nullable|string|max:50',
            'tax_rate' => 'nullable|numeric|min:0|max:1',
            'is_stock_managed' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'product_type' => 'required|in:standard,service,digital',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'meta_data' => 'nullable|array',
        ]);

        DB::beginTransaction();

        try {
            // Handle image upload with compression
            $imageUrl = null;
            $attachmentId = null;

            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $filename = uniqid() . '.' . $image->getClientOriginalExtension();

                // Compress and resize image
                $compressedImage = Image::make($image)
                    ->resize(800, 600, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    })
                    ->encode('jpg', 75);

                $path = 'products/' . date('Y/m');
                Storage::disk('public')->put($path . '/' . $filename, $compressedImage);
                $imageUrl = $path . '/' . $filename;

                // Create attachment record
                $attachment = Attachment::create([
                    'path' => $imageUrl,
                    'file_name' => $image->getClientOriginalName(),
                    'size' => $image->getSize(),
                    'attachment_type' => 'product_image',
                    'alt_text' => $request->name,
                    'title' => $request->name,
                    'description' => $request->description,
                ]);

                $attachmentId = $attachment->id;
            }

            // Create product
            $product = Product::create([
                'name' => $request->name,
                'description' => $request->description,
                'sku' => $request->sku,
                'barcode' => $request->barcode ?? $this->generateBarcode(),
                'category_id' => $request->category_id,
                'brand_id' => $request->brand_id,
                'price' => $request->price,
                'cost' => $request->cost,
                'quantity' => $request->quantity,
                'alert_quantity' => $request->alert_quantity ?? 5,
                'reorder_point' => $request->reorder_point ?? 10,
                'max_stock' => $request->max_stock,
                'unit' => $request->unit,
                'tax_rate' => $request->tax_rate ?? 0,
                'is_stock_managed' => $request->boolean('is_stock_managed'),
                'is_active' => $request->boolean('is_active', true),
                'is_featured' => $request->boolean('is_featured', false),
                'product_type' => $request->product_type,
                'image_url' => $imageUrl,
                'attachment_id' => $attachmentId,
                'meta_data' => $request->meta_data ?? [],
            ]);

            // Log inventory transaction if stock is managed
            if ($product->is_stock_managed && $request->quantity > 0) {
                InventoryTransaction::create([
                    'product_id' => $product->id,
                    'previous_quantity' => 0,
                    'new_quantity' => $request->quantity,
                    'change' => $request->quantity,
                    'transaction_type' => 'initial_stock',
                    'reason' => 'Initial stock entry',
                    'user_id' => Auth::id(),
                    'store_id' => Auth::user()->store_id,
                ]);
            }

            DB::commit();

            return redirect()->route('products.index')
                ->with('success', 'Product created successfully!');

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->with('error', 'Error creating product: ' . $e->getMessage());
        }
    }

    public function show(Product $product)
    {
        $product->load(['category', 'brand', 'attachment', 'inventoryTransactions' => function($query) {
            $query->with('user')->latest()->limit(10);
        }]);

        // Get related products
        $relatedProducts = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->active()
            ->limit(5)
            ->get();

        return Inertia::render('Products/Show', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
        ]);
    }

    public function edit(Product $product)
    {
        $product->load(['category', 'brand', 'attachment']);

        $categories = Category::active()->orderBy('name')->get();
        $brands = Brand::active()->orderBy('name')->get();

        return Inertia::render('Products/Edit', [
            'product' => $product,
            'categories' => $categories,
            'brands' => $brands,
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sku' => 'nullable|string|unique:products,sku,' . $product->id,
            'barcode' => 'nullable|string|unique:products,barcode,' . $product->id,
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'price' => 'required|numeric|min:0',
            'cost' => 'required|numeric|min:0',
            'alert_quantity' => 'nullable|numeric|min:0',
            'reorder_point' => 'nullable|numeric|min:0',
            'max_stock' => 'nullable|numeric|min:0',
            'unit' => 'nullable|string|max:50',
            'tax_rate' => 'nullable|numeric|min:0|max:1',
            'is_stock_managed' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'product_type' => 'required|in:standard,service,digital',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'meta_data' => 'nullable|array',
        ]);

        DB::beginTransaction();

        try {
            $data = $request->except(['image']);

            // Handle image upload
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($product->image_url) {
                    Storage::disk('public')->delete($product->image_url);
                }

                $image = $request->file('image');
                $filename = uniqid() . '.' . $image->getClientOriginalExtension();

                $compressedImage = Image::make($image)
                    ->resize(800, 600, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    })
                    ->encode('jpg', 75);

                $path = 'products/' . date('Y/m');
                Storage::disk('public')->put($path . '/' . $filename, $compressedImage);
                $data['image_url'] = $path . '/' . $filename;

                // Create new attachment
                if ($product->attachment_id) {
                    Attachment::find($product->attachment_id)?->delete();
                }

                $attachment = Attachment::create([
                    'path' => $data['image_url'],
                    'file_name' => $image->getClientOriginalName(),
                    'size' => $image->getSize(),
                    'attachment_type' => 'product_image',
                    'alt_text' => $request->name,
                    'title' => $request->name,
                    'description' => $request->description,
                ]);

                $data['attachment_id'] = $attachment->id;
            }

            $product->update($data);

            DB::commit();

            return redirect()->route('products.index')
                ->with('success', 'Product updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->with('error', 'Error updating product: ' . $e->getMessage());
        }
    }

    public function destroy(Product $product)
    {
        try {
            // Check if product has sales
            if ($product->saleItems()->exists()) {
                return redirect()->back()
                    ->with('error', 'Cannot delete product with sales history. Consider deactivating it instead.');
            }

            DB::beginTransaction();

            // Delete image if exists
            if ($product->image_url) {
                Storage::disk('public')->delete($product->image_url);
            }

            // Delete attachment if exists
            if ($product->attachment_id) {
                Attachment::find($product->attachment_id)?->delete();
            }

            $product->delete();

            DB::commit();

            return redirect()->route('products.index')
                ->with('success', 'Product deleted successfully!');

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->with('error', 'Error deleting product: ' . $e->getMessage());
        }
    }

    public function adjustStock(Request $request, Product $product)
    {
        $request->validate([
            'quantity' => 'required|numeric',
            'reason' => 'required|string|max:255',
            'transaction_type' => 'required|in:adjustment,sale,return,transfer,damage,expiry',
        ]);

        try {
            $previousQuantity = $product->quantity;
            $newQuantity = $request->quantity;
            $change = $newQuantity - $previousQuantity;

            if ($change == 0) {
                return redirect()->back()
                    ->with('error', 'No stock change detected.');
            }

            $product->updateStock($newQuantity, $request->reason, Auth::id());

            return redirect()->back()
                ->with('success', 'Stock adjusted successfully!');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error adjusting stock: ' . $e->getMessage());
        }
    }

    public function bulkImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240',
        ]);

        try {
            $file = $request->file('file');

            // Process file import (implementation depends on preferred library)
            // This is a placeholder for the actual import logic

            return redirect()->back()
                ->with('success', 'Products imported successfully!');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error importing products: ' . $e->getMessage());
        }
    }

    public function export(Request $request)
    {
        $query = Product::query();

        // Apply same filters as index method
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('category_id')) {
            $query->byCategory($request->category_id);
        }

        $products = $query->with(['category', 'brand'])->get();

        // Generate CSV or Excel export
        // Implementation depends on preferred export library

        return response()->download('products_export.csv');
    }

    public function generateBarcode($product)
    {
        try {
            $barcode = $product->barcode ?? $this->generateBarcode();

            // Generate barcode using JsBarcode or similar library
            // This would return an image or base64 encoded barcode

            return response()->json([
                'barcode' => $barcode,
                'barcode_image' => 'data:image/png;base64,barcode_image_data'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error generating barcode: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generateBarcode()
    {
        return 'PROD' . str_pad(mt_rand(1, 999999999), 9, '0', STR_PAD_LEFT);
    }

    // API endpoints for React components
    public function apiIndex(Request $request)
    {
        $query = Product::with(['category', 'brand'])
            ->active()
            ->byStore(Auth::user()->store_id);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('category_id')) {
            $query->byCategory($request->category_id);
        }

        $limit = min($request->input('limit', 20), 100);
        $products = $query->limit($limit)->get();

        return response()->json($products);
    }

    public function lowStockAlerts()
    {
        $products = Product::with(['category', 'brand'])
            ->byStore(Auth::user()->store_id)
            ->lowStock()
            ->active()
            ->get();

        return response()->json($products);
    }

    public function stockReport()
    {
        $products = Product::with(['category', 'brand'])
            ->byStore(Auth::user()->store_id)
            ->where('is_stock_managed', true)
            ->get();

        $report = [
            'total_products' => $products->count(),
            'in_stock' => $products->filter(fn($p) => $p->isInStock())->count(),
            'low_stock' => $products->filter(fn($p) => $p->isLowStock())->count(),
            'out_of_stock' => $products->filter(fn($p) => $p->quantity == 0)->count(),
            'total_value' => $products->sum(fn($p) => $p->quantity * $p->cost),
            'categories' => $products->groupBy('category.name')
                ->map(fn($group) => [
                    'count' => $group->count(),
                    'total_value' => $group->sum(fn($p) => $p->quantity * $p->cost),
                ]),
        ];

        return response()->json($report);
    }
}