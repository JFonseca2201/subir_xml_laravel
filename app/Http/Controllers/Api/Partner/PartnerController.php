<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner\Partner;
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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'identification' => 'required|unique:partners,identification|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'status' => 'nullable|string|in:active,inactive,0,1',
        ]);

        if ($request->has('status')) {
            $status = $request->input('status');
            $validated['is_active'] = ($status === 'active' || $status === 1 || $status === '1');
            unset($validated['status']);
        }

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
            'name' => 'required|string|max:255|unique:partners,name,'.$partner->id,
            'email' => 'required|email|max:255|unique:partners,email,'.$partner->id,
            'identification' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'status' => 'nullable|string|in:active,inactive,0,1',
        ]);

        if ($request->has('status')) {
            $status = $request->input('status');
            $validated['is_active'] = ($status === 'active' || $status === 1 || $status === '1');
            unset($validated['status']);
        }

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
            return response()->json(
                [
                    'message' => 'No se puede eliminar el socio porque tiene aportes registrados.',
                ],
                422,
            );
        }

        $partner->delete();

        return response()->json(['message' => 'Socio eliminado']);
    }
}
