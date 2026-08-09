<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UnitTypeController extends Controller
{
    public function index()
    {
        return response()->json(\App\Models\UnitType::orderBy('name')->pluck('name'));
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|unique:unit_types,name']);
        $unit = \App\Models\UnitType::create(['name' => $request->name]);
        return response()->json(['success' => true, 'unit' => $unit->name]);
    }
}
