<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magna_media_conversions', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('media_id', 26)->index();
            $table->string('preset', 100);
            $table->string('format', 10);
            $table->string('path', 1000);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedBigInteger('size');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['media_id', 'preset', 'format']);

            $table->foreign('media_id')
                ->references('id')
                ->on('magna_media')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('magna_media_conversions');
    }
};
