<?php

namespace App\Http\Controllers\Api\Aporte;

use App\Http\Controllers\Controller;
use App\Models\Partner\AporteCapital;
use App\Models\Finance\MovimientoCuenta;
use App\Models\Finance\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class AporteController extends Controller
{
    /**
     * Listar aportes agrupados por fecha
     */
    public function index(Request $request)
    {
        $timezone = 'America/Guayaquil';
        $now = Carbon::now($timezone);
        $today = Carbon::today($timezone);

        // 1. Totales de Resumen calculados globalmente
        $totalHoy = (float) AporteCapital::whereNull('deleted_at')
            ->whereDate('fecha_aporte', $today)
            ->sum('monto');

        $totalMes = (float) AporteCapital::whereNull('deleted_at')
            ->whereYear('fecha_aporte', $now->year)
            ->whereMonth('fecha_aporte', $now->month)
            ->sum('monto');

        $totalGeneral = (float) AporteCapital::whereNull('deleted_at')->sum('monto');

        // 2. Query de Aportes con filtros y paginación para Scroll Infinito
        $query = AporteCapital::with(['partner', 'user', 'cuenta', 'attachments'])
            ->whereNull('deleted_at');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('descripcion', 'like', "%{$search}%")
                  ->orWhere('monto', 'like', "%{$search}%")
                  ->orWhereHas('partner', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('cuenta', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $filter = $request->input('filter', 'all');
        if ($filter === 'today') {
            $query->whereDate('fecha_aporte', $today);
        } elseif ($filter === 'month') {
            $query->whereYear('fecha_aporte', $now->year)
                  ->whereMonth('fecha_aporte', $now->month);
        }

        $total = (clone $query)->count();
        $perPage = max(1, min((int) $request->input('per_page', 25), 100));
        $page = max(1, (int) $request->input('page', 1));
        $lastPage = max(1, (int) ceil($total / $perPage));

        $aportes = $query->orderBy('fecha_aporte', 'desc')
            ->orderBy('hora_aporte', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $hasMore = $page < $lastPage;

        // Formatear datos
        $aportesFormateados = $aportes->map(function ($aporte) {
            $attachmentsList = $aporte->attachments;
            if ($attachmentsList->isEmpty()) {
                $movement = \App\Models\Finance\FinancialMovement::where('movable_type', get_class($aporte))
                    ->where('movable_id', $aporte->id)
                    ->first();
                $finRecordId = $movement ? ($movement->metadata['finance_record_id'] ?? null) : null;

                $attachmentsList = \App\Models\Attachment::where(function($q) use ($aporte, $movement, $finRecordId) {
                    $q->where(function($q2) use ($aporte) {
                        $q2->where('attachable_type', get_class($aporte))
                           ->where('attachable_id', $aporte->id);
                    })->orWhere(function($q2) use ($aporte) {
                        $q2->where('attachable_type', \App\Models\Finance\FinanceRecord::class)
                           ->where('attachable_id', $aporte->id);
                    });

                    if ($movement) {
                        $q->orWhere(function($q2) use ($movement) {
                            $q2->where('attachable_type', \App\Models\Finance\FinancialMovement::class)
                               ->where('attachable_id', $movement->id);
                        });
                    }
                    if ($finRecordId) {
                        $q->orWhere(function($q2) use ($finRecordId) {
                            $q2->where('attachable_type', \App\Models\Finance\FinanceRecord::class)
                               ->where('attachable_id', $finRecordId);
                        });
                    }
                })->get();
            }

            return [
                'id' => $aporte->id,
                'partner_nombre' => $aporte->partner->name ?? 'N/A',
                'partner_id' => $aporte->partner_id,
                'monto' => (float) $aporte->monto,
                'descripcion' => $aporte->descripcion,
                'cuenta' => $aporte->cuenta->name ?? 'N/A',
                'cuenta_id' => $aporte->cuenta_id,
                'metodo_pago' => $aporte->metodo_pago,
                'fecha_aporte' => $aporte->fecha_aporte,
                'hora_aporte' => Carbon::parse($aporte->hora_aporte)->format('H:i'),
                'hora' => Carbon::parse($aporte->hora_aporte)->format('H:i'),
                'user_nombre' => $aporte->user->name ?? 'N/A',
                'attachments' => $attachmentsList->map(function ($att) {
                    return [
                        'id' => $att->id,
                        'file_name' => $att->file_name,
                        'file_path' => $att->file_path,
                        'url' => $att->url,
                        'download_url' => url("api/attachments/{$att->id}/download"),
                        'is_image' => str_starts_with($att->mime_type ?? '', 'image/'),
                        'is_pdf' => ($att->mime_type === 'application/pdf'),
                        'mime_type' => $att->mime_type,
                        'file_size' => $att->file_size,
                    ];
                })->values(),
            ];
        });

        // Agrupar por fecha los aportes de esta página para compatibilidad con respuesta agrupada
        $agrupados = $aportesFormateados->groupBy(function ($aporte) use ($timezone) {
            $fechaFormat = Carbon::parse($aporte['fecha_aporte'])->format('Y-m-d');
            $fecha = Carbon::parse($fechaFormat, $timezone);
            $hoy = Carbon::now($timezone)->format('Y-m-d');
            $ayer = Carbon::now($timezone)->subDay()->format('Y-m-d');

            if ($fechaFormat === $hoy) {
                return 'Hoy';
            } elseif ($fechaFormat === $ayer) {
                return 'Ayer';
            } else {
                return $fecha->locale('es')->translatedFormat('l d F');
            }
        });

        $data = [];
        foreach ($agrupados as $fechaLabel => $aportesDia) {
            $data[] = [
                'fecha' => $aportesDia->first()['fecha_aporte'],
                'label' => $fechaLabel,
                'total_dia' => (float) $aportesDia->sum('monto'),
                'aportes' => $aportesDia->values(),
            ];
        }

        return response()->json([
            'resumen' => [
                'total_hoy' => (float) $totalHoy,
                'total_mes' => (float) $totalMes,
                'total_general' => (float) $totalGeneral,
            ],
            'data' => $data,
            'items' => $aportesFormateados->values(),
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
                'has_more' => $hasMore,
            ],
        ]);
    }

    /**
     * Crear aporte con impacto contable
     */
    public function store(Request $request)
    {
        $request->validate([
            'partner_id' => 'required|exists:partners,id',
            'monto' => 'required|numeric|min:0.01',
            'descripcion' => 'required|string|max:255',
            'cuenta_id' => 'required|exists:accounts,id',
            'metodo_pago' => 'required|in:EFECTIVO,TRANSFERENCIA',
            'fecha_aporte' => 'required|date|before_or_equal:today',
            'hora_aporte' => 'required',
        ]);

        try {
            DB::beginTransaction();

            // Crear aporte
            $aporte = AporteCapital::create([
                'partner_id' => $request->partner_id,
                'user_id' => auth()->id(),
                'monto' => $request->monto,
                'descripcion' => $request->descripcion,
                'cuenta_id' => $request->cuenta_id,
                'metodo_pago' => $request->metodo_pago,
                'fecha_aporte' => $request->fecha_aporte,
                'hora_aporte' => $request->hora_aporte,
            ]);

            // Crear movimiento contable
            MovimientoCuenta::create([
                'cuenta_id' => $request->cuenta_id,
                'tipo' => 'INGRESO',
                'monto' => $request->monto,
                'descripcion' => "Aporte de capital: {$request->descripcion}",
                'referencia' => 'aporte_capital',
                'referencia_id' => $aporte->id,
                'fecha' => $request->fecha_aporte,
            ]);

            $partnerName = $aporte->partner ? ($aporte->partner->name ?? $aporte->partner->nombre_completo) : 'SOCIO';

            $aporte->registerMovement(
                $request->cuenta_id,
                'income',
                $request->monto,
                "Aporte de socio: {$partnerName} - {$request->descripcion}",
                $request->fecha_aporte,
                [
                    'metodo' => $request->metodo_pago,
                    'aporte_id' => $aporte->id,
                    'partner_id' => $aporte->partner_id,
                    'partner_name' => $partnerName,
                ]
            );

            // Actualizar saldo de la cuenta
            $cuenta = Account::findOrFail($request->cuenta_id);
            $cuenta->increment('saldo_actual', $request->monto);

            // Adjuntar comprobantes si fueron enviados en el request
            if ($request->hasFile('receipts')) {
                try {
                    $storageService = app(\App\Services\InvoiceStorageService::class);
                    $identifier = "APORTE-" . str_pad($aporte->id, 5, '0', STR_PAD_LEFT);
                    $storageService->attachReceiptsToModel(
                        $aporte,
                        $identifier,
                        $partnerName,
                        $request->file('receipts'),
                        Carbon::parse($request->fecha_aporte),
                        'receipt',
                        'ingresos'
                    );
                } catch (\Exception $e) {
                    \Log::warning('Error al adjuntar comprobantes a Aporte:', ['error' => $e->getMessage()]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Aporte creado exitosamente',
                'aporte' => $aporte->load(['partner', 'user', 'cuenta', 'attachments']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al crear aporte',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Soft delete con reverso contable
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            // 1. Buscamos el aporte
            $aporte = AporteCapital::findOrFail($id);

            // 2. Crear movimiento inverso en MovimientoCuenta (USAR DATOS ANTES DE BORRAR)
            MovimientoCuenta::create([
                'cuenta_id' => $aporte->cuenta_id,
                'tipo' => 'EGRESO',
                'monto' => $aporte->monto,
                'descripcion' => "Reverso de aporte: {$aporte->descripcion}",
                'referencia' => 'reverso_aporte',
                'referencia_id' => $aporte->id,
                'fecha' => now()->toDateString(),
            ]);

            // 3. Restar monto del saldo de la cuenta
            $cuenta = Account::findOrFail($aporte->cuenta_id);
            $cuenta->decrement('saldo_actual', $aporte->monto);

            // 4. ELIMINAR DEL DASHBOARD DE OPERACIONES
            // Usamos la relación del Trait para quitarlo de la vista unificada
            if ($aporte->financialMovement()) {
                $aporte->financialMovement()->delete();
            }

            // 5. Soft delete del aporte (AL FINAL)
            // Lo hacemos al final para asegurar que todas las referencias anteriores funcionen
            $aporte->delete();

            DB::commit();

            return response()->json([
                'message' => 'Aporte eliminado exitosamente',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al eliminar aporte',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar aporte con recalculo de saldos
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'partner_id' => 'required|exists:partners,id',
            'monto' => 'required|numeric|min:0.01',
            'descripcion' => 'required|string|max:255',
            'cuenta_id' => 'required|exists:accounts,id',
            'metodo_pago' => 'required|in:EFECTIVO,TRANSFERENCIA',
            'fecha_aporte' => 'required|date|before_or_equal:today',
            'hora_aporte' => 'required|date_format:H:i',
        ]);

        try {
            DB::beginTransaction();

            $aporte = AporteCapital::findOrFail($id);
            $cuentaAnterior = $aporte->cuenta_id;
            $montoAnterior = $aporte->monto;

            // Si cambió la cuenta o el monto, revertir el movimiento anterior
            if ($cuentaAnterior != $request->cuenta_id || $montoAnterior != $request->monto) {
                // Revertir movimiento anterior
                MovimientoCuenta::create([
                    'cuenta_id' => $cuentaAnterior,
                    'tipo' => 'EGRESO',
                    'monto' => $montoAnterior,
                    'descripcion' => "Reverso por edición de aporte: {$aporte->descripcion}",
                    'referencia' => 'reverso_edicion_aporte',
                    'referencia_id' => $aporte->id,
                    'fecha' => now()->toDateString(),
                ]);

                // Restar saldo anterior
                $cuentaAnt = Account::findOrFail($cuentaAnterior);
                $cuentaAnt->decrement('saldo_actual', $montoAnterior);

                // Aplicar nuevo movimiento
                MovimientoCuenta::create([
                    'cuenta_id' => $request->cuenta_id,
                    'tipo' => 'INGRESO',
                    'monto' => $request->monto,
                    'descripcion' => "Aporte de capital: {$request->descripcion}",
                    'referencia' => 'aporte_capital',
                    'referencia_id' => $aporte->id,
                    'fecha' => $request->fecha_aporte,
                ]);

                // Sumar nuevo saldo
                $cuentaNueva = Account::findOrFail($request->cuenta_id);
                $cuentaNueva->increment('saldo_actual', $request->monto);
            }

            // Actualizar datos del aporte
            $aporte->update([
                'partner_id' => $request->partner_id,
                'monto' => $request->monto,
                'descripcion' => $request->descripcion,
                'cuenta_id' => $request->cuenta_id,
                'metodo_pago' => $request->metodo_pago,
                'fecha_aporte' => $request->fecha_aporte,
                'hora_aporte' => $request->hora_aporte,
            ]);

            // Recargar relación del socio para asegurar datos frescos
            $aporte->load('partner');

            // Actualizar el movimiento financiero existente (in-place) sin duplicar filas
            $partnerName = $aporte->partner ? ($aporte->partner->name ?? $aporte->partner->nombre_completo) : 'SOCIO';
            $aporte->updateMovement(
                $request->cuenta_id,
                'income',
                $request->monto,
                "Aporte de socio: {$partnerName} - {$request->descripcion}",
                $request->fecha_aporte,
                [
                    'metodo' => $request->metodo_pago,
                    'aporte_id' => $aporte->id,
                    'partner_id' => $aporte->partner_id,
                    'partner_name' => $partnerName,
                ]
            );

            // Adjuntar comprobantes si fueron enviados en el request
            if ($request->hasFile('receipts')) {
                try {
                    $partnerName = $aporte->partner ? ($aporte->partner->name ?? $aporte->partner->nombre_completo) : 'SOCIO';
                    $storageService = app(\App\Services\InvoiceStorageService::class);
                    $identifier = "APORTE-" . str_pad($aporte->id, 5, '0', STR_PAD_LEFT);
                    $storageService->attachReceiptsToModel(
                        $aporte,
                        $identifier,
                        $partnerName,
                        $request->file('receipts'),
                        \Carbon\Carbon::parse($request->fecha_aporte),
                        'receipt',
                        'ingresos'
                    );
                } catch (\Exception $e) {
                    \Log::warning('Error al adjuntar comprobantes a Aporte en edición:', ['error' => $e->getMessage()]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Aporte actualizado exitosamente',
                'aporte' => $aporte->load(['partner', 'user', 'cuenta', 'attachments']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al actualizar aporte',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}