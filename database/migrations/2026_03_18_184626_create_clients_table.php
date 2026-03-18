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
            $table->string('name')->nullable();
            $table->string('surname')->nullable();
            $table->string('full_name')->unique();
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('type_client');
            $table->string('type_document');
            $table->string('n_document')->unique();
            $table->date('birth_date')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('sucursale_id')->default(1)->nullable()->constrained()->onDelete('set null');
            $table->tinyInteger('state')->default(1); //1 es true, 2 es false
            $table->string('gender')->nullable();
            $table->string('ubigeo_region')->nullable();
            $table->string('ubigeo_provincia')->nullable();
            $table->string('ubigeo_ciudad')->nullable();
            $table->string('region')->nullable();
            $table->string('provincia')->nullable();
            $table->string('ciudad')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
            $table->softDeletes();
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
