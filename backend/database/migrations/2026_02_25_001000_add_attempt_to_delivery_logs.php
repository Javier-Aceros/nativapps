<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_logs', function (Blueprint $table) {
            $table->unsignedSmallInteger('attempt')->default(1)->after('channel');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_logs', function (Blueprint $table) {
            $table->dropColumn('attempt');
        });
    }
};
