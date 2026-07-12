<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('magna_media', function (Blueprint $table): void {
            $table->char('uploaded_by', 26)->nullable()->after('folder_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('magna_media', function (Blueprint $table): void {
            $table->dropColumn('uploaded_by');
        });
    }
};
