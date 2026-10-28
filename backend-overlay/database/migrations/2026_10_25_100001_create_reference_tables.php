<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regional_directions', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });

        Schema::create('establishments', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code', 30)->unique();
            $t->foreignId('regional_direction_id')->nullable()->constrained('regional_directions');
            $t->string('logo_path')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('levels', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        // Un établissement ne propose pas forcément les quatre niveaux.
        Schema::create('establishment_level', function (Blueprint $t) {
            $t->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('level_id')->constrained();
            $t->primary(['establishment_id', 'level_id']);
        });

        Schema::create('filieres', function (Blueprint $t) {
            $t->id();
            $t->foreignId('establishment_id')->constrained();
            $t->foreignId('level_id')->constrained();
            $t->string('name');
            $t->string('code', 30)->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['establishment_id', 'level_id', 'name']);
        });

        Schema::create('promotions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('establishment_id')->constrained();
            $t->string('label');
            $t->unsignedSmallInteger('year_start')->nullable();
            $t->unsignedSmallInteger('year_end')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['establishment_id', 'label']);
        });

        // « groups » est un mot réservé SQL : la table s'appelle training_groups.
        Schema::create('training_groups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('filiere_id')->constrained();
            $t->foreignId('promotion_id')->constrained();
            $t->string('name');
            $t->unsignedTinyInteger('training_year')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['filiere_id', 'promotion_id', 'name']);
        });

        Schema::create('candidate_categories', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('label');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['candidate_categories', 'training_groups', 'promotions', 'filieres',
                  'establishment_level', 'levels', 'establishments', 'regional_directions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
