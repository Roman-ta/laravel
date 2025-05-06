<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agro_prom_bank_data', function (Blueprint $table) {
            $table->id();
            $table->string('from');
            $table->string('to');
            $table->float('buy');
            $table->float('sell');
            $table->timestamps();

            $table->unique(['from', 'to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agro_prom_bank_data');
    }
};
