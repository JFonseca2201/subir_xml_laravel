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
    Schema::table('employee_payments', function (Blueprint $table) {
        // 1. Eliminar columna vieja (solo si existe)
        if (Schema::hasColumn('employee_payments', 'concept')) {
            $table->dropColumn('concept');
        }
        
        // 2. Agregar employee_id si no existe
        if (!Schema::hasColumn('employee_payments', 'employee_id')) {
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade')->after('id');
            $table->index('employee_id');
        }

        // 3. Agregar account_id si no existe
        if (!Schema::hasColumn('employee_payments', 'account_id')) {
            $table->foreignId('account_id')->constrained('accounts')->onDelete('cascade')->after('employee_id');
            $table->index('account_id');
        }

        // 4. Agregar description si no existe
        if (!Schema::hasColumn('employee_payments', 'description')) {
            $table->text('description')->nullable()->after('amount');
        }

        // Puedes hacer lo mismo para payment_method, reference, type y created_by...

        // 5. Modificar la columna amount
        $table->decimal('amount', 10, 2)->change();
        
        // 6. Índices adicionales (ejemplo para el compuesto, previniendo que ya exista)
        // Nota: Para verificar índices existentes de forma segura en modificaciones complejas,
        // a veces es más limpio el comando migrate:fresh si estás en desarrollo.
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_payments', function (Blueprint $table) {
            // Drop new columns
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['account_id']);
            $table->dropForeign(['created_by']);
            $table->dropColumn(['employee_id', 'account_id', 'description', 'payment_method', 'reference', 'type', 'created_by']);
            $table->dropSoftDeletes();
            
            // Add old columns back
            $table->string('employee_name')->after('id');
            $table->string('concept')->nullable()->after('amount');
            
            // Revert amount column
            $table->decimal('amount', 15, 2)->change();
            
            // Drop old index and add new one
            $table->dropIndex(['payment_date', 'type']);
            $table->dropIndex(['payment_date']);
            $table->dropIndex(['type']);
            $table->dropIndex(['account_id']);
            $table->dropIndex(['employee_id']);
            $table->index(['payment_date', 'employee_name']);
        });
    }
};