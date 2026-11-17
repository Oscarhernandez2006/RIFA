<?php

use App\Models\Invoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedInteger('tickets')->default(0)->after('net_total');
        });

        $ticketValue = (int) config('services.invoices.ticket_value');

        DB::table('invoices')->select(['id', 'net_total'])->orderBy('id')->chunk(500, function ($rows) use ($ticketValue) {
            foreach ($rows as $row) {
                DB::table('invoices')
                    ->where('id', $row->id)
                    ->update(['tickets' => Invoice::ticketsFor((float) $row->net_total, $ticketValue)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('tickets');
        });
    }
};
