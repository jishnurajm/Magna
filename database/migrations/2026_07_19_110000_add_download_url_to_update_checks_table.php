<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The pre-built release archive URL, needed by CoreUpdater to apply an update. */
    public function up(): void
    {
        Schema::table('update_checks', function (Blueprint $table): void {
            $table->string('download_url')->nullable()->after('changelog_url');
        });
    }

    public function down(): void
    {
        Schema::table('update_checks', function (Blueprint $table): void {
            $table->dropColumn('download_url');
        });
    }
};
