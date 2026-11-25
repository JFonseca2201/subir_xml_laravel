<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // 1. Agregar tipo de documento
            if (!Schema::hasColumn('invoices', 'document_type')) {
                $table->string('document_type', 50)->default('invoice')->after('supplier_id');
            }

            // 2. Modificar invoice_number para que sea nullable
            $table->string('invoice_number', 100)->nullable()->change();
        });

        // 3. Eliminar índice unique global si existe, y crear índice compuesto (supplier_id, invoice_number)
        try {
            DB::statement('ALTER TABLE invoices DROP INDEX invoices_invoice_number_unique');
        } catch (\Throwable $e) {
            // El índice podría no existir o tener otro nombre
        }

        try {
            Schema::table('invoices', function (Blueprint $table) {
                $table->index(['supplier_id', 'invoice_number'], 'invoices_supplier_invoice_idx');
            });
        } catch (\Throwable $e) {
            // Ignorar si ya existe
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'document_type')) {
                $table->dropColumn('document_type');
            }
        });
    }
};
