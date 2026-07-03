<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magna_relations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('from_type', 100);
            $table->char('from_id', 26);
            $table->string('to_type', 100);
            $table->char('to_id', 26);
            $table->string('field', 100);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['from_type', 'from_id', 'field'], 'idx_magna_relations_from');
            $table->index(['to_type', 'to_id'], 'idx_magna_relations_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('magna_relations');
    }
};
