<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('data_processing_accepted_at')->nullable()->after('opted_into_marketing_at');
        });

        Schema::table('event_settings', function (Blueprint $table) {
            $table->boolean('show_data_processing_consent')->default(true)->after('show_marketing_opt_in');
            $table->string('privacy_policy_url', 2048)->nullable()->after('show_data_processing_consent');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('data_processing_accepted_at');
        });

        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn(['show_data_processing_consent', 'privacy_policy_url']);
        });
    }
};
