<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    protected $table = 'banners';

    protected $fillable = [
        'name', 'image', 'link', 'position', 
        'sort_order', 'description', 
        'created_by', 'updated_by', 'status'
    ];
}