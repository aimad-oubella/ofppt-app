<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role', 30)->default('consultation')->after('password');
            $t->foreignId('establishment_id')->nullable()->after('role')->constrained('establishments');
            $t->boolean('active')->default(true)->after('establishment_id');
            $t->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropForeign(['establishment_id']);
            $t->dropIndex(['role']);
            $t->dropColumn(['role', 'establishment_id', 'active']);
        });
    }
};
