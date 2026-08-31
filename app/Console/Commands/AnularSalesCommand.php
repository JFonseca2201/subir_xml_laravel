<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Sales\Sale;
use App\Services\Sales\SaleUpdateService;

class AnularSalesCommand extends Command
{
    protected $signature = 'sales:anular {documents* : Números de documento o IDs de ventas a anular}';

    protected $description = 'Anula ventas o facturas en el sistema revirtiendo stock, finanzas y liberando OTs';

    public function handle(SaleUpdateService $updateService): int
    {
        $documents = $this->argument('documents');

        foreach ($documents as $doc) {
            $sale = Sale::where('id', $doc)
                ->orWhere('document_number', $doc)
                ->first();

            if (!$sale) {
                $this->error("No se encontró la venta con ID/Doc: {$doc}");
                continue;
            }

            $res = $updateService->deleteSale($sale);

            if ($res['status'] === 200) {
                $this->info("✓ Venta {$sale->document_number} (ID: {$sale->id}) anulada y soft-deleted exitosamente.");
            } else {
                $this->warn("! Venta {$sale->document_number}: " . ($res['data']['message'] ?? 'Error'));
            }
        }

        return Command::SUCCESS;
    }
}
