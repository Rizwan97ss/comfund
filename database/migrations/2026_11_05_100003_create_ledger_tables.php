<?php

use App\Support\ImmutableTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 30)->index();
            $table->string('idempotency_key', 80)->nullable()->unique();
            $table->foreignId('reverses_transaction_id')->nullable()->unique()->constrained('ledger_transactions');
            $table->foreignId('member_id')->nullable()->constrained('users');
            $table->date('occurred_on')->index();
            $table->string('narration', 500);
            $table->foreignId('posted_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_transaction_id')->constrained();
            $table->foreignId('fund_id')->constrained();
            $table->foreignId('financial_account_id')->constrained();
            $table->foreignId('member_id')->nullable()->constrained('users');
            $table->string('kind', 20);
            $table->bigInteger('amount_paise');
            $table->date('occurred_on');
            $table->timestamp('created_at')->nullable();

            $table->index(['fund_id', 'occurred_on']);
            $table->index(['financial_account_id', 'occurred_on']);
            $table->index(['member_id', 'fund_id', 'kind']);
        });

        ImmutableTable::protect('ledger_transactions');
        ImmutableTable::protect('ledger_entries');
    }

    public function down(): void
    {
        ImmutableTable::release('ledger_entries');
        ImmutableTable::release('ledger_transactions');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
    }
};
