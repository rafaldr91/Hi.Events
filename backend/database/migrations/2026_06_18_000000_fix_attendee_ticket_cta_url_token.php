<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('email_templates')
            ->where('template_type', 'attendee_ticket')
            ->whereNotNull('cta')
            ->get()
            ->each(function ($row) {
                $cta = json_decode($row->cta, true);
                if (isset($cta['url_token']) && $cta['url_token'] === 'order.url') {
                    $cta['url_token'] = 'ticket.url';
                    DB::table('email_templates')
                        ->where('id', $row->id)
                        ->update(['cta' => json_encode($cta)]);
                }
            });
    }

    public function down(): void
    {
        DB::table('email_templates')
            ->where('template_type', 'attendee_ticket')
            ->whereNotNull('cta')
            ->get()
            ->each(function ($row) {
                $cta = json_decode($row->cta, true);
                if (isset($cta['url_token']) && $cta['url_token'] === 'ticket.url') {
                    $cta['url_token'] = 'order.url';
                    DB::table('email_templates')
                        ->where('id', $row->id)
                        ->update(['cta' => json_encode($cta)]);
                }
            });
    }
};
