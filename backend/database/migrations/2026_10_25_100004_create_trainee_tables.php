<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainees', function (Blueprint $t) {
            $t->id();
            // CEF nullable : jamais de faux CEF. L'unicité ne s'applique qu'aux valeurs renseignées.
            $t->string('cef', 30)->nullable()->unique();
            $t->string('registration_number', 50)->nullable()->index();
            $t->string('matricule', 50)->nullable()->index();
            $t->string('last_name');
            $t->string('first_name');
            $t->string('name_ar')->nullable();
            $t->date('birth_date')->nullable();
            $t->string('birth_place')->nullable();
            $t->char('gender', 1)->nullable();
            $t->string('cin', 30)->nullable()->index();
            $t->foreignId('establishment_id')->constrained();
            // Nullable : si absente des données importées, elle est confirmée par l'utilisateur.
            $t->foreignId('category_id')->nullable()->constrained('candidate_categories');
            $t->string('admin_status', 50)->nullable();
            $t->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['last_name', 'first_name']);
        });

        // Historique d'inscription : un stagiaire peut changer de groupe ou de promotion.
        Schema::create('enrollments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('trainee_id')->constrained();
            // Nullable : un candidat libre n'a pas forcément de groupe de formation classique.
            $t->foreignId('training_group_id')->nullable()->constrained('training_groups');
            $t->foreignId('filiere_id')->nullable()->constrained();
            $t->foreignId('level_id')->nullable()->constrained();
            $t->foreignId('promotion_id')->nullable()->constrained();
            $t->unsignedTinyInteger('training_year')->nullable();
            $t->timestamps();
            $t->index('trainee_id');
        });

        Schema::create('graduations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('trainee_id')->constrained();
            $t->foreignId('filiere_id')->nullable()->constrained();
            $t->foreignId('promotion_id')->nullable()->constrained();
            $t->foreignId('category_id')->nullable()->constrained('candidate_categories');
            $t->unsignedSmallInteger('graduation_year')->nullable();
            $t->string('status', 50)->nullable();
            $t->text('notes')->nullable();
            $t->boolean('attestation_eligibility_confirmed')->default(false);
            $t->timestamps();
            $t->softDeletes();
            $t->index('trainee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graduations');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('trainees');
    }
};
