<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BannerController extends Controller
{
    // LIST + SEARCH + PAGINATION
    public function index(Request $request)
    {
        $query = Banner::query();

        if ($request->search) {
            $s = $request->search;
            $query->where('name', 'like', "%{$s}%");
        }

        $query->orderBy('sort_order', 'asc')->orderBy('created_at', 'desc');

        $limit = (int) $request->input('limit', 10);
        $banners = $query->paginate($limit);

        $banners->getCollection()->transform(function ($banner) {
            $banner->image_url = $banner->image
                ? (filter_var($banner->image, FILTER_VALIDATE_URL)
                    ? $banner->image
                    : asset('storage/' . $banner->image))
                : null;
            return $banner;
        });

        return response()->json([
            'status' => true,
            'message' => 'Lấy danh sách banner thành công',
            'data' => $banners->items(),
            'meta' => [
                'total' => $banners->total(),
                'per_page' => $banners->perPage(),
                'current_page' => $banners->currentPage(),
                'last_page' => $banners->lastPage(),
            ],
        ]);
    }

    // DETAIL
    public function show($id)
    {
        $banner = Banner::find($id);
        if (!$banner) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy banner'], 404);
        }

        $banner->image_url = $banner->image
            ? (filter_var($banner->image, FILTER_VALIDATE_URL)
                ? $banner->image
                : asset('storage/' . $banner->image))
            : null;

        return response()->json(['status' => true, 'message' => 'Lấy chi tiết banner thành công', 'data' => $banner]);
    }

    // STORE
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:255',
            'image'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'link'       => 'nullable|string|max:1000',
            'position'   => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
            'description'=> 'nullable|string',
            'status'     => 'nullable|integer|in:0,1'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $filePath = null;
        if ($request->hasFile('image')) {
            $filePath = $request->file('image')->store('banners', 'public');
        }

        $banner = Banner::create([
            'name' => $request->name,
            'image' => $filePath,
            'link' => $request->link,
            'position' => $request->position,
            'sort_order' => $request->sort_order ?? 0,
            'description' => $request->description,
            'created_by' => 1, // adjust to Auth::id() if needed
            'status' => $request->status ?? 1
        ]);

        $banner->image_url = $filePath ? asset('storage/' . $filePath) : null;

        return response()->json(['status' => true, 'message' => 'Tạo banner thành công', 'data' => $banner], 201);
    }

    // UPDATE
    public function update(Request $request, $id)
    {
        $banner = Banner::find($id);
        if (!$banner) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy banner'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'       => 'nullable|string|max:255',
            'image'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'link'       => 'nullable|string|max:1000',
            'position'   => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
            'description'=> 'nullable|string',
            'status'     => 'nullable|integer|in:0,1'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('image')) {
            if ($banner->image && Storage::disk('public')->exists($banner->image)) {
                Storage::disk('public')->delete($banner->image);
            }
            $banner->image = $request->file('image')->store('banners', 'public');
        }

        $banner->update($request->except(['image', '_method']));

        $banner->updated_by = 1; // or Auth::id()
        $banner->save();

        $banner->image_url = $banner->image ? asset('storage/' . $banner->image) : null;

        return response()->json(['status' => true, 'message' => 'Cập nhật banner thành công', 'data' => $banner]);
    }

    // DELETE
    public function destroy($id)
    {
        $banner = Banner::find($id);
        if (!$banner) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy banner'], 404);
        }

        if ($banner->image && Storage::disk('public')->exists($banner->image)) {
            Storage::disk('public')->delete($banner->image);
        }

        $banner->delete();

        return response()->json(['status' => true, 'message' => 'Xóa banner thành công']);
    }
}