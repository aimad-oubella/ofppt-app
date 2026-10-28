<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_types', function (Blueprint $t) {
            $t->id();
            $t->string('label')->unique();
            $t->timestamps();
        });

        Schema::create('assessments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assessment_type_id')->constrained();
            $t->foreignId('training_group_id')->constrained('training_groups');
            $t->string('module_name');
            $t->date('date')->nullable();
            $t->time('time')->nullable();
            $t->string('orientation', 10)->default('portrait'); // portrait, paysage
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->timestamps();
        });

        Schema::create('assessment_attendees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('trainee_id')->constrained();
            $t->unsignedSmallInteger('order_no');
            $t->timestamps();
            $t->unique(['assessment_id', 'trainee_id']);
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->string('action', 60);
            $t->string('auditable_type')->nullable();
            $t->unsignedBigInteger('auditable_id')->nullable();
            $t->json('old_values')->nullable();
            $t->json('new_values')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['auditable_type', 'auditable_id']);
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->json('value')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('assessment_attendees');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('assessment_types');
    }
};
