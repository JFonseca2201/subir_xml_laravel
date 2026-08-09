<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json(\App\Models\Category::orderBy('name')->pluck('name'));
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|unique:categories,name']);
        $category = \App\Models\Category::create(['name' => $request->name]);
        return response()->json(['success' => true, 'category' => $category->name]);
    }
}
