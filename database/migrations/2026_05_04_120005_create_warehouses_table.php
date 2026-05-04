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
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->text('address')->nullable();
            $table->foreignId('sucursale_id')->nullable()->constrained('sucursales')->onDelete('set null');
            $table->integer('state')->default(1);

            $table->softDeletes();
            $table->timestamps();

            $table->index(['sucursale_id']);
            $table->index(['state']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
