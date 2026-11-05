<?php

use App\Support\ImmutableTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('fund_id')->constrained();
            $table->foreignId('financial_account_id')->constrained();
            $table->unsignedBigInteger('amount_paise');
            $table->string('beneficiary', 160)->index();
            $table->string('purpose', 160);
            $table->date('expense_date')->index();
            $table->string('payment_method', 20);
            $table->string('transaction_reference', 100)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('ledger_transaction_id')->unique()->constrained();
            $table->string('idempotency_key', 80)->unique();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('expense_documents', function (Blueprint $table) {
            $table->foreignId('expense_id')->constrained();
            $table->foreignId('document_id')->constrained();
            $table->primary(['expense_id', 'document_id']);
        });

        Schema::create('account_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('fund_id')->constrained();
            $table->foreignId('from_account_id')->constrained('financial_accounts');
            $table->foreignId('to_account_id')->constrained('financial_accounts');
            $table->unsignedBigInteger('amount_paise');
            $table->date('transfer_date')->index();
            $table->string('transaction_reference', 100)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('ledger_transaction_id')->unique()->constrained();
            $table->string('idempotency_key', 80)->unique();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('account_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_account_id')->constrained();
            $table->date('as_of');
            $table->bigInteger('statement_balance_paise');
            $table->bigInteger('book_balance_paise');
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->nullable();
        });

        foreach (['expenses', 'expense_documents', 'account_transfers', 'account_reconciliations'] as $table) {
            ImmutableTable::protect($table);
        }
    }

    public function down(): void
    {
        foreach (['account_reconciliations', 'account_transfers', 'expense_documents', 'expenses'] as $table) {
            ImmutableTable::release($table);
            Schema::dropIfExists($table);
        }
    }
};
