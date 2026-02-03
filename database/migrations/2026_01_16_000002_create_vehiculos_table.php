<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVehiculosTable extends Migration
{
    public function up()
    {
        Schema::create('vehiculos', function (Blueprint $table) {
            $table->id();
            $table->string('marca');
            $table->string('modelo');
            $table->string('placa')->unique();
            $table->string('tipo_vehiculo');
            $table->boolean('state')->default(true);
            $table->timestamps();
            $table->softDeletes(); // 👈 Soft delete
        });
    }

    public function down()
    {
        Schema::dropIfExists('vehiculos');
    }
}
