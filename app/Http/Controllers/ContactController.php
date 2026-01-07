<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    // LIST + SEARCH + PAGINATION
    public function index(Request $request)
    {
        $query = Contact::query();

        // Include replies param: if not provided, still treat reply_id = 0 as root.
        $includeReplies = (bool) $request->input('include_replies', false);

        if (!$includeReplies) {
            // treat NULL OR 0 as "no reply" (root messages)
            $query->where(function ($q) {
                $q->whereNull('reply_id')
                  ->orWhere('reply_id', 0);
            });
        }

        if ($request->search) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%")
                  ->orWhere('content', 'like', "%{$s}%");
            });
        }

        $query->orderBy('created_at', 'desc');

        $limit = (int) $request->input('limit', 10);
        $items = $query->with('replies')->paginate($limit);

        return response()->json([
            'status' => true,
            'message' => 'Lấy danh sách liên hệ thành công',
            'data' => $items->items(),
            'meta' => [
                'total' => $items->total(),
                'per_page' => $items->perPage(),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
            ],
        ]);
    }

    // SHOW DETAIL (including replies)
    public function show($id)
    {
        $item = Contact::with('replies')->find($id);
        if (!$item) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy liên hệ'], 404);
        }

        return response()->json(['status' => true, 'message' => 'Lấy chi tiết thành công', 'data' => $item]);
    }

    // STORE (public contact submission)
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer',
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|max:255',
            'phone'   => 'nullable|string|max:50',
            'content' => 'required|string',
            'status'  => 'nullable|integer|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $contact = Contact::create([
            'user_id' => $request->user_id ?? null,
            'name'    => $request->name,
            'email'   => $request->email,
            'phone'   => $request->phone,
            'content' => $request->content,
            'created_by' => $request->user()?->id ?? null,
            'status'  => $request->status ?? 1,
        ]);

        return response()->json(['status' => true, 'message' => 'Gửi liên hệ thành công', 'data' => $contact], 201);
    }

    // REPLY to a contact (admin action) — create a reply record linked to original contact
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

        $reply = Contact::create([
            'user_id' => $parent->user_id,
            'name'    => 'Admin Reply',
            'email'   => $parent->email,
            'phone'   => null,
            'content' => $request->content,
            'reply_id' => $parent->id,
            'created_by' => $request->user()?->id ?? null,
            'status'  => 1,
        ]);

        return response()->json(['status' => true, 'message' => 'Trả lời thành công', 'data' => $reply]);
    }

    // DELETE
    public function destroy($id)
    {
        $item = Contact::find($id);
        if (!$item) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy liên hệ'], 404);
        }

        // delete replies of this parent
        Contact::where('reply_id', $item->id)->delete();

        $item->delete();

        return response()->json(['status' => true, 'message' => 'Xóa liên hệ thành công']);
    }
}