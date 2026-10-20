<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('company_id');
            $table->string('document_type', 5);
            $table->unsignedBigInteger('consecutive');
            $table->string('document_number', 30);
            $table->date('issued_on');
            $table->string('point_of_sale', 120)->nullable();
            $table->decimal('net_total', 14, 2);
            $table->timestamps();

            $table->unique(['company_id', 'document_type', 'consecutive']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
