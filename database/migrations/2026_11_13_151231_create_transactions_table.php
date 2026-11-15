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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                ->constrained()
                ->restrictOnDelete();

            $table->bigInteger('amount_in_cents');

            $table->string('type', 20);
            $table->string('status', 20)->default('pending');

            $table->string('description', 255);

            $table->timestamps();
        });

        DB::statement("
            ALTER TABLE transactions
            ADD CONSTRAINT transactions_amount_positive
            CHECK (amount_in_cents > 0)
        ");

        DB::statement("
            ALTER TABLE transactions
            ADD CONSTRAINT transactions_type_valid
            CHECK (type IN ('credit', 'debit'))
        ");

        DB::statement("
            ALTER TABLE transactions
            ADD CONSTRAINT transactions_status_valid
            CHECK (status IN ('pending', 'completed', 'cancelled'))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};