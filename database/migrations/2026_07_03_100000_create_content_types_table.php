<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_types', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('handle', 100)->unique();
            $table->string('display_name', 255);
            $table->boolean('is_database_defined')->default(false);
            $table->json('schema');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_types');
    }
};
