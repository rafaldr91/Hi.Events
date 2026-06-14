<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->string('confirmation_prefix')->nullable()->after('invoice_start_number');
            $table->unsignedInteger('confirmation_start_number')->default(1)->after('confirmation_prefix');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('document_type', 20)->default('invoice')->after('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn(['confirmation_prefix', 'confirmation_start_number']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('document_type');
        });
    }
};
