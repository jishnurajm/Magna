<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magna_media_folders', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('parent_id', 26)->nullable()->index();
            $table->string('name', 255);
            $table->string('path', 1000);
            $table->timestamps();

            $table->foreign('parent_id')
                ->references('id')
                ->on('magna_media_folders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('magna_media_folders');
    }
};
