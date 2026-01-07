<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductImage; // <--- NHỚ IMPORT MODEL NÀY
use App\Models\Attribute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    // ================================
    // GET LIST
    // ================================
    public function index(Request $request) 
    {
        // Load thêm quan hệ 'images' để lấy ảnh phụ
        $query = Product::with(['product_attributes.attribute', 'images']);
        
        if ($request->search) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }
        
        $query->orderBy('created_at', 'desc');
        $limit = $request->input('limit', 10);
        $products = $query->paginate($limit);
        
        $products->getCollection()->transform(function ($product) {
            // Xử lý ảnh đại diện (thumbnail)
            $product->image_url = $product->thumbnail
                ? (filter_var($product->thumbnail, FILTER_VALIDATE_URL) 
                    ? $product->thumbnail 
                    : asset('storage/'.$product->thumbnail))
                : null;
            
            // Xử lý danh sách ảnh phụ (images)
            $product->list_images = $product->images->map(function($img) {
                return [
                    'id' => $img->id,
                    'url' => asset('storage/' . $img->image),
                    'alt' => $img->alt
                ];
            });

            // Format attributes
            $product->formatted_attributes = $product->product_attributes->groupBy('attribute_id')->map(function($group) {
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

    // ================================
    // GET DETAIL
    // ================================
    public function show($id)
    {
        // Load kèm images
        $product = Product::with(['product_attributes.attribute', 'images'])->find($id);

        if (!$product) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy sản phẩm'], 404);
        }

        $product->image_url = $product->thumbnail
            ? (filter_var($product->thumbnail, FILTER_VALIDATE_URL) 
                ? $product->thumbnail 
                : asset('storage/' . $product->thumbnail))
            : null;

        // Trả về link đầy đủ cho các ảnh phụ
        $product->gallery = $product->images->map(function($img) {
            return [
                'id' => $img->id,
                'url' => asset('storage/' . $img->image)
            ];
        });

        // Group attributes
        $product->grouped_attributes = $product->product_attributes->groupBy('attribute_id')->map(function($group) {
            return [
                'attribute_id' => $group->first()->attribute_id,
                'attribute_name' => $group->first()->attribute->name ?? 'Unknown',
                'values' => $group->pluck('value')->toArray()
            ];
        })->values();

        return response()->json([
            'status' => true,
            'data' => $product
        ]);
    }

    // ================================
    // STORE (THÊM MỚI SẢN PHẨM + NHIỀU ẢNH)
    // ================================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'               => 'required|string|max:255',
            'price_buy'          => 'required|numeric|min:0',
            'category_id'        => 'required|integer',
            'content'            => 'nullable|string',
            'description'        => 'nullable|string',
            'thumbnail'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'product_attributes' => 'nullable|string',
            
            // Validate mảng ảnh phụ (images[])
            'images'             => 'nullable|array',
            'images.*'           => 'image|mimes:jpg,jpeg,png,webp|max:4096' 
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // 1. Upload thumbnail (Ảnh đại diện chính)
            $thumbnailPath = null;
            if ($request->hasFile('thumbnail')) {
                $thumbnailPath = $request->file('thumbnail')->store('products', 'public');
            }

            // 2. Tạo sản phẩm
            $product = Product::create([
                'name'        => $request->name,
                'slug'        => $request->slug ?: Str::slug($request->name),
                'category_id' => $request->category_id,
                'content'     => $request->content ?? '',
                'description' => $request->description,
                'price_buy'   => $request->price_buy,
                'thumbnail'   => $thumbnailPath,
                'status'      => $request->status ?? 1,
            ]);

            // 3. Xử lý Ảnh phụ (Multiple Images)
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $file) {
                    // Lưu ảnh vào folder 'product_gallery'
                    $galleryPath = $file->store('product_gallery', 'public');
                    
                    // Tạo record trong bảng product_images
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image'      => $galleryPath,
                        'alt'        => $request->name, // Mặc định lấy tên sp làm alt
                        'title'      => null
                    ]);
                }
            }

            // 4. Xử lý product_attributes (Giữ nguyên code cũ của bạn)
            if ($request->filled('product_attributes')) {
                $attributesData = json_decode($request->product_attributes, true);
                if (is_array($attributesData)) {
                    foreach ($attributesData as $attrGroup) {
                        $attributeId = $attrGroup['attribute_id'] ?? null;
                        $values = $attrGroup['values'] ?? [];
                        
                        if (!$attributeId || empty($values)) continue;
                        
                        foreach ($values as $value) {
                            $value = trim($value);
                            if ($value === '') continue;
                            ProductAttribute::create([
                                'product_id'   => $product->id,
                                'attribute_id' => $attributeId,
                                'value'        => $value
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            // Load lại quan hệ để trả về
            $product->load(['images', 'product_attributes.attribute']);
            $product->image_url = $thumbnailPath ? asset('storage/'.$thumbnailPath) : null;

            return response()->json([
                'status' => true,
                'message' => 'Thêm sản phẩm thành công',
                'data' => $product
            ]);

        } catch (\Throwable $th) {
            DB::rollBack();
            // Xóa thumbnail nếu lỗi
            if (isset($thumbnailPath) && Storage::disk('public')->exists($thumbnailPath)) {
                Storage::disk('public')->delete($thumbnailPath);
            }
            // (Nâng cao: Nên xóa cả các ảnh gallery đã lỡ upload nếu lỗi xảy ra ở bước sau)
            
            return response()->json(['status' => false, 'message' => 'Lỗi hệ thống: ' . $th->getMessage()], 500);
        }
    }

    // ================================
    // UPDATE
    // ================================
    public function update(Request $request, $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy sản phẩm'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'           => 'nullable|string|max:255',
            'price_buy'      => 'nullable|numeric|min:0',
            'category_id'    => 'nullable|integer',
            'thumbnail'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            // Validate mảng ảnh phụ (images[]) nếu có upload thêm
            'images'         => 'nullable|array',
            'images.*'       => 'image|mimes:jpg,jpeg,png,webp|max:4096'
        ]);
       
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // 1. Upload thumbnail mới
            if ($request->hasFile('thumbnail')) {
                if ($product->thumbnail && Storage::disk('public')->exists($product->thumbnail)) {
                    Storage::disk('public')->delete($product->thumbnail);
                }
                $product->thumbnail = $request->file('thumbnail')->store('products', 'public');
            }

            // 2. Update info
            $dataToUpdate = $request->except(['thumbnail', '_method', 'product_attributes', 'images']);
            if (array_key_exists('content', $dataToUpdate) && $dataToUpdate['content'] === null) {
                $dataToUpdate['content'] = '';
            } elseif (!array_key_exists('content', $dataToUpdate) && $request->has('content')) {
                 $dataToUpdate['content'] = '';
            }
            $product->update($dataToUpdate);

            // 3. Xử lý Ảnh phụ: CHỈ THÊM MỚI (Append), KHÔNG XÓA CŨ ở đây
            // (Việc xóa ảnh cũ thường được làm bằng 1 API riêng: DELETE /product-images/{id})
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $file) {
                    $galleryPath = $file->store('product_gallery', 'public');
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image'      => $galleryPath,
                        'alt'        => $product->name
                    ]);
                }
            }

            // 4. Update Attributes
            if ($request->filled('product_attributes')) {
                ProductAttribute::where('product_id', $product->id)->delete();
                $attributesData = json_decode($request->product_attributes, true);
                if (is_array($attributesData)) {
                    foreach ($attributesData as $attrGroup) {
                        $attributeId = $attrGroup['attribute_id'] ?? null;
                        $values = $attrGroup['values'] ?? [];
                        if (!$attributeId || empty($values)) continue;
                        foreach ($values as $value) {
                            $value = trim($value);
                            if ($value === '') continue;
                            ProductAttribute::create([
                                'product_id'   => $product->id,
                                'attribute_id' => $attributeId,
                                'value'        => $value
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            // Load lại quan hệ
            $product->load(['images', 'product_attributes.attribute']);
            $product->image_url = $product->thumbnail ? asset('storage/' . $product->thumbnail) : null;

            return response()->json([
                'status' => true,
                'message' => 'Cập nhật thành công',
                'data' => $product
            ]);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'Lỗi hệ thống: ' . $th->getMessage()], 500);
        }
    }

    // ================================
    // DESTROY
    // ================================
    public function destroy($id)
    {
        $product = Product::with('images')->find($id); // Load images để xóa file
        if (!$product) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy'], 404);
        }

        DB::beginTransaction();
        try {
            // Xóa thumbnail
            if ($product->thumbnail && Storage::disk('public')->exists($product->thumbnail)) {
                Storage::disk('public')->delete($product->thumbnail);
            }

            // Xóa file ảnh phụ trong storage
            foreach ($product->images as $img) {
                if ($img->image && Storage::disk('public')->exists($img->image)) {
                    Storage::disk('public')->delete($img->image);
                }
            }
            // Xóa record ảnh phụ (Database sẽ tự xóa nếu có cascade, nhưng xóa thủ công cho chắc)
            ProductImage::where('product_id', $id)->delete();

            // Xóa attribute
            ProductAttribute::where('product_id', $product->id)->delete();
            
            // Xóa sản phẩm
            $product->delete();

            DB::commit();

            return response()->json(['status' => true, 'message' => 'Xóa thành công']);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'Lỗi hệ thống: ' . $th->getMessage()], 500);
        }
    }
}