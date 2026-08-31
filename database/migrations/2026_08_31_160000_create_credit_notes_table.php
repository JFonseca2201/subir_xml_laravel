<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('credit_notes')) {
            Schema::create('credit_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sale_id')->constrained('sales')->onDelete('cascade');
                $table->string('document_number', 50)->unique();
                $table->string('sri_access_key', 49)->nullable()->unique();
                $table->string('sri_status', 30)->default('CREADA');
                $table->timestamp('sri_authorization_date')->nullable();
                $table->text('sri_error')->nullable();

                // Montos
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('tax_amount', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);

                // Tarifa y motivo
                $table->decimal('subtotal_iva_15', 12, 2)->default(0);
                $table->decimal('subtotal_iva_0', 12, 2)->default(0);
                $table->string('reason', 255)->default('ANULACIÓN DE FACTURA');
                $table->boolean('restore_stock')->default(true);
                $table->boolean('reverse_balance')->default(true);

                // Rutas de archivos
                $table->string('xml_path')->nullable();
                $table->string('pdf_path')->nullable();

                $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
