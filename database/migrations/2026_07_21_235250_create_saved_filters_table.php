<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('saved_filters', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('name');
            $table->jsonb('definition');
            $table->boolean('is_shared')->default(false);
            $table->timestampsTz();

            $table->index('user_id');
        });

        DB::statement(
            "ALTER TABLE saved_filters ADD CONSTRAINT saved_filters_definition_check CHECK (jsonb_typeof(definition) = 'object')"
        );

        DB::statement('CREATE UNIQUE INDEX saved_filters_user_id_lower_name_unique ON saved_filters (user_id, lower(name))');
        DB::statement('CREATE INDEX saved_filters_is_shared_index ON saved_filters (is_shared) WHERE is_shared');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_filters');
    }
};
