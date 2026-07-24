<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Local cache copy of the Welcome banner's configurable quick-launch links — see the matching owner-side migration. */
    public function up(): void
    {
        Schema::table('dashboard_notices', function (Blueprint $table): void {
            $table->string('link_github')->nullable();
            $table->string('link_docs')->nullable();
            $table->string('link_blog')->nullable();
            $table->string('link_community')->nullable();
            $table->string('link_themes')->nullable();
            $table->string('link_plugins')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dashboard_notices', function (Blueprint $table): void {
            $table->dropColumn(['link_github', 'link_docs', 'link_blog', 'link_community', 'link_themes', 'link_plugins']);
        });
    }
};
