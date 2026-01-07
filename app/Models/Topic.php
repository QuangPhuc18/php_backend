<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    use HasFactory;

    protected $table = 'topic';

    protected $fillable = [
        'name', 'slug', 'sort_order', 'description', 
        'created_by', 'updated_by', 'status'
    ];
    public function posts() {
    return $this->hasMany(Post::class, 'topic_id');
}
}