<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('ksef_status', 20)->nullable()->after('deleted_at');
            $table->string('ksef_number', 50)->nullable()->after('ksef_status');
            $table->string('ksef_reference_number', 100)->nullable()->after('ksef_number');
            $table->text('ksef_error_message')->nullable()->after('ksef_reference_number');
            $table->unsignedSmallInteger('ksef_retry_count')->default(0)->after('ksef_error_message');
            $table->timestamp('ksef_sent_at')->nullable()->after('ksef_retry_count');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'ksef_status',
                'ksef_number',
                'ksef_reference_number',
                'ksef_error_message',
                'ksef_retry_count',
                'ksef_sent_at',
            ]);
        });
    }
};
