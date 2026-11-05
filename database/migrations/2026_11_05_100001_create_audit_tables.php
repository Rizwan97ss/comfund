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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sequence')->unique();
            $table->dateTime('occurred_at', 6)->index();
            $table->string('category', 20)->index();
            $table->string('action', 120)->index();
            $table->string('severity', 10)->index();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('actor_label')->nullable();
            $table->string('actor_role', 20)->nullable();
            $table->string('subject_type', 60)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('request_id', 36)->nullable()->index();
            $table->string('http_method', 10)->nullable();
            $table->string('route', 191)->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            // Exact text rather than JSON: MySQL's JSON type reorders keys, which would break the hash.
            $table->longText('payload')->nullable();
            $table->char('previous_hash', 64);
            $table->char('hash', 64);

            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('audit_chain_heads', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('last_sequence');
            $table->char('last_hash', 64);
        });

        DB::table('audit_chain_heads')->insert([
            'id' => 1,
            'last_sequence' => 0,
            'last_hash' => str_repeat('0', 64),
        ]);

        ImmutableTable::protect('audit_logs');
    }

    public function down(): void
    {
        ImmutableTable::release('audit_logs');
        Schema::dropIfExists('audit_chain_heads');
        Schema::dropIfExists('audit_logs');
    }
};
