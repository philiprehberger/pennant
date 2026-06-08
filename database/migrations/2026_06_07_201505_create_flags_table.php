<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flags', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('workspace_id', 26);
            $table->string('key', 80);
            $table->string('type', 16);
            $table->text('description')->nullable();
            $table->json('default_value');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->unique(['workspace_id', 'key']);
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flags');
    }
};
