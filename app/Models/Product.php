<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'category_id', 'name', 'slug', 'thumbnail', 
        'content', 'description', 'price_buy', 
        'created_by', 'updated_by', 'status'
    ];

    // Quan hệ: Thuộc về một danh mục
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    // Quan hệ: Có nhiều ảnh phụ
    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id');
    }

    // Quan hệ: Có thông tin khuyến mãi
    public function sales()
    {
        return $this->hasMany(ProductSale::class, 'product_id');
    }

    // Quan hệ: Kho hàng
    public function stores()
    {
        return $this->hasMany(ProductStore::class, 'product_id');
    }
    
    // Quan hệ: Chi tiết đơn hàng
    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class, 'product_id');
    }
    // Lấy tất cả thuộc tính của sản phẩm này
// Cách dùng: $product->attributes
public function product_attributes()
{
    return $this->hasMany(ProductAttribute::class, 'product_id');
}
}