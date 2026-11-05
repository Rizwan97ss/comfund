<?php

use App\Support\ImmutableTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('amount_paise');
            $table->date('effective_from')->unique();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('dues_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('users');
            $table->foreignId('fund_id')->constrained();
            $table->date('period');
            $table->unsignedBigInteger('amount_paise');
            $table->string('rate_source', 20);
            $table->timestamp('created_at')->nullable();

            $table->unique(['member_id', 'period']);
            $table->index('period');
        });

        Schema::create('dues_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dues_obligation_id')->constrained();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained();
            $table->bigInteger('amount_paise');
            $table->string('reason', 40);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('dues_waivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dues_obligation_id')->constrained();
            $table->bigInteger('amount_paise');
            $table->string('reason', 500);
            $table->foreignId('reverses_waiver_id')->nullable()->unique()->constrained('dues_waivers');
            $table->foreignId('granted_by')->constrained('users');
            $table->timestamp('created_at')->nullable();
        });

        DB::table('monthly_rates')->insert([
            'amount_paise' => 10000,
            'effective_from' => '2000-01-01',
            'note' => 'Default monthly contribution of ₹100.',
            'created_at' => now(),
        ]);

        foreach (['monthly_rates', 'dues_obligations', 'dues_allocations', 'dues_waivers'] as $table) {
            ImmutableTable::protect($table);
        }
    }

    public function down(): void
    {
        foreach (['dues_waivers', 'dues_allocations', 'dues_obligations', 'monthly_rates'] as $table) {
            ImmutableTable::release($table);
            Schema::dropIfExists($table);
        }
    }
};
