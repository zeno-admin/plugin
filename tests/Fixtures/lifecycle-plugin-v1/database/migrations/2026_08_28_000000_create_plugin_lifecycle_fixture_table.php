<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plugin_lifecycle_fixture')) {
            Schema::create('plugin_lifecycle_fixture', function (Blueprint $table): void {
                $table->id();
            });
        }
    }

    public function down(): void {}
};
