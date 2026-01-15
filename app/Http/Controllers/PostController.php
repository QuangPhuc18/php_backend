<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth; // Đã thêm dòng này để sửa lỗi Auth

class PostController extends Controller
{
    /**
     * Generate unique slug from title
     */
    protected function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $base = Str::slug($title) ?: Str::random(8);
        $slug = $base;
        $i = 1;

        while (
            Post::where('slug', $slug)
                ->when($excludeId, fn($q) => $q->where('id', '<>', $excludeId))
                ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /**
     * Get list of posts with filters and pagination
     */
    public function index(Request $request)
    {
        try {
            $query = Post::query();

            // Filter by topic_id
            if ($request->filled('topic_id')) {
                // Hỗ trợ lọc 'all' từ frontend
                if ($request->topic_id !== 'all') {
                    $query->where('topic_id', $request->topic_id);
                }
            }

            // Filter by post_type
            if ($request->filled('post_type')) {
                $query->where('post_type', $request->post_type);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Search in title, content, description
            if ($request->filled('search')) {
                $searchTerm = trim($request->search);
                
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'like', "%{$searchTerm}%")
                      ->orWhere('description', 'like', "%{$searchTerm}%");
                });
            }

            // Order by latest
            $query->orderBy('id', 'desc');

            // Paginate
            $limit = max(1, min(100, (int) $request->input('limit', 10)));
            $items = $query->paginate($limit);

            // Add image_url to each post
            $items->getCollection()->transform(function ($post) {
                $post->image_url = $this->getImageUrl($post->image);
                return $post;
            });

            return response()->json([
                'status' => true,
                'message' => 'Lấy danh sách bài viết thành công',
                'data' => $items->items(),
                'meta' => [
                    'total' => $items->total(),
                    'per_page' => $items->perPage(),
                    'current_page' => $items->currentPage(),
                    'last_page' => $items->lastPage(),
                ],
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Error fetching posts list:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Lỗi khi lấy danh sách bài viết: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get single post by ID
     */
    public function show($id)
    {
        try {
            $post = Post::find($id);
            
            if (!$post) {
                return response()->json([
                    'status' => false, 
                    'message' => 'Không tìm thấy bài viết'
                ], 404);
            }

            $post->image_url = $this->getImageUrl($post->image);

            return response()->json([
                'status' => true, 
                'message' => 'Lấy chi tiết bài viết thành công', 
                'data' => $post
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Lỗi server: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new post
     */
    public function store(Request $request)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'topic_id'    => 'nullable|integer',
            'title'       => 'required|string|max:255',
            'slug'        => 'nullable|string|max:255|unique:posts,slug',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:10240', // Max 10MB
            'content'     => 'nullable|string',
            'description' => 'nullable|string',
            'post_type'   => 'nullable|string|max:100',
            'status'      => 'nullable|integer|in:0,1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false, 
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        $imagePath = null;

        try {
            // Handle image upload
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('posts', 'public');
            }

            // Generate slug if not provided
            $slug = $request->input('slug');
            if (empty($slug)) {
                $slug = $this->generateUniqueSlug($request->title);
            }

            // Prepare data
            $data = [
                'topic_id'    => $request->topic_id,
                'title'       => $request->title,
                'slug'        => $slug,
                'image'       => $imagePath,
                'content'     => $request->content,
                'description' => $request->description,
                'post_type'   => $request->post_type ?? 'post',
                'status'      => $request->status ?? 1,
            ];

            // Add created_by safe check
            // Kiểm tra Auth::id() trước
            $userId = Auth::id();
            if (!$userId) {
                // Fallback nếu không đăng nhập (ví dụ gán cho admin ID = 1)
                // Hoặc bỏ qua nếu cột nullable
                $userId = 1; 
            }
            
            // Chỉ thêm vào data nếu cột tồn tại trong bảng
            if (Schema::hasColumn('posts', 'created_by')) {
                $data['created_by'] = $userId;
            }

            // Create post
            $post = Post::create($data);

            DB::commit();

            // Add image_url
            $post->image_url = $this->getImageUrl($post->image);

            return response()->json([
                'status' => true, 
                'message' => 'Tạo bài viết thành công', 
                'data' => $post
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            
            // Xóa ảnh nếu đã upload nhưng lỗi DB
            if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }

            Log::error('Error creating post:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Lỗi khi tạo bài viết: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update existing post
     */
    public function update(Request $request, $id)
    {
        $post = Post::find($id);
        if (!$post) {
            return response()->json([
                'status' => false, 
                'message' => 'Không tìm thấy bài viết'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'topic_id'    => 'nullable|integer',
            'title'       => 'nullable|string|max:255',
            'slug'        => 'nullable|string|max:255|unique:posts,slug,' . $id,
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:10240',
            'content'     => 'nullable|string',
            'description' => 'nullable|string',
            'post_type'   => 'nullable|string|max:100',
            'status'      => 'nullable|integer|in:0,1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false, 
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Handle image upload
            if ($request->hasFile('image')) {
                // Delete old image
                if ($post->image && Storage::disk('public')->exists($post->image)) {
                    Storage::disk('public')->delete($post->image);
                }
                $post->image = $request->file('image')->store('posts', 'public');
            }

            // Auto-generate slug if title changed
            if ($request->filled('title') && !$request->filled('slug')) {
                $post->slug = $this->generateUniqueSlug($request->title, $post->id);
            }

            // Update fields
            $fillableFields = ['topic_id', 'title', 'slug', 'content', 'description', 'post_type', 'status'];
            foreach ($fillableFields as $field) {
                if ($request->has($field)) {
                    $post->{$field} = $request->input($field);
                }
            }

            // Update updated_by
            if (Schema::hasColumn('posts', 'updated_by')) {
                $post->updated_by = Auth::id() ?? 1;
            }

            $post->save();
            DB::commit();

            $post->refresh();
            $post->image_url = $this->getImageUrl($post->image);

            return response()->json([
                'status' => true, 
                'message' => 'Cập nhật bài viết thành công', 
                'data' => $post
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error updating post: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Lỗi cập nhật'], 500);
        }
    }

    /**
     * Delete post
     */
    public function destroy($id)
    {
        $post = Post::find($id);
        if (!$post) {
            return response()->json(['status' => false, 'message' => 'Not found'], 404);
        }

        try {
            if ($post->image && Storage::disk('public')->exists($post->image)) {
                Storage::disk('public')->delete($post->image);
            }
            $post->delete();
            return response()->json(['status' => true, 'message' => 'Deleted successfully'], 200);
        } catch (\Throwable $e) {
            return response()->json(['status' => false, 'message' => 'Error deleting'], 500);
        }
    }

    // --- CÁC HÀM API MỚI CHO TRANG CHI TIẾT ---

   public function getPostBySlug($slug)
    {
        try {
            // 1. Tìm bài viết
            $post = Post::where('slug', $slug)->where('status', 1)->first();

            if (!$post) {
                return response()->json([
                    'status' => false,
                    'message' => 'Bài viết không tồn tại',
                    'data' => null
                ], 404);
            }

            // 2. Tạo full link ảnh (image_url)
            $post->image_url = $this->getImageUrl($post->image);

            // 3. Trả về JSON đúng cấu trúc
            return response()->json([
                'status' => true,
                'message' => 'Lấy chi tiết bài viết thành công',
                'data' => $post // Trả về object bài viết vào trong key 'data'
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Lỗi server: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Lấy bài viết liên quan
     */
    public function getRelatedPosts($topicId, $excludeId)
    {
        try {
            $posts = Post::where('topic_id', $topicId)
                ->where('id', '<>', $excludeId)
                ->where('status', 1)
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get();

            // Xử lý ảnh cho từng bài
            $posts->transform(function ($post) {
                $post->image_url = $this->getImageUrl($post->image);
                return $post;
            });

            return response()->json([
                'status' => true,
                'message' => 'Lấy bài liên quan thành công',
                'data' => $posts // Trả về mảng bài viết
            ], 200);

        } catch (\Throwable $e) {
            return response()->json(['status' => false, 'data' => []]);
        }
    }

    // Helper xử lý ảnh
    protected function getImageUrl(?string $imagePath): ?string
    {
        if (!$imagePath) return null;
        if (filter_var($imagePath, FILTER_VALIDATE_URL)) return $imagePath;
        return asset('storage/' . $imagePath);
    }
}