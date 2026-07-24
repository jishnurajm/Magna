<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('action', 100)->index();
            $table->string('actor_id', 26)->nullable()->index();
            $table->string('actor_type', 50)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('subject_type', 150)->nullable();
            $table->string('subject_id', 26)->nullable()->index();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
