<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use HasFactory;

    protected $table = 'menu';

    protected $fillable = [
        'name', 'link', 'type', 'parent_id', 
        'sort_order', 'table_id', 'position', 
        'created_by', 'updated_by', 'status'
    ];
    // Menu cha
public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id', 'id')->orderBy('sort_order', 'asc');
    }

    // Quan hệ ngược: Menu con thuộc về menu cha
    public function parent()
    {
        return $this->belongsTo(Menu::class, 'parent_id', 'id');
    }}