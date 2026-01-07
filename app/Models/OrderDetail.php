<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDetail extends Model
{
    use HasFactory;

    protected $table = 'order_details';
    public $timestamps = true;

    protected $fillable = [
        'order_id', 'product_id', 'price', 'size',
        'qty', 'amount', 'discount'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
    // Đã có product(), thiếu order()
public function order() {
    return $this->belongsTo(Order::class, 'order_id');
}
public function productAttribute()
{
    return $this->belongsTo(ProductAttribute::class, 'size');
}

}