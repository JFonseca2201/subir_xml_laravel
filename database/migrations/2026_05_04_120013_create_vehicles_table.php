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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('license_plate')->unique();
            $table->string('brand');
            $table->string('model');
            $table->year('year')->nullable();
            $table->string('color')->nullable();
            $table->string('vehicle_type');
            $table->text('description')->nullable();
            $table->string('status')->default('active');

            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['vehicle_type', 'status']);
            $table->index(['brand', 'model']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};