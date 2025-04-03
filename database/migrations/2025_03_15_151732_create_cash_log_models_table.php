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
        Schema::create('cash_log_models', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('cash_id')->nullable();
            $table->string('class_id');
            $table->integer('tahun');
            $table->integer('bulan'); // 1 = Januari, 2 = Februari, dst.
            $table->string('type');
            $table->bigInteger('amount');
            $table->text('description');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_log_models');
    }
};
