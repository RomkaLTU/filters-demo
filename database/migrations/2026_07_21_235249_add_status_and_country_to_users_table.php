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
        Schema::table('users', function (Blueprint $table) {
            $table->text('status')->default('active')->after('email');
            $table->text('country')->nullable()->after('status');
        });

        DB::statement(
            "ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active', 'pending', 'suspended', 'deactivated'))"
        );
        DB::statement(
            "ALTER TABLE users ADD CONSTRAINT users_country_check CHECK (country ~ '^[A-Z]{2}$')"
        );

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        Schema::table('users', function (Blueprint $table) {
            $table->index('status');
            $table->index('country');
            $table->index('created_at');
        });

        DB::statement('CREATE INDEX users_name_trgm_index ON users USING GIN (name gin_trgm_ops)');
        DB::statement('CREATE INDEX users_email_trgm_index ON users USING GIN (email gin_trgm_ops)');
        DB::statement('CREATE INDEX users_created_at_date_index ON users ((created_at::date))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_created_at_date_index');
        DB::statement('DROP INDEX IF EXISTS users_email_trgm_index');
        DB::statement('DROP INDEX IF EXISTS users_name_trgm_index');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['country']);
            $table->dropIndex(['created_at']);
        });

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_country_check');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['status', 'country']);
        });
    }
};
