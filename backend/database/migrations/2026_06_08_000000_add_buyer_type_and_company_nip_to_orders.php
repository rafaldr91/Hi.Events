<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('buyer_type', 20)->default('individual')->after('email');
            $table->string('company_nip', 20)->nullable()->after('buyer_type');
            $table->string('company_name', 255)->nullable()->after('company_nip');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['buyer_type', 'company_nip', 'company_name']);
        });
    }
};
