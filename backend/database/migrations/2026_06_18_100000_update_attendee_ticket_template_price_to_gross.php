<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('email_templates')
            ->where('template_type', 'attendee_ticket')
            ->get()
            ->each(function ($row) {
                if (str_contains($row->body, '{{ ticket.price }}')) {
                    DB::table('email_templates')
                        ->where('id', $row->id)
                        ->update(['body' => str_replace('{{ ticket.price }}', '{{ ticket.price_gross }}', $row->body)]);
                }
            });
    }

    public function down(): void
    {
        DB::table('email_templates')
            ->where('template_type', 'attendee_ticket')
            ->get()
            ->each(function ($row) {
                if (str_contains($row->body, '{{ ticket.price_gross }}')) {
                    DB::table('email_templates')
                        ->where('id', $row->id)
                        ->update(['body' => str_replace('{{ ticket.price_gross }}', '{{ ticket.price }}', $row->body)]);
                }
            });
    }
};
