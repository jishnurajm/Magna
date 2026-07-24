<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('magna_media', function (Blueprint $table): void {
            $table->index('mime_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('magna_media', function (Blueprint $table): void {
            $table->dropIndex(['mime_type']);
            $table->dropIndex(['created_at']);
        });
    }
};
