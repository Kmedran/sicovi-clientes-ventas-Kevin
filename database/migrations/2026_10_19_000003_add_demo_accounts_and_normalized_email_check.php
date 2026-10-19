<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false);
        });
        // The existing case-insensitive unique index prevents collisions already.
        // Existing accounts remain real accounts; passwords and roles are preserved.
        DB::statement('UPDATE users SET email = lower(btrim(email) COLLATE pg_catalog.pg_c_utf8) WHERE email <> lower(btrim(email) COLLATE pg_catalog.pg_c_utf8)');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_email_lowercase_check CHECK (email = lower(btrim(email) COLLATE pg_catalog.pg_c_utf8))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_email_lowercase_check');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_demo'));
    }
};
