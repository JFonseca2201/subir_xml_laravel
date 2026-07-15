<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_technicians', function (Blueprint $table) {
            $table->foreignId('quote_id')->constrained('quotes')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->primary(['quote_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_technicians');
    }
};
