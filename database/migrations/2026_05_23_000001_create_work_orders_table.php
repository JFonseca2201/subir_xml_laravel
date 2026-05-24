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
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->onDelete('set null');
            $table->foreignId('user_id')->constrained('users');
            $table->integer('mileage')->nullable();
            $table->string('fuel_level')->nullable();
            $table->text('observations')->nullable();
            $table->text('diagnostic')->nullable();
            $table->enum('status', ['received', 'in_progress', 'ready', 'delivered'])->default('received');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
