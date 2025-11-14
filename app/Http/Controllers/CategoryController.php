<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:products']);
    }

    public function index(Request $request)
    {
        $query = Category::with(['parent', 'children']);

        // Search
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('code', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%");
            });
        }

        // Filter by status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by parent category
        if ($request->filled('parent_id')) {
            if ($request->parent_id === 'root') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $request->parent_id);
            }
        }

        $categories = $query->orderBy('name')->paginate(25);
        $rootCategories = Category::root()->active()->orderBy('name')->get();

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
            'rootCategories' => $rootCategories,
            'filters' => $request->only(['search', 'is_active', 'parent_id']),
        ]);
    }

    public function create()
    {
        $parentCategories = Category::root()->active()->orderBy('name')->get();

        return Inertia::render('Categories/Create', [
            'parentCategories' => $parentCategories,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'code' => 'nullable|string|max:50|unique:categories,code',
            'parent_id' => 'nullable|exists:categories,id',
            'image_url' => 'nullable|url',
            'is_active' => 'boolean',
        ]);

        Category::create([
            'name' => $request->name,
            'description' => $request->description,
            'code' => $request->code ?? $this->generateCategoryCode(),
            'parent_id' => $request->parent_id,
            'image_url' => $request->image_url,
            'is_active' => $request->boolean('is_active', true),
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('categories.index')
            ->with('success', 'Category created successfully!');
    }

    public function show(Category $category)
    {
        $category->load(['parent', 'children', 'products' => function($query) {
            $query->active()->orderBy('name')->limit(10);
        }]);

        $productCount = $category->products()->count();
        $childCategoryCount = $category->children()->count();

        return Inertia::render('Categories/Show', [
            'category' => $category,
            'productCount' => $productCount,
            'childCategoryCount' => $childCategoryCount,
        ]);
    }

    public function edit(Category $category)
    {
        $category->load('parent');
        $parentCategories = Category::root()
            ->active()
            ->where('id', '!=', $category->id)
            ->orderBy('name')
            ->get();

        return Inertia::render('Categories/Edit', [
            'category' => $category,
            'parentCategories' => $parentCategories,
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'code' => 'nullable|string|max:50|unique:categories,code,' . $category->id,
            'parent_id' => [
                'nullable',
                'exists:categories,id',
                function($attribute, $value, $fail) use ($category) {
                    if ($value == $category->id) {
                        $fail('A category cannot be its own parent.');
                    }

                    // Check for circular reference
                    $parent = Category::find($value);
                    if ($parent && $this->isDescendant($parent, $category->id)) {
                        $fail('Circular reference detected. This would create an infinite loop.');
                    }
                },
            ],
            'image_url' => 'nullable|url',
            'is_active' => 'boolean',
        ]);

        $category->update([
            'name' => $request->name,
            'description' => $request->description,
            'code' => $request->code,
            'parent_id' => $request->parent_id,
            'image_url' => $request->image_url,
            'is_active' => $request->boolean('is_active'),
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('categories.index')
            ->with('success', 'Category updated successfully!');
    }

    public function destroy(Category $category)
    {
        // Check if category has products
        if ($category->products()->exists()) {
            return redirect()->back()
                ->with('error', 'Cannot delete category with products. Move or delete the products first.');
        }

        // Check if category has children
        if ($category->children()->exists()) {
            return redirect()->back()
                ->with('error', 'Cannot delete category with subcategories. Move or delete the subcategories first.');
        }

        $category->delete();

        return redirect()->route('categories.index')
            ->with('success', 'Category deleted successfully!');
    }

    public function tree()
    {
        $categories = Category::with(['children' => function($query) {
            $query->with('children');
        }])->whereNull('parent_id')->get();

        return Inertia::render('Categories/Tree', [
            'categories' => $categories,
        ]);
    }

    // API endpoints
    public function apiIndex()
    {
        $categories = Category::active()
            ->with('children')
            ->orderBy('name')
            ->get();

        return response()->json($categories);
    }

    public function apiTree()
    {
        $categories = Category::active()
            ->with(['children' => function($query) {
                $query->with(['children' => function($q) {
                    $q->active();
                }])->active();
            }])
            ->root()
            ->orderBy('name')
            ->get();

        return response()->json($categories);
    }

    private function isDescendant($category, $parentId)
    {
        if ($category->parent_id == $parentId) {
            return true;
        }

        if ($category->parent) {
            return $this->isDescendant($category->parent, $parentId);
        }

        return false;
    }

    private function generateCategoryCode()
    {
        do {
            $code = 'CAT-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (Category::where('code', $code)->exists());

        return $code;
    }
}