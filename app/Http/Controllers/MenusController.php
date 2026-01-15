<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class MenuController extends Controller
{
    // Lấy danh sách (có lọc theo vị trí và cấp cha/con)
    public function index(Request $request)
    {
        $query = Menu::query();

        // Lọc theo vị trí (mainmenu, footermenu)
        if ($request->filled('position')) {
            $query->where('position', $request->position);
        }

        // Lọc theo cha (nếu muốn lấy menu cấp 1 thì truyền parent_id=0)
        if ($request->filled('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        // Lọc theo trạng thái
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Sắp xếp theo thứ tự ưu tiên (sort_order)
        $query->orderBy('sort_order', 'asc');

        // Nếu muốn lấy cả menu con trong menu cha (nested)
        if ($request->boolean('with_children')) {
            $query->with('children');
        }

        $menus = $query->get(); // Menu thường không phân trang nhiều, lấy all

        return response()->json([
            'status' => true,
            'message' => 'Lấy danh sách menu thành công',
            'data' => $menus
        ]);
    }

    public function show($id)
    {
        $menu = Menu::find($id);
        if (!$menu) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy menu'], 404);
        }
        return response()->json(['status' => true, 'data' => $menu]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:255',
            'link'       => 'required|string',
            'type'       => 'required|in:category,page,topic,custom',
            'position'   => 'required|in:mainmenu,footermenu',
            'parent_id'  => 'nullable|integer',
            'sort_order' => 'nullable|integer',
            'status'     => 'nullable|integer|in:0,1',
            'table_id'   => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['created_by'] = Auth::id() ?? 1; // Mặc định admin ID 1 nếu chưa login
        $data['status'] = $request->status ?? 1;
        $data['parent_id'] = $request->parent_id ?? 0;
        $data['sort_order'] = $request->sort_order ?? 0;

        $menu = Menu::create($data);

        return response()->json(['status' => true, 'message' => 'Thêm menu thành công', 'data' => $menu], 201);
    }

    public function update(Request $request, $id)
    {
        $menu = Menu::find($id);
        if (!$menu) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy menu'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'       => 'nullable|string|max:255',
            'link'       => 'nullable|string',
            'type'       => 'nullable|in:category,page,topic,custom',
            'position'   => 'nullable|in:mainmenu,footermenu',
            'status'     => 'nullable|integer|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['updated_by'] = Auth::id() ?? 1;

        $menu->update($data);

        return response()->json(['status' => true, 'message' => 'Cập nhật menu thành công', 'data' => $menu]);
    }

    public function destroy($id)
    {
        $menu = Menu::find($id);
        if (!$menu) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy menu'], 404);
        }

        // Kiểm tra xem có menu con không, nếu có thì không cho xóa (hoặc xóa cả con - tùy logic)
        if ($menu->children()->count() > 0) {
            return response()->json(['status' => false, 'message' => 'Menu này đang chứa menu con, không thể xóa!'], 400);
        }

        $menu->delete();
        return response()->json(['status' => true, 'message' => 'Xóa menu thành công']);
    }
}