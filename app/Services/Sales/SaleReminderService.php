<?php

namespace App\Services\Sales;

use App\Models\Sales\Sale;
use App\Models\Sales\SaleDetail;
use App\Models\Sales\RepuestosReposicion;
use Illuminate\Support\Facades\Log;
use Exception;

class SaleReminderService
{
    /**
     * Sincronizar y generar reposiciones para la venta dada si califica.
     */
    public function syncReplacementReminders(Sale $sale): void
    {
        try {
            if (!$sale || $sale->document_type === 'quote' || $sale->status === 'draft' || $sale->status === 'canceled') {
                return;
            }

            // Cargar relaciones si no están cargadas
            $sale->loadMissing(['details.product']);

            $keywords = ['amortiguador', 'pastilla', 'freno', 'aceite', 'filtro', 'aire', 'acondicionado'];

            foreach ($sale->details as $detail) {
                $product = $detail->product;
                if (!$product || $product->item_type != 1) {
                    continue;
                }

                $desc = mb_strtolower($detail->description, 'UTF-8');
                $matches = false;
                foreach ($keywords as $kw) {
                    if (str_contains($desc, $kw)) {
                        $matches = true;
                        break;
                    }
                }

                if ($matches) {
                    // Verificar si ya existe una reposición para esta venta y producto
                    $exists = RepuestosReposicion::where('sale_id', $sale->id)
                        ->where('product_id', $product->id)
                        ->exists();

                    if (!$exists) {
                        RepuestosReposicion::create([
                            'product_id' => $product->id,
                            'sku' => $product->sku ?? $product->code_aux ?? $product->code ?? null,
                            'description' => $product->description ?? $product->name ?? $detail->description,
                            'quantity' => $detail->quantity,
                            'purchase_price' => $product->purchase_price ?? 0.00,
                            'supplier_id' => $product->supplier_id,
                            'status' => 'pending',
                            'sale_id' => $sale->id
                        ]);
                    }
                }
            }
        } catch (Exception $e) {
            Log::error('Error en syncReplacementReminders: ' . $e->getMessage());
        }
    }

    /**
     * Obtener el historial de repuestos vendidos (amortiguadores, pastillas, aceites, filtros, AC).
     */
    public function getRepuestosHistorial(): array
    {
        $keywords = ['amortiguador', 'pastilla', 'freno', 'aceite', 'filtro', 'aire', 'acondicionado'];

        $query = SaleDetail::query()
            ->with(['sale.client', 'sale.vehicle', 'product.categorie'])
            ->whereHas('sale', function ($q) {
                $q->where('status', '!=', 'canceled')
                  ->where('document_type', '!=', 'quote');
            })
            ->whereHas('product', function ($q) {
                $q->where('item_type', 1); // Solo productos físicos (excluir servicios)
            })
            ->where(function ($q) use ($keywords) {
                foreach ($keywords as $kw) {
                    $q->orWhere('description', 'like', "%{$kw}%");
                }
            });

        $details = $query->orderBy('id', 'desc')->get();
        $vehicleBrands = config('vehicle_brands', []);

        return $details->map(function ($detail) use ($vehicleBrands) {
            $sale = $detail->sale;
            $client = $sale ? $sale->client : null;
            $vehicle = $sale ? $sale->vehicle : null;
            
            $category = ($detail->product && $detail->product->categorie) 
                ? $detail->product->categorie->title 
                : 'Otros Repuestos';
            $nextSuggestion = 'Según manual';

            // Formatear marca
            $brandName = '';
            if ($vehicle && isset($vehicle->brand)) {
                $brandId = $vehicle->brand;
                $brandName = $vehicleBrands[$brandId] ?? $brandId;
            }

            return [
                'id' => $detail->id,
                'sale_id' => $detail->sale_id,
                'fecha' => $sale ? $sale->service_date : null,
                'comprobante' => $sale ? $sale->document_number : 'N/A',
                'cliente' => $client ? $client->full_name : 'Consumidor Final',
                'cliente_dni' => $client ? $client->n_document : 'N/A',
                'vehiculo_placa' => $vehicle ? $vehicle->license_plate : 'N/A',
                'vehiculo_modelo' => $vehicle ? trim(($brandName . ' ' . $vehicle->model)) : 'N/A',
                'kilometraje' => $sale ? $sale->mileage : 0,
                'repuesto' => $detail->description,
                'sku' => $detail->product ? ($detail->product->sku ?? $detail->product->code_aux ?? $detail->product->code) : null,
                'categoria' => $category,
                'cantidad' => $detail->quantity,
                'sugerencia' => $nextSuggestion,
            ];
        })->toArray();
    }
}
