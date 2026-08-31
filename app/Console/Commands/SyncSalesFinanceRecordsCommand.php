<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Sales\Sale;
use App\Models\Finance\FinanceRecord;
use App\Services\Sales\SaleFinanceService;

class SyncSalesFinanceRecordsCommand extends Command
{
    protected $signature = 'sales:sync-finance {--force : Re-sincronizar incluso si ya tienen registro financiero}';

    protected $description = 'Sincroniza registros financieros y movimientos de caja para ventas y facturas existentes';

    public function handle(SaleFinanceService $financeService): int
    {
        $force = $this->option('force');
        $this->info('Iniciando sincronización de registros financieros para ventas y facturas...');

        $query = Sale::where('document_type', '!=', 'quote')
            ->whereNotIn('status', ['canceled', 'draft'])
            ->where('payment_status', '!=', 'pending');

        $sales = $query->get();
        $syncedCount = 0;
        $skippedCount = 0;

        foreach ($sales as $sale) {
            $existingRecord = FinanceRecord::where('invoice_number', $sale->document_number)->first();

            if ($existingRecord && !$force) {
                $skippedCount++;
                continue;
            }

            try {
                $financeService->processFinancialRecord($sale, [
                    'payment_method' => $sale->payment_method ?? 'Efectivo',
                ], $sale->user_id ?? 1);

                $this->info("✓ Sincronizado: [{$sale->document_type}] #{$sale->document_number} - Total: \${$sale->total}");
                $syncedCount++;
            } catch (\Throwable $e) {
                $this->error("✗ Error en [{$sale->document_type}] #{$sale->document_number}: " . $e->getMessage());
            }
        }

        $this->info("Proceso finalizado. Sincronizados: {$syncedCount}, Omitidos (ya existentes): {$skippedCount}");

        return Command::SUCCESS;
    }
}
