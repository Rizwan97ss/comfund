<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('role', 20)->default('member')->after('password')->index();
            $table->string('status', 20)->default('invited')->after('role')->index();
            $table->string('member_code', 20)->nullable()->unique()->after('status');
            $table->string('phone', 20)->nullable()->unique()->after('member_code');
            $table->text('address')->nullable()->after('phone');
            $table->date('joined_on')->nullable()->after('address');
            $table->unsignedBigInteger('monthly_contribution_paise')->nullable()->after('joined_on');
            $table->foreignId('invited_by')->nullable()->after('monthly_contribution_paise')->constrained('users');
            $table->timestamp('activated_at')->nullable()->after('invited_by');
            $table->timestamp('suspended_at')->nullable()->after('activated_at');
        });

        // Accounts created before membership existed belong to whoever installed the app.
        DB::table('users')->update(['role' => 'admin', 'status' => 'active', 'activated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invited_by');
            $table->dropColumn(['role', 'status', 'member_code', 'phone', 'address', 'joined_on', 'monthly_contribution_paise', 'activated_at', 'suspended_at']);
        });
    }
};
