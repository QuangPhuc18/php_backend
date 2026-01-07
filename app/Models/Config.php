<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Config extends Model
{
    use HasFactory;

    protected $table = 'configs';
    public $timestamps = false; // File mô tả không ghi created_at cho bảng này

    protected $fillable = [
        'site_name', 'email', 'phone', 
        'hotline', 'address', 'status'
    ];
}