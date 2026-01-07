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
                $query->where('topic_id', $request->topic_id);
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
                
                // Try FULLTEXT search first
                try {
                    $query->whereRaw(
                        "MATCH(title, content, description) AGAINST(? IN BOOLEAN MODE)", 
                        [$searchTerm . '*']
                    );
                } catch (\Throwable $e) {
                    // Fallback to LIKE search if FULLTEXT not available
                    $query->where(function ($q) use ($searchTerm) {
                        $q->where('title', 'like', "%{$searchTerm}%")
                          ->orWhere('content', 'like', "%{$searchTerm}%")
                          ->orWhere('description', 'like', "%{$searchTerm}%");
                    });
                }
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
                'message' => 'Lỗi khi lấy danh sách bài viết',
                'error' => $e->getMessage()
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
            Log::error('Error fetching post:', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Lỗi khi lấy chi tiết bài viết',
                'error' => $e->getMessage()
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
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:4096',
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
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('posts', 'public');
            }

            // Generate slug if not provided
            $slug = $request->input('slug');
            if (!$slug) {
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

            // Add created_by if column exists
            $table = (new Post())->getTable();
            if (Schema::hasColumn($table, 'created_by')) {
                $data['created_by'] = $request->user()?->id ?? null;
            }

            // Create post
            $post = Post::create($data);

            DB::commit();

            // Add image_url
            $post->image_url = $this->getImageUrl($post->image);

            Log::info('Post created successfully:', ['id' => $post->id]);

            return response()->json([
                'status' => true, 
                'message' => 'Tạo bài viết thành công', 
                'data' => $post
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            
            // Remove uploaded file if exists
            if (!empty($imagePath) && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }

            Log::error('Error creating post:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Lỗi khi tạo bài viết',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update existing post
     */
    public function update(Request $request, $id)
    {
        // Find post
        $post = Post::find($id);
        if (!$post) {
            return response()->json([
                'status' => false, 
                'message' => 'Không tìm thấy bài viết'
            ], 404);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'topic_id'    => 'nullable|integer',
            'title'       => 'nullable|string|max:255',
            'slug'        => 'nullable|string|max:255|unique:posts,slug,' . $id,
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:6090',
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
                
                // Upload new image
                $post->image = $request->file('image')->store('posts', 'public');
            }

            // Auto-generate slug if title changed but slug not provided
            if ($request->filled('title') && !$request->filled('slug')) {
                $slug = $this->generateUniqueSlug($request->title, $post->id);
                $request->merge(['slug' => $slug]);
            }

            // Update only provided fields
            $fillableFields = ['topic_id', 'title', 'slug', 'content', 'description', 'post_type', 'status'];
            foreach ($fillableFields as $field) {
                if ($request->has($field) && $request->input($field) !== null) {
                    $post->{$field} = $request->input($field);
                }
            }

            // Update updated_by if column exists
            $table = $post->getTable();
            if (Schema::hasColumn($table, 'updated_by')) {
                $post->updated_by = $request->user()?->id ?? null;
            }

            $post->save();

            DB::commit();

            // Refresh and add image_url
            $post->refresh();
            $post->image_url = $this->getImageUrl($post->image);

            Log::info('Post updated successfully:', ['id' => $post->id]);

            return response()->json([
                'status' => true, 
                'message' => 'Cập nhật bài viết thành công', 
                'data' => $post
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            
            Log::error('Error updating post:', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Lỗi khi cập nhật bài viết',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete post
     */
    public function destroy($id)
    {
        // Find post
        $post = Post::find($id);
        if (!$post) {
            return response()->json([
                'status' => false, 
                'message' => 'Không tìm thấy bài viết'
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Delete image if exists
            if ($post->image && Storage::disk('public')->exists($post->image)) {
                Storage::disk('public')->delete($post->image);
            }

            // Delete post
            $post->delete();

            DB::commit();

            Log::info('Post deleted successfully:', ['id' => $id]);

            return response()->json([
                'status' => true, 
                'message' => 'Xóa bài viết thành công'
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            
            Log::error('Error deleting post:', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false, 
                'message' => 'Lỗi khi xóa bài viết', 
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper: Get full image URL
     */
    protected function getImageUrl(?string $imagePath): ?string
    {
        if (!$imagePath) {
            return null;
        }

        // If already full URL
        if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
            return $imagePath;
        }

        // Generate storage URL
        return asset('storage/' . $imagePath);
    }
}