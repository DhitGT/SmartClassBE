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
        Schema::create('task_subject_models', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('class_id');
            $table->string('subject_id');
            $table->string('name');
            $table->text('description');
            $table->date('due_to');
            $table->enum('status',['ToDo','InProgress','Completed']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_subject_models');
    }
};
