<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('reference', 32)->unique();
            $table->string('title', 191);
            $table->longText('description');

            // Double polymorphisme : auteur + assigné (191 pour index utf8mb4)
            $table->string('created_by_type', 191);
            $table->string('created_by_id', 191);
            $table->string('assigned_to_type', 191)->nullable();
            $table->string('assigned_to_id', 191)->nullable();

            $table->unsignedTinyInteger('status');
            $table->unsignedTinyInteger('priority');

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['created_by_type', 'created_by_id'], 'support_tickets_creator_index');
            $table->index(['assigned_to_type', 'assigned_to_id'], 'support_tickets_assignee_index');
            $table->index('status');
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
