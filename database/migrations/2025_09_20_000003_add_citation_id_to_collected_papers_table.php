<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('collected_papers', function (Blueprint $table) {
            $table->foreignId('citation_id')
                ->nullable()
                ->constrained('citations')
                ->nullOnDelete()
                ->after('paper_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collected_papers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('citation_id');
        });
    }
};
