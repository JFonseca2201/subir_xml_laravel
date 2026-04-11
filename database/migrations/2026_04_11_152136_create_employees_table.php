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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('surname', 100)->nullable();
            $table->string('full_name', 200);
            $table->string('dni', 15)->unique();
            $table->string('phone', 20)->nullable();
            $table->string('email', 150)->nullable()->unique();
            $table->date('birth_date')->nullable();
            $table->string('address', 255)->nullable();
            $table->tinyInteger('gender')->nullable()->comment('1=Masculino, 2=Femenino, 3=Otro');
            $table->string('position', 100)->nullable()->comment('Puesto del empleado');
            $table->decimal('salary', 10, 2)->nullable();
            $table->date('hire_date')->nullable()->comment('Fecha de contratación');
            $table->string('account_number', 50)->nullable()->comment('Número de cuenta bancaria');
            $table->string('bank_name', 100)->nullable()->comment('Nombre del banco');
            $table->tinyInteger('status')->default(1)->comment('1=Activo, 2=Inactivo');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('sucursale_id')->nullable()->default(1)->constrained('sucursales')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
