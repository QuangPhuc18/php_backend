<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attribute extends Model
{
    use HasFactory;

    protected $table = 'attributes';
    public $timestamps = false;

    protected $fillable = ['name'];

    // Thuộc tính này có ở những sản phẩm nào (qua bảng trung gian product_attributes)
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_attributes', 'attribute_id', 'product_id')
                    ->withPivot('value');
    }

    // Các bản ghi product_attributes liên quan (nếu cần)
    public function productAttributes()
    {
        return $this->hasMany(ProductAttribute::class, 'attribute_id');
    }
}