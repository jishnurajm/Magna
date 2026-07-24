<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('type', 20); // scheduled | manual
            $table->string('status', 20)->index(); // pending | running | success | failed
            $table->string('disk', 50)->nullable();
            $table->string('secondary_disk', 50)->nullable();
            $table->string('path', 500)->nullable();
            $table->string('secondary_path', 500)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('triggered_by', 26)->nullable()->index();
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
