<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('targeting_rules', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('flag_configuration_id', 26);
            $table->unsignedInteger('priority');
            $table->string('description')->nullable();
            $table->json('condition');
            $table->json('variation');
            $table->timestamps();

            $table->foreign('flag_configuration_id')->references('id')->on('flag_configurations')->cascadeOnDelete();
            $table->index(['flag_configuration_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('targeting_rules');
    }
};
