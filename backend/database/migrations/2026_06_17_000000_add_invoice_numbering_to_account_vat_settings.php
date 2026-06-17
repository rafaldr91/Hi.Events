<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('account_vat_settings', function (Blueprint $table) {
            $table->string('invoice_number_format', 100)->nullable()->after('vat_validation_attempts');
            $table->string('invoice_prefix', 50)->nullable()->after('invoice_number_format');
            $table->string('invoice_suffix', 50)->nullable()->after('invoice_prefix');
            $table->unsignedInteger('invoice_start_number')->default(1)->after('invoice_suffix');
            $table->string('confirmation_prefix', 50)->nullable()->after('invoice_start_number');
            $table->unsignedInteger('confirmation_start_number')->default(1)->after('confirmation_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('account_vat_settings', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_number_format',
                'invoice_prefix',
                'invoice_suffix',
                'invoice_start_number',
                'confirmation_prefix',
                'confirmation_start_number',
            ]);
        });
    }
};
