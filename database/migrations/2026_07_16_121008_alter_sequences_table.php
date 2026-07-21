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
            $table->string('type')->unique(); // 'work_order', 'sale_note', 'invoice', etc.
            $table->bigInteger('current_number')->default(0);
            $table->string('prefix')->nullable();
            $table->timestamps();
        });

        // Initialize taller_global_sequence value as current max global number
        $maxGlobal = 0;
        if (Schema::hasTable('sales')) {
            $salesNumbers = DB::table('sales')->pluck('document_number');
            foreach ($salesNumbers as $number) {
                if (preg_match('/-\d{8}-/', $number)) {
                    continue;
                }
                if (preg_match('/(?:OT|V|NV|FAC)-?(\d+)/i', $number, $matches)) {
                    $val = (int)$matches[1];
                    if ($val < 1000000) {
                        $maxGlobal = max($maxGlobal, $val);
                    }
                } elseif (preg_match('/^\d+$/', $number)) {
                    $val = (int)$number;
                    if ($val < 1000000) {
                        $maxGlobal = max($maxGlobal, $val);
                    }
                }
            }
        }

        if (Schema::hasTable('work_orders')) {
            $otNumbers = DB::table('work_orders')->pluck('number');
            foreach ($otNumbers as $number) {
                if (preg_match('/-\d{8}-/', $number)) {
                    continue;
                }
                if (preg_match('/(?:OT)-?(\d+)/i', $number, $matches)) {
                    $val = (int)$matches[1];
                    if ($val < 1000000) {
                        $maxGlobal = max($maxGlobal, $val);
                    }
                } elseif (preg_match('/^\d+$/', $number)) {
                    $val = (int)$number;
                    if ($val < 1000000) {
                        $maxGlobal = max($maxGlobal, $val);
                    }
                }
            }
        }

        // Seed sequences table with initial values
        DB::table('sequences')->insert([
            [
                'type' => 'work_order',
                'current_number' => $maxGlobal,
                'prefix' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'sale_note',
                'current_number' => $maxGlobal,
                'prefix' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'invoice',
                'current_number' => $maxGlobal,
                'prefix' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
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
