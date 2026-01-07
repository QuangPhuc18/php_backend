<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductImage;
use App\Models\Attribute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductController extends Controller
{
    // ================================
    // GET LIST (Danh sách sản phẩm)
    // ================================
    public function index(Request $request) 
    {
        // Load thêm quan hệ 'images' để lấy ảnh phụ
        $query = Product::with(['product_attributes.attribute', 'images']);
        
        if ($request->search) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }
        
        // Thêm filter theo category nếu cần (mở rộng)
        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }
        
        $query->orderBy('created_at', 'desc');
        $limit = $request->input('limit', 10);
        $products = $query->paginate($limit);
        
        // Format dữ liệu trả về
        $products->getCollection()->transform(function ($product) {
            // 1. Xử lý Thumbnail
            $product->image_url = $product->thumbnail
                ? (filter_var($product->thumbnail, FILTER_VALIDATE_URL) 
                    ? $product->thumbnail 
                    : asset('storage/'.$product->thumbnail))
                : null;
            
            // 2. Format danh sách ảnh phụ (Gallery)
            $product->gallery = $product->images->map(function($img) {
                return [
                    'id' => $img->id,
                    'url' => asset('storage/' . $img->image),
                    'alt' => $img->alt
                ];
            });

            // 3. Format attributes (nhóm theo attribute_id)
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
    // GET DETAIL (Chi tiết sản phẩm)
    // ================================
    public function show($id)
    {
        // 1. Lấy giờ Việt Nam để check Sale
        $now = Carbon::now('Asia/Ho_Chi_Minh');

        // 2. Query Sản phẩm kèm các quan hệ
        $product = Product::with([
            'product_attributes.attribute', 
            'images', 
            'sales' => function($q) use ($now) {
                $q->where('status', 1)
                  ->where('date_begin', '<=', $now)
                  ->where('date_end', '>=', $now)
                  ->orderBy('created_at', 'desc');
            }
        ])->find($id);

        if (!$product) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy sản phẩm'], 404);
        }

        // 3. Xử lý Giá (Ưu tiên giá Sale nếu có)
        $activeSale = $product->sales->first();
        
        $product->is_sale = false;
        $product->price_final = $product->price_buy; // Mặc định là giá gốc

        if ($activeSale) {
            $product->is_sale = true;
            $product->price_final = $activeSale->price_sale;
            
            // Tính % giảm giá để hiển thị Badge
            $product->sale_info = [
                'discount_percent' => $product->price_buy > 0 
                    ? round((($product->price_buy - $activeSale->price_sale) / $product->price_buy) * 100) 
                    : 0,
                'end_date' => $activeSale->date_end
            ];
        }

        // 4. Xử lý URL Ảnh
        $product->image_url = $product->thumbnail 
            ? (filter_var($product->thumbnail, FILTER_VALIDATE_URL) ? $product->thumbnail : asset('storage/' . $product->thumbnail)) 
            : null;
            
        $product->gallery = $product->images->map(function($img) {
            return ['id' => $img->id, 'url' => asset('storage/' . $img->image)];
        });

        // 5. Gom nhóm Attribute (Size/Màu) để Frontend render nút chọn
        $product->grouped_attributes = $product->product_attributes
            ->groupBy('attribute_id')
            ->map(function($group) {
                $attrModel = $group->first()->attribute;
                return [
                    'id' => $attrModel ? $attrModel->id : 0,
                    'name' => ($attrModel && $attrModel->name) ? $attrModel->name : 'Size', 
                    'values' => $group->pluck('value')->toArray() // VD: ['S', 'M', 'L']
                ];
            })->values();

        return response()->json([
            'status' => true,
            'data' => $product
        ]);
    }

    // ================================
    // STORE (Thêm mới)
    // ================================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'             => 'required|string|max:255',
            'price_buy'        => 'required|numeric|min:0',
            'category_id'      => 'required|integer',
            'content'          => 'nullable|string',
            'description'      => 'nullable|string',
            'thumbnail'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'product_attributes' => 'nullable|string', // JSON string
            'images'           => 'nullable|array',
            'images.*'         => 'image|mimes:jpg,jpeg,png,webp|max:4096'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // 1. Upload thumbnail
            $filePath = null;
            if ($request->hasFile('thumbnail')) {
                $filePath = $request->file('thumbnail')->store('products', 'public');
            }

            // 2. Tạo sản phẩm
            $product = Product::create([
                'name'        => $request->name,
                'slug'        => $request->slug ?: Str::slug($request->name),
                'category_id' => $request->category_id,
                'content'     => $request->content ?? '',
                'description' => $request->description,
                'price_buy'   => $request->price_buy,
                'thumbnail'   => $filePath,
                'status'      => $request->status ?? 1,
            ]);

            // 3. Xử lý lưu nhiều ảnh phụ (Gallery)
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $file) {
                    $galleryPath = $file->store('product_gallery', 'public');
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image'      => $galleryPath,
                        'alt'        => $request->name
                    ]);
                }
            }

            // 4. Xử lý product_attributes (JSON)
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
            $product->load(['product_attributes.attribute', 'images']);
            $product->image_url = $filePath ? asset('storage/'.$filePath) : null;

            return response()->json([
                'status' => true,
                'message' => 'Thêm sản phẩm thành công',
                'data' => $product
            ]);

        } catch (\Throwable $th) {
            DB::rollBack();
            // Xóa ảnh thumbnail nếu lỡ upload mà lỗi DB
            if (isset($filePath) && $filePath && Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }
            return response()->json(['status' => false, 'message' => 'Lỗi hệ thống: ' . $th->getMessage()], 500);
        }
    }

    // ================================
    // UPDATE (Cập nhật)
    // ================================
    public function update(Request $request, $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy sản phẩm'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'             => 'nullable|string|max:255',
            'price_buy'        => 'nullable|numeric|min:0',
            'category_id'      => 'nullable|integer',
            'content'          => 'nullable|string',
            'description'      => 'nullable|string',
            'thumbnail'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'product_attributes' => 'nullable|string',
            'images'           => 'nullable|array',
            'images.*'         => 'image|mimes:jpg,jpeg,png,webp|max:4096'
        ]);
       
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // 1. Upload thumbnail mới (nếu có)
            if ($request->hasFile('thumbnail')) {
                if ($product->thumbnail && Storage::disk('public')->exists($product->thumbnail)) {
                    Storage::disk('public')->delete($product->thumbnail);
                }
                $product->thumbnail = $request->file('thumbnail')->store('products', 'public');
            }

            // 2. Cập nhật thông tin cơ bản
            $dataToUpdate = $request->except(['thumbnail', '_method', 'product_attributes', 'images']);
            
            // Xử lý field content nếu gửi null hoặc empty
            if (array_key_exists('content', $dataToUpdate) && $dataToUpdate['content'] === null) {
                $dataToUpdate['content'] = '';
            } elseif (!array_key_exists('content', $dataToUpdate) && $request->has('content')) {
                 $dataToUpdate['content'] = '';
            }

            $product->update($dataToUpdate);

            // 3. Thêm ảnh phụ mới (Cộng dồn vào ảnh cũ)
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

            // 4. Cập nhật attributes (Xóa cũ, tạo mới)
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

            $product->load(['product_attributes.attribute', 'images']);
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
    // DESTROY (Xóa)
    // ================================
    public function destroy($id)
    {
        $product = Product::with('images')->find($id);
        
        if (!$product) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy'], 404);
        }

        DB::beginTransaction();
        try {
            // 1. Xóa file thumbnail
            if ($product->thumbnail && Storage::disk('public')->exists($product->thumbnail)) {
                Storage::disk('public')->delete($product->thumbnail);
            }

            // 2. Xóa tất cả file ảnh phụ
            foreach ($product->images as $img) {
                if ($img->image && Storage::disk('public')->exists($img->image)) {
                    Storage::disk('public')->delete($img->image);
                }
            }
            
            // 3. Xóa record ảnh phụ trong DB
            ProductImage::where('product_id', $id)->delete();

            // 4. Xóa attributes
            ProductAttribute::where('product_id', $product->id)->delete();
            
            // 5. Xóa sản phẩm
            $product->delete(); 

            DB::commit();

            return response()->json(['status' => true, 'message' => 'Xóa thành công']);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'Lỗi hệ thống: ' . $th->getMessage()], 500);
        }
    }
}