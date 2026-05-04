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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('surname')->nullable();
            $table->string('full_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('type_client')->nullable();
            $table->string('type_document')->nullable();
            $table->string('n_document')->nullable();
            $table->date('birth_date')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('sucursale_id')->nullable()->constrained('sucursales')->onDelete('set null');
            $table->integer('state')->default(1);
            $table->string('gender')->nullable();
            $table->string('ubigeo_region')->nullable();
            $table->string('ubigeo_provincia')->nullable();
            $table->string('ubigeo_distrito')->nullable();
            $table->string('region')->nullable();
            $table->string('provincia')->nullable();
            $table->string('distrito')->nullable();
            $table->text('address')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['sucursale_id']);
            $table->index(['state']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
