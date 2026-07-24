<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magna_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('entry_type', 100);
            $table->char('entry_id', 26);
            $table->json('payload');
            $table->char('author_id', 26)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entry_type', 'entry_id', 'created_at'], 'idx_magna_revisions_entry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('magna_revisions');
    }
};
