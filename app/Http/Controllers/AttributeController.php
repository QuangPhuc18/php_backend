<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use Illuminate\Http\Request;

class AttributeController extends Controller
{
    public function index()
    {
        $attributes = Attribute::select('id', 'name')->get();
        
        return response()->json([
            'status' => true,
            'message' => 'Lấy danh sách thành công',
            'data' => $attributes
        ]);
    }
}