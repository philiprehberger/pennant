<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flag_configurations', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('flag_id', 26);
            $table->char('environment_id', 26);
            $table->string('state', 8)->default('off');
            $table->json('variation');
            $table->string('bucketing_attribute', 64)->default('userId');
            $table->char('bucketing_seed', 26);
            $table->timestamps();

            $table->foreign('flag_id')->references('id')->on('flags')->cascadeOnDelete();
            $table->foreign('environment_id')->references('id')->on('environments')->cascadeOnDelete();
            $table->unique(['flag_id', 'environment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flag_configurations');
    }
};
