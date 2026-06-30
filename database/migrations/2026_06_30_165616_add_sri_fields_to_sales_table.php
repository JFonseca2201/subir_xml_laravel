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
        Schema::table('sales', function (Blueprint $table) {
            // Desglose de impuestos para SRI
            $table->decimal('subtotal_iva_15', 12, 2)->default(0.00)->after('subtotal');
            $table->decimal('subtotal_iva_0', 12, 2)->default(0.00)->after('subtotal_iva_15');
            $table->decimal('subtotal_no_objeto', 12, 2)->default(0.00)->after('subtotal_iva_0');
            $table->decimal('subtotal_exento', 12, 2)->default(0.00)->after('subtotal_no_objeto');

            // Campos SRI
            $table->string('sri_access_key', 49)->nullable()->after('observations');
            $table->dateTime('sri_authorization_date')->nullable()->after('sri_access_key');
            $table->enum('sri_status', ['CREADA', 'FIRMADA', 'ENVIADA', 'AUTORIZADA', 'RECHAZADA', 'DEVUELTA'])->nullable()->after('sri_authorization_date');
            $table->text('sri_error')->nullable()->after('sri_status');
            $table->string('xml_path')->nullable()->after('sri_error');
            $table->string('pdf_path')->nullable()->after('xml_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'subtotal_iva_15',
                'subtotal_iva_0',
                'subtotal_no_objeto',
                'subtotal_exento',
                'sri_access_key',
                'sri_authorization_date',
                'sri_status',
                'sri_error',
                'xml_path',
                'pdf_path'
            ]);
        });
    }
};
