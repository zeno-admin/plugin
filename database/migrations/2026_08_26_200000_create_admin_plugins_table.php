<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_plugins', function (Blueprint $table): void {
            $table->string('key', 80)->primary();
            $table->string('version', 191);
            $table->string('reference', 191)->nullable();
            $table->string('status', 30)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_plugins');
    }
};
