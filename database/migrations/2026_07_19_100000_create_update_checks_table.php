<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per updatable thing (core, or one row per installed plugin slug).
     * Populated by the scheduled check-in and by the manual "Check for Updates"
     * action — both write here, so the dashboard notice and the System Info
     * page can never disagree about what's available.
     */
    public function up(): void
    {
        Schema::create('update_checks', function (Blueprint $table): void {
            $table->id();
            $table->string('type'); // 'core' | 'plugin'
            $table->string('slug')->nullable(); // null for core, package slug for plugins
            $table->string('current_version');
            $table->string('latest_version')->nullable();
            $table->string('changelog_url')->nullable();
            $table->boolean('update_available')->default(false);
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->unique(['type', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('update_checks');
    }
};
