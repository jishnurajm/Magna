<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // WebhookDeliveryController::index() filters by subscription_id
        // (no status predicate) and sorts by created_at — the existing
        // [subscription_id, status] index doesn't cover the sort column.
        Schema::table('webhook_deliveries', function (Blueprint $table): void {
            $table->index(['subscription_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('webhook_deliveries', function (Blueprint $table): void {
            $table->dropIndex(['subscription_id', 'created_at']);
        });
    }
};
