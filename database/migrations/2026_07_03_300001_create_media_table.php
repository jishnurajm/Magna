<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magna_media', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('folder_id', 26)->nullable()->index();
            $table->string('disk', 50)->default('public');
            $table->string('path', 1000);
            $table->string('filename', 255);
            $table->string('original_filename', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt', 500)->nullable();
            $table->string('title', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('folder_id')
                ->references('id')
                ->on('magna_media_folders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('magna_media');
    }
};
