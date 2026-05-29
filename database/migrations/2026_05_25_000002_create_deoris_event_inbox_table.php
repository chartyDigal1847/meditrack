<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deoris_event_inbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique()->index();
            $table->string('event_name')->index();
            $table->string('source_module')->index();
            $table->json('payload');
            $table->string('signature');
            $table->string('nonce')->index();
            $table->unsignedBigInteger('timestamp')->index();
            $table->uuid('correlation_id')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['source_module', 'event_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deoris_event_inbox');
    }
};
