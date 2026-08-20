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
        // 1. Agregar tipo de uso a la tabla de vehículos
        if (Schema::hasTable('vehicles') && !Schema::hasColumn('vehicles', 'usage_type')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->string('usage_type', 50)->default('particular')->after('vehicle_type')->comment('particular, taxi, comercial, pesado');
            });
        }

        // 2. Crear tabla de recordatorios y eventos de mantenimiento preventivo
        if (!Schema::hasTable('maintenance_reminders')) {
            Schema::create('maintenance_reminders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
                $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();

                // Categoría de servicio e intervalo
                $table->string('service_category', 50)->default('general')->comment('aceite, frenos, inyectores, amortiguadores, distribucion, alineacion, general');
                $table->integer('interval_km')->default(10000);

                // Proyección y datos del servicio
                $table->integer('last_service_mileage');
                $table->integer('target_mileage');
                $table->decimal('avg_daily_km', 8, 2)->default(35.00);
                $table->date('last_service_date');
                $table->date('scheduled_date');

                // Títulos, descripción y estado
                $table->string('title');
                $table->text('description')->nullable();
                $table->enum('status', ['pending', 'notified', 'scheduled', 'completed', 'cancelled'])->default('pending');

                // Notificación
                $table->timestamp('notified_at')->nullable();
                $table->string('notification_channel', 50)->nullable()->comment('whatsapp, email, both');

                $table->timestamps();
                $table->softDeletes();

                $table->index(['scheduled_date', 'status']);
                $table->index(['vehicle_id', 'service_category']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_reminders');

        if (Schema::hasTable('vehicles') && Schema::hasColumn('vehicles', 'usage_type')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->dropColumn('usage_type');
            });
        }
    }
};
