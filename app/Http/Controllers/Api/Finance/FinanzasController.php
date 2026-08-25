<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\FinancialMovement;
use App\Models\Finance\Account;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Config\Sucursale;

class FinanzasController extends Controller
{
    //
    public function getDashboardData()
    {
        // 1. Obtener movimientos recientes con sus relaciones
        $movements = FinancialMovement::with(['movable'])
            ->orderBy('entry_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->take(15)
            ->get();

        // 2. Calcular resumen del mes actual
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $summary = [
            'monthlyIncome' => (float) FinancialMovement::where('type', 'income')
                ->whereBetween('entry_date', [$startOfMonth, $endOfMonth])
                ->sum('amount'),

            'monthlyExpense' => (float) FinancialMovement::where('type', 'expense')
                ->whereBetween('entry_date', [$startOfMonth, $endOfMonth])
                ->sum('amount'),

            // El balance actual se puede calcular restando totales o sumando todo
            'currentBalance' => (float) FinancialMovement::where('type', 'income')->sum('amount') -
                FinancialMovement::where('type', 'expense')->sum('amount')
        ];

        return response()->json([
            'movements' => $movements,
            'summary' => $summary
        ]);
    }

    public function generatePDF(Request $request)
    {
        try {
            $query = FinancialMovement::with(['movable', 'account'])
                ->orderBy('entry_date', 'desc')
                ->orderBy('created_at', 'desc');

            // Filtrar por tipo (income, expense, transfer)
            if ($request->has('type') && !empty($request->type)) {
                $query->where('type', $request->type);
            }

            // Filtrar por mes (formato Y-m, ej. '2026-07')
            if ($request->has('month') && !empty($request->month)) {
                $month = $request->month;
                $query->whereYear('entry_date', substr($month, 0, 4))
                      ->whereMonth('entry_date', substr($month, 5, 2));
            }

            // Búsqueda (OT, Factura, Descripción)
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                      ->orWhere('metadata->invoice', 'like', "%{$search}%")
                      ->orWhere('metadata->work_order', 'like', "%{$search}%")
                      ->orWhere('metadata->invoice_number', 'like', "%{$search}%")
                      ->orWhere('metadata->work_order_number', 'like', "%{$search}%")
                      ->orWhereHasMorph('movable', [\App\Models\Finance\PaymentDistribution::class], function ($mQuery) use ($search) {
                          $mQuery->whereHas('financeRecord', function ($frQuery) use ($search) {
                              $frQuery->where('work_order_number', 'like', "%{$search}%")
                                      ->orWhere('invoice_number', 'like', "%{$search}%")
                                      ->orWhere('description', 'like', "%{$search}%");
                          });
                      })
                      ->orWhereHasMorph('movable', [\App\Models\Finance\FinanceRecord::class], function ($mQuery) use ($search) {
                          $mQuery->where('work_order_number', 'like', "%{$search}%")
                                 ->orWhere('invoice_number', 'like', "%{$search}%")
                                 ->orWhere('description', 'like', "%{$search}%");
                      });
                });
            }

            // Rango de fechas
            if ($request->has('start_date') && !empty($request->start_date)) {
                $query->whereDate('entry_date', '>=', $request->start_date);
            }
            if ($request->has('end_date') && !empty($request->end_date)) {
                $query->whereDate('entry_date', '<=', $request->end_date);
            }

            $movements = $query->get();
            $movements->loadMorph('movable', [
                \App\Models\Finance\PaymentDistribution::class => ['financeRecord']
            ]);

            Log::info('Total movements for PDF: ' . $movements->count());

            // Cargar nombres de cuentas para transferencias
            $allAccounts = Account::all()->keyBy('id');
            Log::info('Available accounts: ' . json_encode($allAccounts->pluck('name', 'id')));

