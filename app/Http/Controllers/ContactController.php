<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class ContactController extends Controller
{
    /**
     * Lấy danh sách liên hệ (Khớp cấu trúc JSON bạn yêu cầu)
     */
    public function index(Request $request)
    {
        $query = Contact::query();

        // 1. Chỉ lấy các tin nhắn gốc (reply_id = 0 hoặc NULL)
        $query->where(function ($q) {
            $q->where('reply_id', 0)
              ->orWhereNull('reply_id');
        });

        // 2. Tìm kiếm (nếu có)
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('content', 'like', "%{$s}%");
            });
        }

        // 3. Sắp xếp mới nhất trước
        $query->orderBy('created_at', 'desc');

        // 4. Load quan hệ replies để có mảng "replies": [] trong JSON
        $query->with('replies');

        // 5. Phân trang
        $limit = (int) $request->input('limit', 10);
        $contacts = $query->paginate($limit);

        // 6. Trả về cấu trúc JSON chuẩn
        return response()->json([
            'status' => true,
            'message' => 'Lấy danh sách liên hệ thành công',
            'data' => $contacts->items(), // Mảng dữ liệu chính
            'meta' => [
                'total' => $contacts->total(),
                'per_page' => $contacts->perPage(),
                'current_page' => $contacts->currentPage(),
                'last_page' => $contacts->lastPage(),
            ],
        ], 200);
    }

    /**
     * Gửi liên hệ mới (Khắc phục lỗi created_by null)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|max:255',
            'phone'   => 'nullable|string|max:20',
            'content' => 'required|string',
        ], [
            'name.required' => 'Vui lòng nhập họ tên',
            'email.required' => 'Vui lòng nhập email',
            'content.required' => 'Vui lòng nhập nội dung',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false, 
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Lấy ID người dùng hiện tại, nếu không đăng nhập thì gán mặc định là 0 (hoặc 1 tùy logic của bạn để tránh lỗi SQL)
            // Nếu bạn muốn khách vãng lai cũng gửi được, hãy set cứng giá trị này nếu Auth::id() null
            $creatorId = Auth::id() ?? 0; 

            $contact = Contact::create([
                'user_id'    => Auth::id() ?? null, // Có thể null nếu khách vãng lai
                'name'       => $request->name,
                'email'      => $request->email,
                'phone'      => $request->phone,
                'content'    => $request->content,
                'reply_id'   => 0, // Mặc định là tin nhắn gốc
                'status'     => 1, // 1: Chưa xử lý
                'created_by' => $creatorId, // FIX LỖI: Không để null
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Gửi liên hệ thành công',
                'data' => $contact
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Lỗi server: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xem chi tiết
     */
    public function show($id)
    {
        $contact = Contact::with('replies')->find($id);
        
        if (!$contact) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy liên hệ'], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Lấy chi tiết thành công',
            'data' => $contact
        ]);
    }

    /**
     * Trả lời liên hệ (Admin)
     */
    public function reply(Request $request, $id)
    {
        $parent = Contact::find($id);
        if (!$parent) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy liên hệ gốc'], 404);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        // Lấy ID Admin đang đăng nhập
        $adminId = Auth::id() ?? 1; 

        $reply = Contact::create([
            'user_id'    => $parent->user_id, // Gán cùng user với tin nhắn gốc
            'name'       => 'Admin Support',
            'email'      => $parent->email,
            'phone'      => null,
            'content'    => $request->content,
            'reply_id'   => $parent->id, // Link với tin nhắn gốc
            'status'     => 2, // Đã trả lời
            'created_by' => $adminId,
        ]);

        // Cập nhật trạng thái tin nhắn gốc thành đã xử lý
        $parent->status = 2; 
        $parent->save();

        return response()->json([
            'status' => true, 
            'message' => 'Trả lời thành công', 
            'data' => $reply
        ]);
    }

    /**
     * Xóa liên hệ
     */
    public function destroy($id)
    {
        $contact = Contact::find($id);
        if (!$contact) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy'], 404);
        }

        // Xóa cả các câu trả lời liên quan
        Contact::where('reply_id', $id)->delete();
        $contact->delete();

        return response()->json(['status' => true, 'message' => 'Xóa thành công']);
    }
}