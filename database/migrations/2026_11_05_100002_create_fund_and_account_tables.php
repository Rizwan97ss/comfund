<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funds', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 80);
            $table->string('description')->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('is_restricted')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('fund_purposes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_id')->constrained();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['fund_id', 'name']);
        });

        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('type', 20);
            $table->string('account_reference', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('fund_account_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_id')->constrained();
            $table->foreignId('financial_account_id')->constrained();
            $table->bigInteger('balance_paise')->default(0);
            $table->timestamps();

            $table->unique(['fund_id', 'financial_account_id']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE fund_account_balances ADD CONSTRAINT fund_account_balances_not_negative CHECK (balance_paise >= 0)');
        }

        $now = now();

        $funds = [
            ['code' => 'monthly_fund', 'name' => 'Monthly Fund', 'description' => 'Mandatory monthly member contribution.', 'is_mandatory' => true, 'is_restricted' => false],
            ['code' => 'zakat', 'name' => 'Zakat', 'description' => 'Obligatory alms, spent only on eligible recipients.', 'is_mandatory' => false, 'is_restricted' => true],
            ['code' => 'sadaqah', 'name' => 'Sadaqah', 'description' => 'Voluntary charity.', 'is_mandatory' => false, 'is_restricted' => false],
            ['code' => 'fitrah', 'name' => 'Fitrah', 'description' => 'Zakat al-Fitr, given for the needy before Eid.', 'is_mandatory' => false, 'is_restricted' => true],
            ['code' => 'imdad', 'name' => 'Imdad', 'description' => 'Assistance for members and families in hardship.', 'is_mandatory' => false, 'is_restricted' => true],
            ['code' => 'other_donations', 'name' => 'Other Donations', 'description' => 'General voluntary donations.', 'is_mandatory' => false, 'is_restricted' => false],
        ];

        foreach ($funds as $index => $fund) {
            DB::table('funds')->insert([...$fund, 'is_active' => true, 'sort_order' => $index, 'created_at' => $now, 'updated_at' => $now]);
        }

        $purposes = [
            'zakat' => ['The poor (Fuqara)', 'The needy (Masakin)', 'Zakat administrators (Amilin)', 'Reconciliation of hearts (Muallafah)', 'Freeing from bondage (Riqab)', 'Those in debt (Gharimin)', 'In the cause of Allah (Fi Sabilillah)', 'The stranded traveller (Ibn al-Sabil)'],
            'fitrah' => ['Food for the poor and needy', 'Fitrah distribution to the needy'],
            'imdad' => ['Medical assistance', 'Education assistance', 'Emergency relief', 'Funeral assistance', 'Livelihood support'],
        ];

        foreach ($purposes as $code => $names) {
            $fundId = DB::table('funds')->where('code', $code)->value('id');

            foreach ($names as $name) {
                DB::table('fund_purposes')->insert(['fund_id' => $fundId, 'name' => $name, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        foreach ([['Cash in Hand', 'cash'], ['Community Bank Account', 'bank']] as [$name, $type]) {
            DB::table('financial_accounts')->insert(['name' => $name, 'type' => $type, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        foreach (DB::table('funds')->pluck('id') as $fundId) {
            foreach (DB::table('financial_accounts')->pluck('id') as $accountId) {
                DB::table('fund_account_balances')->insert(['fund_id' => $fundId, 'financial_account_id' => $accountId, 'balance_paise' => 0, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_account_balances');
        Schema::dropIfExists('financial_accounts');
        Schema::dropIfExists('fund_purposes');
        Schema::dropIfExists('funds');
    }
};
