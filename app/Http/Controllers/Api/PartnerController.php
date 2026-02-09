<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->search;

        $partners = Partner::query()
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('identification', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(10);

        return response()->json($partners);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'identification' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
        ]);

        $partner = Partner::create($validated);

        return response()->json($partner, 201);
    }

    public function show(int $id)
    {
        $partner = Partner::with('contributions')->findOrFail($id);

        return response()->json($partner);
    }


    public function update(Request $request, int $id)
    {
        $partner = Partner::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:partners,name,' . $partner->id,
            'email' => 'required|email|max:255|unique:partners,email,' . $partner->id,
            'identification' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
        ]);

        $partner->update($validated);

        return response()->json($partner);
    }


    public function destroy(int $id)
    {
        $partner = Partner::findOrFail($id);

        if ($partner->contributions()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el socio porque tiene aportes registrados.'
            ], 422);
        }

        $partner->delete();

        return response()->json(['message' => 'Socio eliminado']);
    }

}