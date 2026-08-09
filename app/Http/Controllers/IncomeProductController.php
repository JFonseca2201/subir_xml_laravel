<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class IncomeProductController extends Controller
{
    public function index()
    {
        return response()->json(\App\Models\IncomeProduct::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'default_price' => 'required|numeric'
        ]);

        $product = \App\Models\IncomeProduct::updateOrCreate(
            ['name' => $request->name],
            ['default_price' => $request->default_price]
        );

        return response()->json(['success' => true, 'product' => $product]);
    }
}
