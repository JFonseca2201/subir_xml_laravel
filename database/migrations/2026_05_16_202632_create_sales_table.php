<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            
            // Tipo de Documento: Cotización, Nota de Venta o Factura
            $table->enum('document_type', ['quote', 'sale_note', 'invoice'])->default('sale_note');
            $table->string('document_number')->unique();
            
            // Relaciones de tu sistema (Clientes, Vehículos, Usuarios)
            $table->foreignId('client_id')->constrained('clients')->onDelete('restrict');
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->onDelete('set null');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');

            // 🚗 Campos específicos para el taller Luxury Evys
            $table->integer('mileage')->nullable(); // Kilometraje actual
            $table->date('service_date')->nullable(); // Fecha de atención mecánica

            // Totales Financieros
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('tax_amount', 12, 2)->default(0.00); 
            $table->decimal('total', 12, 2)->default(0.00);
            
            // Escalabilidad para el módulo financiero (Caja, Ingresos y Cuentas por Cobrar)
            $table->enum('status', ['pending', 'completed', 'canceled'])->default('completed');
            $table->enum('payment_status', ['paid', 'partial', 'pending'])->default('paid');
            $table->boolean('is_credited')->default(false); 
            $table->string('payment_method')->default('cash'); // cash, transfer, card, credit
            
            $table->text('observations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};