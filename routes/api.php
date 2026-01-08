<?php

use App\Http\Controllers\AttributeController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductSaleController;
use App\Http\Controllers\ProductStoreController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// --- 1. RESOURCES CHUẨN ---
Route::apiResource('products', ProductController::class);
Route::apiResource('categories', CategoryController::class);
Route::post('categories/{id}', [CategoryController::class, 'update']); // Fix cho FormData
Route::apiResource('/user', UserController::class);
Route::apiResource('banners', BannerController::class);
Route::apiResource('contacts', ContactController::class);
Route::post('contacts/{id}/reply', [ContactController::class, 'reply']);
Route::apiResource('configs', ConfigController::class);
Route::apiResource('posts', PostController::class);
Route::apiResource('orders', OrderController::class); // Dùng 1 cái orders thôi
Route::post('orders/{id}/status', [OrderController::class, 'updateStatus']);
Route::apiResource('attribute', AttributeController::class);
Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);
// --- 2. PRODUCT SALES (QUẢN LÝ KHUYẾN MÃI) ---
// URL: /api/sales/...
Route::prefix('sales')->group(function () {
    Route::get('/', [ProductSaleController::class, 'index']);
    Route::get('/products-selection', [ProductSaleController::class, 'getProductsForSelection']);
    Route::post('/store', [ProductSaleController::class, 'store']);
    // Xóa các route của store bị đặt nhầm ở đây
});
// Route xóa sale nằm ngoài prefix sales (do frontend gọi api/product-sales)
Route::delete('/product-sales/{id}', [ProductSaleController::class, 'destroy']);


// --- 3. PRODUCT STORE (QUẢN LÝ KHO) ---
// URL: /api/store/...
Route::prefix('store')->group(function () {
    // Lấy danh sách tồn kho
    Route::get('/', [ProductStoreController::class, 'index']);
    
    // Nhập hàng (Import)
    Route::post('/import', [ProductStoreController::class, 'importGoods']);
    
    // [QUAN TRỌNG] Route Sửa (Update) - Đã thêm vào đây
    // URL: PUT /api/store/{id}
    Route::put('/{id}', [ProductStoreController::class, 'update']); 
    
    // Xem chi tiết
    Route::get('/{id}', [ProductStoreController::class, 'show']);
    
    // Xóa lô hàng
    Route::delete('/{id}', [ProductStoreController::class, 'destroy']);
});


// --- 4. AUTHENTICATION ---
Route::post('/login', [UserController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [UserController::class, 'logout']);
    Route::get('/profile', [UserController::class, 'profile']);
    Route::post('/profile/update', [UserController::class, 'updateProfile']);
    Route::post('/profile/change-password', [UserController::class, 'changePassword']);
    
    // Lịch sử đơn hàng của User
    Route::get('/my-orders', [OrderController::class, 'myOrders']);
    Route::post('/my-orders/{id}/cancel', [OrderController::class, 'cancelOrder']);
});