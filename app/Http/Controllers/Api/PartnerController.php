<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        return response()->json([
            'status' => 200,
            'partners' => $partners,
        ]);
    }

    public function store(Request $request)
    {
        $maxId = Partner::max('id') ?? 0;
        DB::statement('ALTER TABLE partners AUTO_INCREMENT = ' . ($maxId + 1));

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'identification' => 'required|unique:partners,identification|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string|max:255',
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
            'address' => 'nullable|string|max:255',
        ]);

        $partner->update($validated);

        return response()->json([
            'message' => 'Socio actualizado',
            'partner' => $partner,
        ]);
    }

    public function destroy(int $id)
    {
        $partner = Partner::findOrFail($id);

        if ($partner->contributions()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el socio porque tiene aportes registrados.',
            ], 422);
        }

        $partner->delete();

        return response()->json(['message' => 'Socio eliminado']);
    }
}
