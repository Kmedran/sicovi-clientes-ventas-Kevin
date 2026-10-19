<?php

use App\Support\Usernames;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 40)->nullable();
        });

        // PostgreSQL runs this migration in one transaction. ALTER TABLE's lock
        // prevents concurrent user writes until all existing accounts are assigned.
        $assigned = [];
        DB::table('users')->select(['id', 'email'])->orderBy('id')->chunkById(200, function ($users) use (&$assigned): void {
            foreach ($users as $user) {
                $username = Usernames::available(Usernames::fromEmail($user->email), fn (string $candidate): bool => isset($assigned[$candidate]));
                DB::table('users')->where('id', $user->id)->update(['username' => $username]);
                $assigned[$username] = true;
            }
        });

        DB::statement('ALTER TABLE users ALTER COLUMN username SET NOT NULL');
        Schema::table('users', function (Blueprint $table) {
            $table->unique('username');
        });
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_username_format_check CHECK (username COLLATE \"C\" ~ '^[a-z0-9][a-z0-9._-]{2,39}$')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_username_format_check');
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
