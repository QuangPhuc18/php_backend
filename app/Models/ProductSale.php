<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSale extends Model
{
    use HasFactory;

    protected $table = 'product_sales';

    protected $fillable = [
        'name', 'product_id', 'price_sale', 
        'date_begin', 'date_end', 'created_by', 
        'updated_by', 'status'
    ];
    
    protected $casts = [
        'date_begin' => 'datetime',
        'date_end' => 'datetime',
    ];
    public function product() {
    return $this->belongsTo(Product::class, 'product_id');
}
}