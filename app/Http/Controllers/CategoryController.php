<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    // ================================
    // GET LIST + SEARCH + PAGINATION
    // ================================
    public function index(Request $request)
    {
        $query = Category::query();

        // Search theo tên
        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Sắp xếp (Có thể sửa thành sort_order nếu muốn hiện theo thứ tự menu)
        $query->orderBy('created_at', 'desc');
        
        $limit = $request->input('limit', 10);
        $categories = $query->paginate($limit);

        // Xử lý link ảnh (Field là 'image' thay vì 'thumbnail')
        $categories->getCollection()->transform(function ($category) {
            $category->image_url = $category->image
                ? (filter_var($category->image, FILTER_VALIDATE_URL)
                    ? $category->image
                    : asset('storage/' . $category->image))
                : null;

            return $category;
        });

        return response()->json([
            'status' => true,
            'message' => 'Lấy danh sách danh mục thành công',
            'data' => $categories->items(),
            'meta' => [
                'total' => $categories->total(),
                'per_page' => $categories->perPage(),
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
            ],
        ]);
    }

    // ================================
    // GET DETAIL
    // ================================
    public function show($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy danh mục'], 404);
        }

        // Xử lý link ảnh
        $category->image_url = $category->image
            ? (filter_var($category->image, FILTER_VALIDATE_URL)
                ? $category->image
                : asset('storage/' . $category->image))
            : null;

        return response()->json([
            'status' => true,
            'message' => 'Lấy chi tiết danh mục thành công',
            'data' => $category
        ]);
    }

    // ================================
    // STORE CATEGORY
    // ================================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            // Fix: use correct table name "categories"
            'slug'        => 'nullable|string|max:255|unique:categories,slug',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'parent_id'   => 'nullable|integer',
            'sort_order'  => 'nullable|integer',
            'description' => 'nullable|string',
            'status'      => 'nullable|integer|in:0,1'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $filePath = null;

        // Xử lý upload ảnh vào thư mục 'categories'
        if ($request->hasFile('image')) {
            $filePath = $request->file('image')->store('categories', 'public');
        }

        // Tạo danh mục
        $category = Category::create([
            'name'        => $request->name,
            'slug'        => $request->slug ?: Str::slug($request->name),
            'image'       => $filePath,
            'parent_id'   => $request->parent_id ?? 0,
            'sort_order'  => $request->sort_order ?? 0,
            'description' => $request->description,
            'created_by'  => 1, // Mặc định admin ID 1 hoặc lấy Auth::id() nếu có login
            'status'      => $request->status ?? 1,
        ]);

        $category->image_url = $filePath ? asset('storage/' . $filePath) : null;

        return response()->json([
            'status' => true,
            'message' => 'Thêm danh mục thành công',
            'data' => $category
        ]);
    }

    // ================================
    // UPDATE CATEGORY
    // ================================
    public function update(Request $request, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy danh mục'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'nullable|string|max:255',
            // Fix: use correct table name "categories"
            'slug'        => 'nullable|string|max:255|unique:categories,slug,' . $id,
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'parent_id'   => 'nullable|integer',
            'sort_order'  => 'nullable|integer',
            'description' => 'nullable|string',
            'status'      => 'nullable|integer|in:0,1'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        // Xử lý ảnh mới
        if ($request->hasFile('image')) {
            // Xóa ảnh cũ nếu có và tồn tại trong đĩa
            if ($category->image && Storage::disk('public')->exists($category->image)) {
                Storage::disk('public')->delete($category->image);
            }

            // Lưu ảnh mới
            $category->image = $request->file('image')->store('categories', 'public');
        }

        // Cập nhật Slug nếu tên thay đổi và slug không được truyền lên
        if ($request->has('name') && !$request->has('slug')) {
            $request->merge(['slug' => Str::slug($request->name)]);
        }

        // Cập nhật thông tin (loại bỏ image và _method để tránh lỗi)
        $category->update($request->except(['image', '_method']));
        
        // Cập nhật updated_by
        $category->updated_by = 1; // Hoặc Auth::id()
        $category->save();

        // Build image URL trả về
        $category->image_url = $category->image
            ? asset('storage/' . $category->image)
            : null;

        return response()->json([
            'status' => true,
            'message' => 'Cập nhật thành công',
            'data' => $category
        ]);
    }

    // ================================
    // DELETE CATEGORY
    // ================================
    public function destroy($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy danh mục'], 404);
        }

        // Xóa ảnh từ storage
        if ($category->image && Storage::disk('public')->exists($category->image)) {
            Storage::disk('public')->delete($category->image);
        }

        $category->delete();

        return response()->json([
            'status' => true,
            'message' => 'Xóa danh mục thành công'
        ]);
    }
}