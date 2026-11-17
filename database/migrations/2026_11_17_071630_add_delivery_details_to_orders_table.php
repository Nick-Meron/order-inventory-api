<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nullable, so orders placed before this change stay valid.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_name')->nullable()->after('total');
            $table->string('phone', 20)->nullable()->after('delivery_name');
            $table->string('address', 500)->nullable()->after('phone');
            $table->string('city', 100)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_name', 'phone', 'address', 'city']);
        });
    }
};