            foreach ($movements as $movement) {
                try {
                    if ($movement->type === 'transfer' && is_array($movement->metadata)) {
                        Log::info('Processing transfer movement ID: ' . $movement->id . ', metadata: ' . json_encode($movement->metadata));

                        $fromAccountId = $movement->metadata['from_account'] ?? null;
                        $toAccountId = $movement->metadata['to_account'] ?? null;

                        Log::info('Looking for from_account_id: ' . $fromAccountId . ', to_account_id: ' . $toAccountId);
                        Log::info('from_account_id exists in array: ' . (isset($allAccounts[$fromAccountId]) ? 'YES' : 'NO'));
                        Log::info('to_account_id exists in array: ' . (isset($allAccounts[$toAccountId]) ? 'YES' : 'NO'));

                        // Obtener metadata como array, modificarlo y asignarlo de vuelta
                        $metadata = $movement->metadata;

                        if ($fromAccountId && isset($allAccounts[$fromAccountId])) {
                            $metadata['from_account_name'] = $allAccounts[$fromAccountId]->name;
                            Log::info('Set from_account_name to: ' . $allAccounts[$fromAccountId]->name);
                        } else {
                            Log::warning('from_account_id ' . $fromAccountId . ' not found in accounts array');
                        }

                        if ($toAccountId && isset($allAccounts[$toAccountId])) {
                            $metadata['to_account_name'] = $allAccounts[$toAccountId]->name;
                            Log::info('Set to_account_name to: ' . $allAccounts[$toAccountId]->name);
                        } else {
                            Log::warning('to_account_id ' . $toAccountId . ' not found in accounts array');
                        }

                        $movement->metadata = $metadata;
                        Log::info('Updated metadata: ' . json_encode($movement->metadata));
                    }
                } catch (\Exception $e) {
                    // Continuar con el siguiente movimiento si hay error
                    Log::warning('Error loading account names for movement: ' . $e->getMessage());
                }
            }

            // Calcular resumen general de todos los movimientos
            $summary = [
                'totalIncome' => (float) $movements->where('type', 'income')->sum('amount'),
                'totalExpense' => (float) $movements->where('type', 'expense')->sum('amount'),
                'balance' => (float) $movements->where('type', 'income')->sum('amount') -
                    $movements->where('type', 'expense')->sum('amount'),
                'totalCount' => $movements->count()
            ];

            // Generar un solo PDF con todos los movimientos
            $pdf = Pdf::loadView('movimientos.pdf', [
                'movements' => $movements,
                'summary' => $summary
            ]);

