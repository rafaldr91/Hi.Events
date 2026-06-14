<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->string('invoice_suffix')->nullable()->after('invoice_prefix');
            $table->string('invoice_number_format')->nullable()->after('invoice_suffix');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedInteger('sequence_number')->nullable()->after('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn(['invoice_suffix', 'invoice_number_format']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('sequence_number');
        });
    }
};
