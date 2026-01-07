<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    // ================================
    // 1. GET LIST + SEARCH + PAGINATION (Giữ nguyên)
    // ================================
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->search) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%")
                  ->orWhere('username', 'like', "%{$s}%");
            });
        }

        $query->orderBy('created_at', 'desc');
        $limit = $request->input('limit', 10);
        $users = $query->paginate($limit);

        // Build avatar_url
        $users->getCollection()->transform(function ($user) {
            $user->avatar_url = $this->getAvatarUrl($user->avatar);
            return $user;
        });

        return response()->json([
            'status' => true,
            'message' => 'Lấy danh sách user thành công',
            'data' => $users->items(),
            'meta' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    // ================================
    // 2. STORE / REGISTER (Giữ nguyên + Note gửi mail)
    // ================================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|min:3|max:255',
            'email'    => 'required|string|email|max:255|unique:users,email',
            'phone'    => 'required|string|max:20|unique:users,phone',
            'username' => 'required|string|max:255|unique:users,username',
            'password' => 'required|string|min:6',
            'roles'    => 'nullable|in:admin,customer',
            'status'   => 'nullable|integer|in:0,1',
            'avatar'   => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096'
        ], [
            'email.unique' => 'Email đã tồn tại trong hệ thống.',
            'phone.unique' => 'Số điện thoại đã tồn tại.',
            'username.unique' => 'Tên đăng nhập đã tồn tại.',
            'password.min' => 'Mật khẩu phải từ 6 ký tự trở lên.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Đăng ký thất bại. Vui lòng kiểm tra lại thông tin.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $avatarPath = null;
            if ($request->hasFile('avatar')) {
                $avatarPath = $request->file('avatar')->store('avatars', 'public');
            }

            $user = User::create([
                'name'       => $request->name,
                'email'      => $request->email,
                'phone'      => $request->phone,
                'username'   => $request->username,
                'password'   => Hash::make($request->password),
                'roles'      => $request->roles ?? 'customer',
                'avatar'     => $avatarPath,
                'created_by' => 1,
                'status'     => $request->status ?? 1, // Mặc định 1 (Active) hoặc 0 nếu cần xác thực email
            ]);

            // TODO: Gửi email xác thực ở đây (Sử dụng Mail::to($user)->send(new VerifyEmail($user)))
            
            $user->avatar_url = $this->getAvatarUrl($avatarPath);

            return response()->json([
                'status' => true,
                'message' => 'Đăng ký thành công',
                'data' => $user
            ], 201);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => 'Lỗi hệ thống: ' . $th->getMessage()
            ], 500);
        }
    }

    // ================================
    // 3. UPDATE USER (Admin update user khác - Giữ nguyên)
    // ================================
    public function update(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy user'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'     => 'nullable|string|min:3|max:255',
            'email'    => 'nullable|email|max:255|unique:users,email,' . $id,
            'phone'    => 'nullable|string|max:20|unique:users,phone,' . $id,
            'username' => 'nullable|string|max:255|unique:users,username,' . $id,
            'password' => 'nullable|string|min:6',
            'roles'    => 'nullable|in:admin,customer',
            'status'   => 'nullable|integer|in:0,1',
            'avatar'   => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        $input = $request->except(['avatar', '_method']);
        if (!empty($input['password'])) {
            $input['password'] = Hash::make($input['password']);
        } else {
            unset($input['password']);
        }

        $user->update($input);
        $user->avatar_url = $this->getAvatarUrl($user->avatar);

        return response()->json([
            'status' => true,
            'message' => 'Cập nhật user thành công',
            'data' => $user
        ]);
    }

    // ================================
    // 4. DELETE USER (Giữ nguyên)
    // ================================
    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy user'], 404);
        }

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->delete();
        return response()->json(['status' => true, 'message' => 'Xóa user thành công']);
    }

    // ================================
    // 5. LOGIN (Cập nhật logic Email/Phone/Username)
    // ================================
    public function login(Request $request)
    {
        // Cho phép nhận key 'login' hoặc 'email' từ frontend
        $loginInput = $request->input('login') ?? $request->input('email');
        $password = $request->input('password');

        if (!$loginInput || !$password) {
             return response()->json(['status' => false, 'message' => 'Vui lòng nhập tài khoản và mật khẩu'], 422);
        }

        // Xác định loại đăng nhập: Email, Phone hay Username
        $fieldType = 'username';
        if (filter_var($loginInput, FILTER_VALIDATE_EMAIL)) {
            $fieldType = 'email';
        } elseif (is_numeric($loginInput)) {
            $fieldType = 'phone';
        }

        // 1. Kiểm tra User có tồn tại không
        $user = User::where($fieldType, $loginInput)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Thông tin đăng nhập không chính xác.',
            ], 401);
        }

        // 2. Kiểm tra trạng thái kích hoạt
        if ($user->status != 1) {
            return response()->json([
                'status' => false,
                'message' => 'Tài khoản chưa kích hoạt hoặc bị khóa. Vui lòng liên hệ Admin.',
            ], 403);
        }

        // 3. Tạo Token
        $token = $user->createToken('auth_token')->plainTextToken;
        $user->avatar_url = $this->getAvatarUrl($user->avatar);

        return response()->json([
            'status' => true,
            'message' => 'Đăng nhập thành công',
            'data' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    // ================================
    // 6. LOGOUT (Giữ nguyên)
    // ================================
    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            /** @var \Laravel\Sanctum\PersonalAccessToken $token */
            $token = $user->currentAccessToken();
            $token->delete();
        }

        return response()->json([
            'status' => true,
            'message' => 'Đăng xuất thành công'
        ]);
    }

    // ================================
    // 7. GET PROFILE (Đã đăng nhập)
    // ================================
    public function profile(Request $request)
    {
        $user = $request->user();
        $user->avatar_url = $this->getAvatarUrl($user->avatar);
        
        return response()->json([
            'status' => true,
            'data' => $user
        ]);
    }

    // ================================
    // 8. UPDATE PROFILE (Người dùng tự sửa thông tin)
    // ================================
    public function updateProfile(Request $request)
    {
        $user = $request->user(); // Lấy user từ token

        $validator = Validator::make($request->all(), [
            'name'    => 'required|string|min:3|max:255',
            'email'   => 'required|email|unique:users,email,' . $user->id,
            'phone'   => 'required|string|unique:users,phone,' . $user->id,
            'address' => 'nullable|string|max:255', // Thêm địa chỉ nếu cần
            'avatar'  => 'nullable|image|max:4096'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'Dữ liệu không hợp lệ', 'errors' => $validator->errors()], 422);
        }

        // Xử lý Avatar
        if ($request->hasFile('avatar')) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($request->only(['name', 'email', 'phone', 'address']));
        $user->avatar_url = $this->getAvatarUrl($user->avatar);

        return response()->json([
            'status' => true,
            'message' => 'Cập nhật hồ sơ thành công',
            'data' => $user
        ]);
    }

    // ================================
    // 9. CHANGE PASSWORD (Đổi mật khẩu)
    // ================================
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password'     => 'required|min:6|confirmed', // Cần field new_password_confirmation
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'Dữ liệu không hợp lệ', 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        // Kiểm tra mật khẩu cũ
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['status' => false, 'message' => 'Mật khẩu hiện tại không đúng'], 400);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        return response()->json(['status' => true, 'message' => 'Đổi mật khẩu thành công']);
    }

    // --- Helper function ---
    private function getAvatarUrl($path) {
        if (!$path) return null;
        return filter_var($path, FILTER_VALIDATE_URL) ? $path : asset('storage/' . $path);
    }
}