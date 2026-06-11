<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('sequences');

        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->bigInteger('current_value')->default(0);
            $table->timestamps();
        });

        // Initialize taller_global_sequence with current max
        $maxGlobal = 0;

        if (Schema::hasTable('sales')) {
            $salesNumbers = DB::table('sales')->pluck('document_number');
            foreach ($salesNumbers as $number) {
                if (preg_match('/(?:V|OT)-?(\d+)/i', $number, $matches)) {
                    $maxGlobal = max($maxGlobal, (int) $matches[1]);
                }
            }
        }

        if (Schema::hasTable('work_orders')) {
            $otNumbers = DB::table('work_orders')->pluck('number');
            foreach ($otNumbers as $number) {
                if (preg_match('/OT-?(\d+)/i', $number, $matches)) {
                    $maxGlobal = max($maxGlobal, (int) $matches[1]);
                }
            }
        }

        DB::table('sequences')->insert([
            'name' => 'taller_global_sequence',
            'current_value' => $maxGlobal,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
