<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedInteger('point_of_sale_id')->nullable()->after('company_id');
            $table->dropUnique(['company_id', 'document_type', 'consecutive']);
            $table->unique(['company_id', 'point_of_sale_id', 'document_type', 'consecutive']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'point_of_sale_id', 'document_type', 'consecutive']);
            $table->unique(['company_id', 'document_type', 'consecutive']);
            $table->dropColumn('point_of_sale_id');
        });
    }
};
