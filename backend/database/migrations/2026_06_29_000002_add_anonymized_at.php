<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('anonymized_at')->nullable()->after('data_processing_accepted_at');
        });

        Schema::table('attendees', function (Blueprint $table) {
            $table->timestamp('anonymized_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('anonymized_at');
        });

        Schema::table('attendees', function (Blueprint $table) {
            $table->dropColumn('anonymized_at');
        });
    }
};
