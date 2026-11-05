<?php

use App\Support\ImmutableTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('path')->unique();
            $table->string('original_name');
            $table->string('mime_type', 60);
            $table->unsignedInteger('size_bytes');
            $table->char('sha256', 64)->index();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('payment_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('member_id')->constrained('users');
            $table->foreignId('fund_id')->constrained();
            $table->unsignedBigInteger('amount_paise');
            $table->date('applicable_month')->nullable();
            $table->date('payment_date');
            $table->string('payment_method', 20);
            $table->string('transaction_reference', 100)->nullable();
            $table->char('duplicate_guard', 64)->nullable()->unique();
            $table->string('member_note', 500)->nullable();
            $table->foreignId('document_id')->nullable()->constrained();
            $table->string('source', 20)->default('member');
            $table->string('attribution_note', 500)->nullable();
            $table->foreignId('submitted_by')->constrained('users');
            $table->foreignId('resubmission_of_id')->nullable()->constrained('payment_submissions');
            $table->string('idempotency_key', 80)->unique();
            $table->string('status', 20)->default('pending');
            $table->foreignId('financial_account_id')->nullable()->constrained();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignId('ledger_transaction_id')->nullable()->unique()->constrained();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['member_id', 'status']);
        });

        ImmutableTable::protect('documents');

        // A submission may change only once, from pending; what the member submitted never changes.
        ImmutableTable::rejectUpdatesWhen(
            'payment_submissions',
            "OLD.status <> 'pending' OR NEW.amount_paise <> OLD.amount_paise OR NEW.member_id <> OLD.member_id OR NEW.fund_id <> OLD.fund_id OR NEW.payment_date <> OLD.payment_date OR NEW.payment_method <> OLD.payment_method OR NEW.submitted_by <> OLD.submitted_by",
        );
        ImmutableTable::rejectDeletes('payment_submissions');
    }

    public function down(): void
    {
        ImmutableTable::release('payment_submissions');
        ImmutableTable::release('documents');
        Schema::dropIfExists('payment_submissions');
        Schema::dropIfExists('documents');
    }
};
