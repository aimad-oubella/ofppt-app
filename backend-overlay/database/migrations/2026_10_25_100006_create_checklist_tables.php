<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Statuts configurables, non présentés comme réglementaires.
        Schema::create('checklist_statuses', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('label');
            $t->string('color', 20)->nullable();
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('checklist_models', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->unique()->constrained('candidate_categories');
            $t->string('name');
            $t->string('header_label'); // ex. « Catégorie : Cours Du Jour/Candidat Libre »
            $t->timestamps();
        });

        Schema::create('checklist_support_definitions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('checklist_model_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->string('label');
            $t->json('info_to_verify')->nullable(); // une entrée = une ligne distincte
            $t->boolean('needs_confirmation')->default(false); // à confirmer d'après le Word original
            $t->timestamps();
        });

        Schema::create('diploma_checks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('graduation_id')->constrained();
            $t->foreignId('checklist_model_id')->constrained();
            $t->foreignId('category_id')->constrained('candidate_categories'); // copie de contrôle
            $t->foreignId('status_id')->constrained('checklist_statuses');
            $t->text('rejection_reason')->nullable();
            $t->date('rejection_date')->nullable();
            $t->foreignId('prepared_by')->nullable()->constrained('users');
            $t->timestamps();
            $t->index('status_id');
        });

        Schema::create('diploma_check_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('diploma_check_id')->constrained()->cascadeOnDelete();
            $t->foreignId('support_definition_id')->constrained('checklist_support_definitions');
            $t->boolean('available')->nullable();
            $t->boolean('compliant')->nullable();
            $t->text('observation')->nullable();
            $t->timestamps();
            $t->unique(['diploma_check_id', 'support_definition_id'], 'check_line_unique');
        });

        Schema::create('diploma_check_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('diploma_check_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained();
            $t->string('from_status', 30)->nullable();
            $t->string('to_status', 30);
            $t->text('comment')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['diploma_check_history', 'diploma_check_lines', 'diploma_checks',
                  'checklist_support_definitions', 'checklist_models', 'checklist_statuses'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
