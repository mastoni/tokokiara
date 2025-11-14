<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Intervention\Image\Facades\Image;

class BrandController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:products']);
    }

    public function index(Request $request)
    {
        $query = Brand::with(['products' => function($query) {
            $query->select('id', 'brand_id', 'name');
        }]);

        // Search
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%")
                  ->orWhere('website', 'like', "%{$request->search}%");
            });
        }

        // Filter by status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $brands = $query->orderBy('name')->paginate(25);

        return Inertia::render('Brands/Index', [
            'brands' => $brands,
            'filters' => $request->only(['search', 'is_active']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Brands/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'website' => 'nullable|url',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:1024',
            'is_active' => 'boolean',
        ]);

        $logoUrl = null;

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $logo = $request->file('logo');
            $filename = uniqid() . '.' . $logo->getClientOriginalExtension();

            // Compress and resize logo
            $compressedLogo = Image::make($logo)
                ->resize(300, 200, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })
                ->encode('jpg', 75);

            $path = 'brands/logos/' . date('Y/m');
            Storage::disk('public')->put($path . '/' . $filename, $compressedLogo);
            $logoUrl = $path . '/' . $filename;
        }

        Brand::create([
            'name' => $request->name,
            'description' => $request->description,
            'website' => $request->website,
            'contact_email' => $request->contact_email,
            'contact_phone' => $request->contact_phone,
            'logo_url' => $logoUrl,
            'is_active' => $request->boolean('is_active', true),
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('brands.index')
            ->with('success', 'Brand created successfully!');
    }

    public function show(Brand $brand)
    {
        $brand->load(['products' => function($query) {
            $query->active()->orderBy('name');
        }]);

        $productCount = $brand->products()->count();
        $activeProductCount = $brand->products()->active()->count();

        return Inertia::render('Brands/Show', [
            'brand' => $brand,
            'productCount' => $productCount,
            'activeProductCount' => $activeProductCount,
        ]);
    }

    public function edit(Brand $brand)
    {
        return Inertia::render('Brands/Edit', [
            'brand' => $brand,
        ]);
    }

    public function update(Request $request, Brand $brand)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'website' => 'nullable|url',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:1024',
            'is_active' => 'boolean',
        ]);

        $data = $request->except(['logo']);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($brand->logo_url) {
                Storage::disk('public')->delete($brand->logo_url);
            }

            $logo = $request->file('logo');
            $filename = uniqid() . '.' . $logo->getClientOriginalExtension();

            $compressedLogo = Image::make($logo)
                ->resize(300, 200, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })
                ->encode('jpg', 75);

            $path = 'brands/logos/' . date('Y/m');
            Storage::disk('public')->put($path . '/' . $filename, $compressedLogo);
            $data['logo_url'] = $path . '/' . $filename;
        }

        $data['updated_by'] = Auth::id();
        $brand->update($data);

        return redirect()->route('brands.index')
            ->with('success', 'Brand updated successfully!');
    }

    public function destroy(Brand $brand)
    {
        // Check if brand has products
        if ($brand->products()->exists()) {
            return redirect()->back()
                ->with('error', 'Cannot delete brand with products. Move or delete the products first.');
        }

        // Delete logo if exists
        if ($brand->logo_url) {
            Storage::disk('public')->delete($brand->logo_url);
        }

        $brand->delete();

        return redirect()->route('brands.index')
            ->with('success', 'Brand deleted successfully!');
    }

    // API endpoints
    public function apiIndex()
    {
        $brands = Brand::active()
            ->orderBy('name')
            ->get(['id', 'name', 'logo_url']);

        return response()->json($brands);
    }

    public function apiSearch(Request $request)
    {
        $search = $request->input('search');

        $brands = Brand::active()
            ->where('name', 'like', "%{$search}%")
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'logo_url']);

        return response()->json($brands);
    }
}