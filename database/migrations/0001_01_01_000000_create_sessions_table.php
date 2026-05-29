<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('user_id')->nullable()->index();
                $table->string('deoris_user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });

            return;
        }

        Schema::table('sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('sessions', 'deoris_user_id')) {
                $table->string('deoris_user_id')->nullable()->index()->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
