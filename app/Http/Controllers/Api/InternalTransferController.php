<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InternalTransfer;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InternalTransferController extends Controller
{
    /**
     * Display a listing of the transfers.
     */
    public function index(Request $request): JsonResponse
    {
        // Obtener transferencias con sus relaciones
        $transfers = InternalTransfer::with(['sourceAccount', 'destinationAccount', 'user'])
            ->orderBy('transfer_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Calcular resúmenes (Total hoy, Total mes, Total histórico)
        $today = Carbon::now('America/Guayaquil')->toDateString();
        $currentMonth = Carbon::now('America/Guayaquil')->format('Y-m');

        $resumen = [
            'total_hoy' => (float) $transfers->filter(function ($transfer) use ($today) {
                $date = $transfer->transfer_date
                    ? Carbon::parse($transfer->transfer_date)->format('Y-m-d')
                    : Carbon::parse($transfer->created_at)->setTimezone('America/Guayaquil')->format('Y-m-d');
                return $date === $today;
            })->sum('amount'),
            'total_mes' => (float) $transfers->filter(function ($transfer) use ($currentMonth) {
                $date = $transfer->transfer_date
                    ? Carbon::parse($transfer->transfer_date)->format('Y-m')
                    : Carbon::parse($transfer->created_at)->setTimezone('America/Guayaquil')->format('Y-m');
                return $date === $currentMonth;
            })->sum('amount'),
            'total_general' => (float) $transfers->sum('amount'),
        ];

        return response()->json([
            'data' => $transfers,
            'resumen' => $resumen
        ]);
    }

    /**
     * Store a newly created transfer in storage.
     */
    public function store(Request $request): JsonResponse 
    {
            $validated = $request->validate([
                'from_account_id' => 'required|exists:accounts,id',
                'to_account_id'   => 'required|exists:accounts,id|different:from_account_id',
                'amount'          => 'required|numeric|min:0.01',
                'description'     => 'nullable|string|max:1000',
                'reference_number' => 'nullable|string|max:255',
                'transfer_date'   => 'nullable|date',
            ], [
                'to_account_id.different' => 'La cuenta de destino debe ser diferente a la cuenta de origen.',
            ]);

            // 2. Ejecutar dentro de una transacción
            return DB::transaction(function () use ($validated) {

                // Obtener las cuentas involucradas
                $fromAccount = Account::findOrFail($validated['from_account_id']);
                $toAccount = Account::findOrFail($validated['to_account_id']);

            // Crear el registro de la transferencia
            $transfer = InternalTransfer::create($validated);

                // --- REGISTRO DE MOVIMIENTOS FINANCIEROS (TRAIT) ---

                $entryDate = $validated['transfer_date'] ?? now();

                // Registrar un único movimiento de tipo 'transfer'
                $transfer->registerMovement(
                    $fromAccount->id,
                    'transfer',
                    $validated['amount'],
                    "Transferencia interna: " . ($validated['description'] ?? 'Sin descripción'),
                    $entryDate,
                    [
                        'type' => 'internal_transfer', 
                        'from_account' => $fromAccount->id, 
                        'to_account' => $toAccount->id
                    ]
                );

                // 3. Actualizar saldos
                $fromAccount->updateBalance($validated['amount'], 1); // 1 = Egreso
                $toAccount->updateBalance($validated['amount'], 0);   // 0 = Ingreso

            // --- ACTUALIZACIÓN DE SALDOS ---
            
            // Tipo 1 = Egreso (resta de la cuenta de origen)
            $fromAccount->updateBalance($validated['amount'], 1);
            
            // Tipo 0 = Ingreso (suma a la cuenta de destino)
            $toAccount->updateBalance($validated['amount'], 0);

            return response()->json([
                'message' => 'Transferencia realizada con éxito y movimientos registrados.',
                'data' => $transfer->load(['sourceAccount', 'destinationAccount'])
            ], 201);
        });
    }

    /**
     * Update the specified transfer in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'from_account_id' => 'required|exists:accounts,id',
            'to_account_id'   => 'required|exists:accounts,id|different:from_account_id',
            'amount'          => 'required|numeric|min:0.01',
            'description'     => 'nullable|string|max:1000',
            'reference_number' => 'nullable|string|max:255',
            'transfer_date'   => 'nullable|date',
        ], [
            'to_account_id.different' => 'La cuenta de destino debe ser diferente a la cuenta de origen.',
        ]);

        return DB::transaction(function () use ($validated, $id) {
            $transfer = InternalTransfer::findOrFail($id);

            // 1. Revertir saldos anteriores (Lógica contable pura)
            $oldFromAccount = Account::findOrFail($transfer->from_account_id);
            $oldToAccount = Account::findOrFail($transfer->to_account_id);

            $oldFromAccount->updateBalance($transfer->amount, 0); // Devuelve el dinero al origen
            $oldToAccount->updateBalance($transfer->amount, 1);   // Resta el dinero del destino

            // 2. Actualizar el registro de la transferencia
            $transfer->update($validated);

            $entryDate = $validated['transfer_date'] ?? $transfer->created_at;

            // Actualizar a un único movimiento de tipo 'transfer'
            $transfer->registerMovement(
                $validated['from_account_id'],
                'transfer',
                $validated['amount'],
                "Transferencia interna (Actualizada): " . ($validated['description'] ?? 'Sin descripción'),
                $entryDate,
                [
                    'type' => 'internal_transfer_updated', 
                    'from_account' => $validated['from_account_id'], 
                    'to_account' => $validated['to_account_id'], 
                    'reference' => $validated['reference_number'] ?? null
                ]
            );

            // 3. Aplicar nuevos saldos
            $newFromAccount = Account::findOrFail($validated['from_account_id']);
            $newToAccount = Account::findOrFail($validated['to_account_id']);

            $newFromAccount->updateBalance($validated['amount'], 1); // Resta del nuevo origen
            $newToAccount->updateBalance($validated['amount'], 0);   // Sumar al nuevo destino

            return response()->json([
                'message' => 'Transferencia actualizada con éxito',
                'data' => $transfer->load(['sourceAccount', 'destinationAccount'])
            ]);
        });
    }
    /**
     * Remove the specified transfer from storage (Revertir).
     */
    public function destroy(int $id): JsonResponse
    {
        return DB::transaction(function () use ($id) {

            $internalTransfer = InternalTransfer::findOrFail($id);

            // 1. Identificar las cuentas para revertir saldos
            $fromAccount = Account::findOrFail($internalTransfer->from_account_id);
            $toAccount = Account::findOrFail($internalTransfer->to_account_id);

            // 2. Revertir saldos
            // Tipo 0 = Ingreso (devolvemos el dinero al origen)
            $fromAccount->updateBalance($internalTransfer->amount, 0);
            // Tipo 1 = Egreso (quitamos el dinero que llegó al destino)
            $toAccount->updateBalance($internalTransfer->amount, 1);

            // --- INTEGRACIÓN CON EL TRAIT / MOVIMIENTOS ---
            // Eliminamos los movimientos financieros relacionados antes de borrar la transferencia.
            // Asumiendo que la relación en tu modelo se llama financialMovement()
            $internalTransfer->financialMovement()->delete();

            // 3. Eliminar el registro de la transferencia
            $internalTransfer->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Transferencia revertida y eliminada exitosamente'
            ]);
        });
    }
}