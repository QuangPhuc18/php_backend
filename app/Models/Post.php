<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $table = 'posts';

    protected $fillable = [
        'topic_id', 'title', 'slug', 'image', 
        'content', 'description', 'post_type', 
        'created_by', 'updated_by', 'status'
    ];

    public function topic()
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }
}