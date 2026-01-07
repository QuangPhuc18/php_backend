<?php

namespace App\Http\Controllers;

use App\Models\Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;

class ConfigController extends Controller
{
    public function index(Request $request)
    {
        $query = Config::query();

        if ($request->search) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('site_name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%")
                  ->orWhere('hotline', 'like', "%{$s}%")
                  ->orWhere('address', 'like', "%{$s}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $query->orderBy('id', 'desc');

        $limit = (int) $request->input('limit', 20);
        $items = $query->paginate($limit);

        return response()->json([
            'status' => true,
            'message' => 'Lấy danh sách cấu hình thành công',
            'data' => $items->items(),
            'meta' => [
                'total' => $items->total(),
                'per_page' => $items->perPage(),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
            ],
        ]);
    }

    public function show($id)
    {
        $item = Config::find($id);
        if (!$item) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy cấu hình'], 404);
        }

        return response()->json(['status' => true, 'message' => 'Lấy chi tiết thành công', 'data' => $item]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'site_name' => 'required|string|max:255',
            'email'     => 'required|email|max:255',
            'phone'     => 'nullable|string|max:50',
            'hotline'   => 'nullable|string|max:50',
            'address'   => 'nullable|string|max:1000',
            'status'    => 'nullable|integer|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->only(['site_name','email','phone','hotline','address','status']);
        $data['status'] = $data['status'] ?? 1;

        // Only set created_by if column exists
        $table = (new Config())->getTable();
        if (Schema::hasColumn($table, 'created_by')) {
            $data['created_by'] = $request->user()?->id ?? null;
        }

        $config = Config::create($data);

        return response()->json(['status' => true, 'message' => 'Tạo cấu hình thành công', 'data' => $config], 201);
    }

    public function update(Request $request, $id)
    {
        $config = Config::find($id);
        if (!$config) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy cấu hình'], 404);
        }

        $validator = Validator::make($request->all(), [
            'site_name' => 'nullable|string|max:255',
            'email'     => 'nullable|email|max:255',
            'phone'     => 'nullable|string|max:50',
            'hotline'   => 'nullable|string|max:50',
            'address'   => 'nullable|string|max:1000',
            'status'    => 'nullable|integer|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->only(['site_name','email','phone','hotline','address','status']);
        if (!array_key_exists('status', $data)) {
            unset($data['status']);
        }

        $config->update($data);

        // Only set updated_by if column exists
        $table = $config->getTable();
        if (Schema::hasColumn($table, 'updated_by')) {
            $config->updated_by = $request->user()?->id ?? $config->updated_by;
            $config->save();
        }

        return response()->json(['status' => true, 'message' => 'Cập nhật cấu hình thành công', 'data' => $config]);
    }

    public function destroy($id)
    {
        $config = Config::find($id);
        if (!$config) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy cấu hình'], 404);
        }

        $config->delete();

        return response()->json(['status' => true, 'message' => 'Xóa cấu hình thành công']);
    }
}