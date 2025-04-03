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
        Schema::create('cash_models', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('member_id');
            $table->string('class_id');
            $table->integer('tahun');
            $table->integer('bulan'); // 1 = Januari, 2 = Februari, dst.
            $table->string('minggu'); // minggu_1, minggu_2, dst.
            $table->enum('status', ['Sudah Bayar', 'Belum Bayar'])->default('Belum Bayar');
            $table->integer('nominal')->default(0);
            $table->date('tanggal')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_models');
    }
};
