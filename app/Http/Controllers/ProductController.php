<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductController extends Controller
{
    // =========================================================================
    // 1. GET LIST (Hỗ trợ Lọc & Sắp xếp hoàn chỉnh)
    // =========================================================================
    public function index(Request $request)
    {
        $query = Product::with(['product_attributes.attribute', 'images']);

        // --- 1. Lọc theo Tên (Search) ---
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // --- 2. Lọc theo Danh mục (Category) ---
        // Client gửi category_id (ví dụ: 1, 2, 3). Nếu là 'all' thì bỏ qua.
        if ($request->filled('category_id') && $request->category_id !== 'all') {
            $query->where('category_id', $request->category_id);
        }

        // --- 3. Lọc theo Giá (Price Range) ---
        // Xử lý logic lọc giá: Dưới 30k, 30-70k...
        if ($request->filled('price_min')) {
            $query->where('price_buy', '>=', $request->price_min);
        }
        
        if ($request->filled('price_max')) {
            $query->where('price_buy', '<=', $request->price_max);
        }

        // --- 4. Logic Phân Quyền (Admin/User) ---
        // Admin (hoặc popup nhập kho) thấy tất cả. User chỉ thấy status = 1.
        $isAdminRequest = $request->boolean('admin_view') || $request->boolean('for_import');
        if (!$isAdminRequest) {
            $query->where('status', 1);
        }

        // --- 5. Sắp xếp (Sort) ---
        $sort = $request->input('sort', 'newest');
        switch ($sort) {
            case 'price-asc': // Giá: Thấp -> Cao
                $query->orderBy('price_buy', 'asc');
                break;
                
            case 'price-desc': // Giá: Cao -> Thấp
                $query->orderBy('price_buy', 'desc');
                break;
                
            case 'name': // Tên A-Z
                $query->orderBy('name', 'asc');
                break;
                
            case 'newest': // Mới nhất
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        // --- 6. Phân trang ---
        $limit = $request->input('limit', 12);
        $products = $query->paginate($limit);

        // --- 7. Format dữ liệu trả về ---
        $products->getCollection()->transform(function ($product) {
            $product->image_url = $this->getValidImageUrl($product->thumbnail);
            
            $product->gallery = $product->images->map(function ($img) {
                return [
                    'id' => $img->id, 
                    'url' => asset('storage/' . $img->image), 
                    'alt' => $img->alt
                ];
            });

            $product->formatted_attributes = $product->product_attributes
                ->groupBy('attribute_id')
                ->map(function ($group) {
                    return [
                        'attribute_name' => $group->first()->attribute->name ?? 'Unknown',
                        'values' => $group->pluck('value')->toArray()
                    ];
                })->values();

            return $product;
        });

        return response()->json([
            'status' => true,
            'message' => 'Lấy danh sách thành công',
            'data' => $products->items(),
            'meta' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    // =========================================================================
    // 2. STORE (Thêm mới - Viết rõ ràng)
    // =========================================================================
    public function store(Request $request)
    {
        // Validate dữ liệu đầu vào
        $validator = Validator::make($request->all(), [
            'name'             => 'required|string|max:255',
            'price_buy'        => 'required|numeric|min:0',
            'category_id'      => 'required|integer',
            'thumbnail'        => 'nullable|image|max:4096',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // Upload Thumbnail
            $filePath = null;
            if ($request->hasFile('thumbnail')) {
                $filePath = $request->file('thumbnail')->store('products', 'public');
            }

            // Tạo sản phẩm (Status = 0: Ẩn chờ nhập kho)
            $product = Product::create([
                'name'        => $request->name,
                'slug'        => $request->slug ?: Str::slug($request->name),
                'category_id' => $request->category_id,
                'content'     => $request->content ?? '',
                'description' => $request->description,
                'price_buy'   => $request->price_buy,
                'thumbnail'   => $filePath,
                'status'      => 0, 
            ]);

            // Lưu ảnh Gallery
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $file) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image'      => $file->store('product_gallery', 'public'),
                        'alt'        => $request->name
                    ]);
                }
            }

            // Lưu thuộc tính (Size, Màu...)
            if ($request->filled('product_attributes')) {
                $this->saveAttributes($product->id, $request->product_attributes);
            }

            DB::commit();
            
            $product->image_url = $this->getValidImageUrl($product->thumbnail);
            
            return response()->json([
                'status' => true, 
                'data' => $product,
                'message' => 'Thêm thành công (Sản phẩm đang ẩn)'
            ]);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $th->getMessage()], 500);
        }
    }

    // =========================================================================
    // 3. UPDATE (Cập nhật - Viết rõ ràng & Fix lỗi Content null)
    // =========================================================================
    public function update(Request $request, $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['status' => false, 'message' => 'Not Found'], 404);
        }

        DB::beginTransaction();
        try {
            // Xử lý dữ liệu cập nhật
            $dataToUpdate = $request->except(['thumbnail', '_method', 'product_attributes', 'images']);
            
            // Fix lỗi SQL: Nếu content là null -> chuyển thành chuỗi rỗng
            if (array_key_exists('content', $dataToUpdate) && is_null($dataToUpdate['content'])) {
                $dataToUpdate['content'] = '';
            }

            // Upload thumbnail mới nếu có
            if ($request->hasFile('thumbnail')) {
                // Xóa ảnh cũ
                if ($product->thumbnail) {
                    Storage::disk('public')->delete($product->thumbnail);
                }
                $product->thumbnail = $request->file('thumbnail')->store('products', 'public');
            }

            $product->update($dataToUpdate);

            // Thêm ảnh Gallery mới
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $file) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image'      => $file->store('product_gallery', 'public'),
                        'alt'        => $product->name
                    ]);
                }
            }

            // Cập nhật thuộc tính
            if ($request->filled('product_attributes')) {
                ProductAttribute::where('product_id', $product->id)->delete();
                $this->saveAttributes($product->id, $request->product_attributes);
            }

            DB::commit();
            return response()->json(['status' => true, 'data' => $product]);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $th->getMessage()], 500);
        }
    }

    // =========================================================================
    // 4. Các hàm phụ trợ
    // =========================================================================
    public function destroy($id)
    {
        try {
            $product = Product::find($id);
            if (!$product) return response()->json(['status' => false], 404);
            $product->delete();
            return response()->json(['status' => true]);
        } catch (\Exception $e) {
            return response()->json(['status' => false], 500);
        }
    }

    public function show($id)
    {
        $product = Product::with(['product_attributes.attribute', 'images'])->find($id);
        if (!$product) return response()->json(['status' => false], 404);
        
        $product->image_url = $this->getValidImageUrl($product->thumbnail);
        return response()->json(['status' => true, 'data' => $product]);
    }

    private function getValidImageUrl($path)
    {
        if (!$path) return 'https://placehold.co/60';
        return Str::startsWith($path, ['http', 'https']) ? $path : asset('storage/' . $path);
    }

    private function saveAttributes($pid, $json)
    {
        $data = json_decode($json, true);
        if (is_array($data)) {
            foreach ($data as $group) {
                if (empty($group['values'])) continue;
                foreach ($group['values'] as $val) {
                    if (trim($val) != '') {
                        ProductAttribute::create([
                            'product_id' => $pid,
                            'attribute_id' => $group['attribute_id'] ?? null,
                            'value' => trim($val)
                        ]);
                    }
                }
            }
        }
    }
}