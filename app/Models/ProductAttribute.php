<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductAttribute extends Model
{
    use HasFactory;

    protected $table = 'product_attributes';

    // Cho phép mass assignment các cột này
    protected $fillable = [
        'product_id',
        'attribute_id',
        'value',
    ];

    // Nếu bảng product_attributes không có timestamps, bạn có thể tắt:
    // public $timestamps = false;

    // Quan hệ ngược: thuộc về product
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // Quan hệ tới attribute (nếu có model Attribute)
    public function attribute()
    {
        return $this->belongsTo(Attribute::class, 'attribute_id');
    }
}