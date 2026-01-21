<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class TopicController extends Controller
{
    /**
     * Helper: Generate unique slug
     */
    protected function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $base = Str::slug($name) ?: Str::random(8);
        $slug = $base;
        $i = 1;

        while (
            Topic::where('slug', $slug)
                ->when($excludeId, fn($q) => $q->where('id', '<>', $excludeId))
                ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /**
     * 1. GET LIST (Danh sách chủ đề, phân trang, lọc, tìm kiếm)
     */
    public function index(Request $request)
    {
        try {
            $query = Topic::query();

            // Lọc theo trạng thái (0: Ẩn, 1: Hiện)
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Tìm kiếm theo tên hoặc mô tả
            if ($request->filled('search')) {
                $searchTerm = trim($request->search);
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'like', "%{$searchTerm}%")
                      ->orWhere('description', 'like', "%{$searchTerm}%");
                });
            }

            // Sắp xếp (Mặc định sort_order tăng dần, sau đó mới nhất)
            $query->orderBy('sort_order', 'asc')->orderBy('created_at', 'desc');

            // Phân trang
            $limit = max(1, min(100, (int) $request->input('limit', 10)));
            $topics = $query->paginate($limit);

            return response()->json([
                'status' => true,
                'message' => 'Lấy danh sách chủ đề thành công',
                'data' => $topics->items(),
                'meta' => [
                    'total' => $topics->total(),
                    'per_page' => $topics->perPage(),
                    'current_page' => $topics->currentPage(),
                    'last_page' => $topics->lastPage(),
                ],
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Error fetching topics list:', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'Lỗi khi lấy danh sách chủ đề: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 2. SHOW (Chi tiết chủ đề)
     */
    public function show($id)
    {
        try {
            $topic = Topic::find($id);
            
            if (!$topic) {
                return response()->json(['status' => false, 'message' => 'Không tìm thấy chủ đề'], 404);
            }

            return response()->json([
                'status' => true, 
                'message' => 'Lấy chi tiết chủ đề thành công', 
                'data' => $topic
            ], 200);

        } catch (\Throwable $e) {
            return response()->json(['status' => false, 'message' => 'Lỗi server: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 3. STORE (Thêm chủ đề mới)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'slug'        => 'nullable|string|max:255|unique:topics,slug',
            'description' => 'nullable|string|max:500',
            'sort_order'  => 'nullable|integer',
            'status'      => 'nullable|integer|in:0,1'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // Tự động tạo slug nếu không có
            $slug = $request->input('slug');
            if (empty($slug)) {
                $slug = $this->generateUniqueSlug($request->name);
            }

            // Chuẩn bị dữ liệu
            $data = [
                'name'        => $request->name,
                'slug'        => $slug,
                'description' => $request->description,
                'sort_order'  => $request->sort_order ?? 0,
                'status'      => $request->status ?? 1,
                'created_by'  => Auth::id() ?? 1, // Fallback ID 1 nếu chưa đăng nhập
            ];

            $topic = Topic::create($data);
            DB::commit();

            return response()->json([
                'status' => true, 
                'message' => 'Tạo chủ đề thành công', 
                'data' => $topic
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error creating topic: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Lỗi tạo chủ đề: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 4. UPDATE (Cập nhật chủ đề)
     */
    public function update(Request $request, $id)
    {
        $topic = Topic::find($id);
        if (!$topic) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy chủ đề'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'nullable|string|max:255',
            'slug'        => 'nullable|string|max:255|unique:topics,slug,' . $id,
            'description' => 'nullable|string|max:500',
            'sort_order'  => 'nullable|integer',
            'status'      => 'nullable|integer|in:0,1'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // Cập nhật Slug nếu đổi tên mà không nhập slug mới
            if ($request->filled('name') && !$request->filled('slug') && $request->name !== $topic->name) {
                $topic->slug = $this->generateUniqueSlug($request->name, $id);
            } elseif ($request->filled('slug')) {
                $topic->slug = $request->slug;
            }

            // Cập nhật các trường khác
            $fields = ['name', 'description', 'sort_order', 'status'];
            foreach ($fields as $field) {
                if ($request->has($field)) {
                    $topic->{$field} = $request->input($field);
                }
            }

            $topic->updated_by = Auth::id() ?? 1;
            $topic->save();
            DB::commit();

            return response()->json([
                'status' => true, 
                'message' => 'Cập nhật chủ đề thành công', 
                'data' => $topic
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error updating topic: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Lỗi cập nhật'], 500);
        }
    }

    /**
     * 5. DELETE (Xóa chủ đề)
     */
    public function destroy($id)
    {
        $topic = Topic::find($id);
        if (!$topic) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy chủ đề'], 404);
        }

        try {
            // Kiểm tra xem chủ đề có bài viết con không (Optional nhưng nên làm)
            $postCount = DB::table('posts')->where('topic_id', $id)->count();
            if ($postCount > 0) {
                return response()->json([
                    'status' => false, 
                    'message' => "Không thể xóa. Chủ đề này đang chứa {$postCount} bài viết."
                ], 400);
            }

            $topic->delete();
            return response()->json(['status' => true, 'message' => 'Xóa chủ đề thành công'], 200);

        } catch (\Throwable $e) {
            return response()->json(['status' => false, 'message' => 'Lỗi xóa chủ đề: ' . $e->getMessage()], 500);
        }
    }
}