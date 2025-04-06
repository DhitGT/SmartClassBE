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
        Schema::create('schedule_subject_models', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('day');
            $table->string('subject_id');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('class_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_subject_models');
    }
};
