<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';

    protected $fillable = [
        'user_id', 'name', 'email', 'phone', 
        'address', 'note', 'created_by', 
        'updated_by', 'status'
    ];

    // Quan hệ: Thuộc về User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Quan hệ: Có nhiều chi tiết đơn hàng
    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class, 'order_id','id');
    }
}