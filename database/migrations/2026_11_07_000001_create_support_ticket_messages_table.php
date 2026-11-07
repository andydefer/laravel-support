<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('ticket_id');
            $table->foreign('ticket_id')
                ->references('id')
                ->on('support_tickets')
                ->cascadeOnDelete();

            $table->uuid('reply_to_id')->nullable();
            $table->foreign('reply_to_id')
                ->references('id')
                ->on('support_ticket_messages')
                ->nullOnDelete();

            // Enums string (backed)
            $table->string('sender_type', 32);
            $table->string('status', 32)->default('sent');

            $table->longText('content');
            $table->boolean('is_internal')->default(false);

            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['ticket_id', 'created_at']);
            $table->index('sender_type');
            $table->index('status');
            $table->index('is_internal');
            $table->index('reply_to_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');
    }
};
