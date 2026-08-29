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
                $table->timestamp('upgraded_at')->nullable();
            });

            return;
        }

        if (! Schema::hasColumn('plugin_lifecycle_fixture', 'upgraded_at')) {
            Schema::table('plugin_lifecycle_fixture', function (Blueprint $table): void {
                $table->timestamp('upgraded_at')->nullable();
            });
        }
    }

    public function down(): void {}
};
