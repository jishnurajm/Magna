<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name', 255);
            // Public identifier sent in X-Magna-Key header (e.g. mag_del_xxx...)
            $table->string('key', 64)->unique();
            // SHA-256 hex hash of the plain-text secret — never store the secret itself
            $table->string('secret_hash', 64);
            $table->string('scope', 20)->default('delivery');   // delivery | management
            $table->unsignedInteger('rate_limit_per_minute')->nullable();
            $table->json('allowed_origins')->nullable();        // CORS allowlist (optional)
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignUlid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