            return $pdf->download('reporte_financiero_' . date('Y-m-d') . '.pdf');
        } catch (\Exception $e) {
            Log::error('Error generating PDF: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function generateSinglePDF(int $id)
    {
        try {
            $movement = \App\Models\Finance\FinancialMovement::with(['movable', 'account'])->findOrFail($id);

            // Determinar tipo como string
            $movementType = $movement->type; // 'income', 'expense', 'transfer'
            $movement->type_string = $movementType;

            // Determinar nombre de la cuenta o cuentas
            $accountName = 'N/A';
            if ($movementType === 'transfer' && is_array($movement->metadata)) {
                $fromAcc = $movement->metadata['from_account_name'] ?? 'N/A';
                $toAcc = $movement->metadata['to_account_name'] ?? 'N/A';
                $accountName = "{$fromAcc} → {$toAcc}";
            } else if ($movement->account) {
                $accountName = $movement->account->name;
            } else if ($movement->movable_type === \App\Models\Finance\PaymentDistribution::class) {
                $movement->load('movable.financeRecord.paymentDistributions.account');
                $financeRecord = $movement->movable->financeRecord ?? null;
                if ($financeRecord && $financeRecord->paymentDistributions && $financeRecord->paymentDistributions->count() > 0) {
                    $accountName = $financeRecord->paymentDistributions->map(function ($pd) {
                        return $pd->account ? $pd->account->name : 'N/A';
                    })->implode(', ');
                }
            }

            // Mapear referencias para compatibilidad con la vista
            $movement->work_order_number = null;
            $movement->invoice_number = null;

            if (is_array($movement->metadata)) {
                $movement->work_order_number = $movement->metadata['work_order_number'] ?? null;
                $movement->invoice_number = $movement->metadata['invoice_number'] ?? null;
            }

            if (!$movement->work_order_number && !$movement->invoice_number && $movement->movable) {
                $movement->work_order_number = $movement->movable->work_order_number ?? null;
                $movement->invoice_number = $movement->movable->invoice_number ?? null;
            }

            // Preparar el logo
            $sucursal = Sucursale::query()->first();
            $logoBase64 = '';
            $logoPath = null;
            if ($sucursal && $sucursal->logo) {
                $tempPath = public_path($sucursal->logo);
                if (file_exists($tempPath)) {
                    $logoPath = $tempPath;
                } else {
                    $cleanLogo = str_replace('storage/', '', $sucursal->logo);
                    $tempPath = storage_path('app/public/' . $cleanLogo);
                    if (file_exists($tempPath)) {
                        $logoPath = $tempPath;
                    }
                }
            }
            if (!$logoPath || !file_exists($logoPath)) {
                $logoPath = public_path('assets/img/brand/logo.jpeg');
            }
            if (file_exists($logoPath)) {
                $logoData = file_get_contents($logoPath);
                $logoMime = 'image/jpeg';
                $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
                if ($ext === 'png') $logoMime = 'image/png';
                elseif ($ext === 'gif') $logoMime = 'image/gif';
                elseif ($ext === 'svg') $logoMime = 'image/svg+xml';
                $logoBase64 = 'data:' . $logoMime . ';base64,' . base64_encode($logoData);
            }

            $pdf = Pdf::loadView('movimientos.single_pdf', [
                'movement' => $movement,
                'type_string' => $movementType,
                'account_name' => $accountName,
                'logoBase64' => $logoBase64
            ]);

            return $pdf->download('comprobante_' . $movementType . '_' . $id . '.pdf');
        } catch (\Exception $e) {
            Log::error('Error generating single movement PDF: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getMovements(Request $request)
    {
        try {
            $query = FinancialMovement::with(['account'])
                ->orderBy('entry_date', 'desc')
                ->orderBy('created_at', 'desc');

            // Filtrar por tipo (income, expense, transfer)
            if ($request->has('type') && !empty($request->type)) {
                $query->where('type', $request->type);
            }

            // Filtrar por mes (formato Y-m, ej. '2026-07')
            if ($request->has('month') && !empty($request->month)) {
                $month = $request->month;
                $query->whereYear('entry_date', substr($month, 0, 4))
                      ->whereMonth('entry_date', substr($month, 5, 2));
            }

            // Búsqueda (OT, Factura, Descripción)
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                      ->orWhere('metadata->invoice', 'like', "%{$search}%")
                      ->orWhere('metadata->work_order', 'like', "%{$search}%")
                      ->orWhere('metadata->invoice_number', 'like', "%{$search}%")
                      ->orWhere('metadata->work_order_number', 'like', "%{$search}%")
                      ->orWhereHasMorph('movable', [\App\Models\Finance\PaymentDistribution::class], function ($mQuery) use ($search) {
                          $mQuery->whereHas('financeRecord', function ($frQuery) use ($search) {
                              $frQuery->where('work_order_number', 'like', "%{$search}%")
                                      ->orWhere('invoice_number', 'like', "%{$search}%")
                                      ->orWhere('description', 'like', "%{$search}%");
                          });
                      })
                      ->orWhereHasMorph('movable', [\App\Models\Finance\FinanceRecord::class], function ($mQuery) use ($search) {
                          $mQuery->where('work_order_number', 'like', "%{$search}%")
                                 ->orWhere('invoice_number', 'like', "%{$search}%")
                                 ->orWhere('description', 'like', "%{$search}%");
                      });
                });
            }

            // Rango de fechas
            if ($request->has('start_date') && !empty($request->start_date)) {
                $query->whereDate('entry_date', '>=', $request->start_date);
            }
            if ($request->has('end_date') && !empty($request->end_date)) {
                $query->whereDate('entry_date', '<=', $request->end_date);
            }

            $movements = $query->get();
            $movements->load(['movable', 'attachments']);
            $movements->loadMorph('movable', [
                \App\Models\Finance\PaymentDistribution::class => ['financeRecord.attachments'],
                \App\Models\Finance\FinanceRecord::class => ['attachments'],
                \App\Models\Finance\InternalTransfer::class => ['attachments'],
                \App\Models\Sales\Sale::class => ['attachments'],
                \App\Models\WorkOrder\WorkOrder::class => ['attachments'],
                \App\Models\Employee\EmployeePayment::class => ['attachments'],
                \App\Models\Employee\EmployeeAdvance::class => ['attachments'],
                \App\Models\Partner\AporteCapital::class => ['attachments'],
            ]);

            // Cargar nombres de cuentas para transferencias y resolver attachments normalizados
            $allAccounts = Account::all()->keyBy('id');
            foreach ($movements as $movement) {
                try {
                    if ($movement->type === 'transfer' && is_array($movement->metadata)) {
                        $fromAccountId = $movement->metadata['from_account'] ?? null;
                        $toAccountId = $movement->metadata['to_account'] ?? null;

                        $metadata = $movement->metadata;

                        if ($fromAccountId && isset($allAccounts[$fromAccountId])) {
                            $metadata['from_account_name'] = $allAccounts[$fromAccountId]->name;
                        }

                        if ($toAccountId && isset($allAccounts[$toAccountId])) {
                            $metadata['to_account_name'] = $allAccounts[$toAccountId]->name;
                        }

                        $movement->metadata = $metadata;
                    }

                    // Resolver lista de attachments consolidados
                    $rawAttachments = collect();
                    if ($movement->attachments && $movement->attachments->count() > 0) {
                        $rawAttachments = $rawAttachments->concat($movement->attachments);
                    }
                    if ($movement->movable) {
                        if (isset($movement->movable->attachments) && $movement->movable->attachments->count() > 0) {
                            $rawAttachments = $rawAttachments->concat($movement->movable->attachments);
                        }
                        if (isset($movement->movable->financeRecord->attachments) && $movement->movable->financeRecord->attachments->count() > 0) {
                            $rawAttachments = $rawAttachments->concat($movement->movable->financeRecord->attachments);
                        }
                    }

                    // Si está vinculado a FinanceRecord y aún no se cargaron adjuntos
                    $finRecordId = $movement->metadata['finance_record_id'] ?? null;
                    if (!$finRecordId && $movement->movable_type === \App\Models\Finance\PaymentDistribution::class) {
                        $finRecordId = $movement->movable?->finance_record_id;
                    }
                    if ($finRecordId && $rawAttachments->isEmpty()) {
                        $frAtts = \App\Models\Attachment::where('attachable_id', $finRecordId)
                            ->whereIn('attachable_type', [
                                \App\Models\Finance\FinanceRecord::class,
                                'App\Models\FinanceRecord',
                                'finance_record',
                                'expense'
                            ])->get();
                        $rawAttachments = $rawAttachments->concat($frAtts);
                    }

                    $movement->resolved_attachments = $rawAttachments->unique('id')->map(function ($att) {
                        $cleanPath = ltrim(str_replace('storage/', '', $att->file_path), '/');
                        $urlPath = implode('/', array_map('rawurlencode', explode('/', $cleanPath)));
                        $scheme = request()->getScheme();
                        $host = request()->getHttpHost();
                        $fullUrl = "{$scheme}://{$host}/storage/{$urlPath}";

                        return [
                            'id' => $att->id,
                            'file_name' => $att->file_name,
                            'original_name' => $att->original_name,
                            'file_path' => $att->file_path,
                            'url' => $fullUrl,
                            'download_url' => url("api/attachments/{$att->id}/download"),
                            'is_image' => str_starts_with($att->mime_type ?? '', 'image/'),
                            'is_pdf' => ($att->mime_type === 'application/pdf'),
                            'mime_type' => $att->mime_type,
                            'file_size' => $att->file_size,
                            'type' => $att->type,
                        ];
                    })->values();
                } catch (\Exception $e) {
                    // Ignorar errores al cargar
                }
            }

            // Calcular totales/resumen dinámicos
            $totalIncome = (float) $movements->where('type', 'income')->sum('amount');
            $totalExpense = (float) $movements->where('type', 'expense')->sum('amount');
            $totalTransfer = (float) $movements->where('type', 'transfer')->sum('amount');

            return response()->json([
                'movements' => $movements,
                'totals' => [
                    'income' => $totalIncome,
                    'expense' => $totalExpense,
                    'transfer' => $totalTransfer,
                    'balance' => $totalIncome - $totalExpense
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error loading movements: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
