<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Escenario 1: Catálogos PDF - Solo metadatos en Base de Datos Relacional.
     * El texto indexado reside en archivos independientes de almacenamiento.
     */
    public function up(): void
    {
        // Asegurar que no exista tabla de páginas en BD
        Schema::dropIfExists('distributor_catalog_pages');

        // 1. Tabla de Catálogos PDF (Únicamente Metadatos)
        if (!Schema::hasTable('distributor_catalogs')) {
            Schema::create('distributor_catalogs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->onDelete('cascade');
                $table->string('supplier_name', 150)->index();
                $table->string('title', 255)->nullable();
                $table->string('file_name', 255);
                $table->string('original_filename', 255)->nullable();
                $table->string('file_path', 500);
                $table->string('index_path', 500)->nullable(); // Ruta del archivo de texto/índice independiente
                $table->unsignedBigInteger('file_size')->default(0); // en bytes
                $table->unsignedInteger('total_pages')->default(0);
                $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->index();
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index(['supplier_id', 'status']);
            });
        } else {
            $cols = [
                'supplier_id'       => fn(Blueprint $t) => $t->unsignedBigInteger('supplier_id')->nullable()->index(),
                'supplier_name'     => fn(Blueprint $t) => $t->string('supplier_name', 150)->nullable()->index(),
                'title'             => fn(Blueprint $t) => $t->string('title', 255)->nullable(),
                'file_name'         => fn(Blueprint $t) => $t->string('file_name', 255)->nullable(),
                'original_filename' => fn(Blueprint $t) => $t->string('original_filename', 255)->nullable(),
                'file_path'         => fn(Blueprint $t) => $t->string('file_path', 500)->nullable(),
                'index_path'        => fn(Blueprint $t) => $t->string('index_path', 500)->nullable(),
                'file_size'         => fn(Blueprint $t) => $t->unsignedBigInteger('file_size')->default(0),
                'total_pages'       => fn(Blueprint $t) => $t->unsignedInteger('total_pages')->default(0),
                'status'            => fn(Blueprint $t) => $t->string('status', 50)->default('pending')->index(),
                'error_message'     => fn(Blueprint $t) => $t->text('error_message')->nullable(),
            ];

            foreach ($cols as $colName => $def) {
                if (!Schema::hasColumn('distributor_catalogs', $colName)) {
                    Schema::table('distributor_catalogs', function (Blueprint $t) use ($def) {
                        $def($t);
                    });
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distributor_catalog_pages');
        Schema::dropIfExists('distributor_catalogs');
    }
};
