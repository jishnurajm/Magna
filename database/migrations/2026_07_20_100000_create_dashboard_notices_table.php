<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Local cache of active dashboard notices, synced on every check-in
     * (Magna\Updater\UpdateCheckClient) from Update Manager's own
     * dashboard_notices table. dismissed_at is local-only — a site's own
     * dismissal is never reported back and is preserved across re-syncs of
     * the same still-active notice.
     */
    public function up(): void
    {
        Schema::create('dashboard_notices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('remote_id')->unique(); // Update Manager's own dashboard_notices.id
            $table->string('category'); // 'announcement' | 'system_upgrade'
            $table->string('category_description')->nullable();
            $table->string('image_url')->nullable();
            $table->string('title');
            $table->text('description');
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_notices');
    }
};
