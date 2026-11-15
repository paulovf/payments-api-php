<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();

            $table->string('holder', 120);

            $table->bigInteger('balance_in_cents')
                ->default(0);

            $table->timestamps();
        });

        DB::statement('
            ALTER TABLE accounts
            ADD CONSTRAINT accounts_balance_non_negative
            CHECK (balance_in_cents >= 0)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};