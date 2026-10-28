<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->string('original_name');
            $t->string('stored_path');
            $t->string('status', 30)->default('televerse'); // televerse, mappe, valide, importe
            $t->json('mapping')->nullable();
            $t->json('totals')->nullable(); // importees, ignorees, rejetees
            $t->timestamps();
        });

        Schema::create('import_rows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('row_number');
            $t->json('raw_data');
            $t->string('status', 30)->default('en_attente'); // valide, doublon, rejetee, importee, ignoree
            $t->json('errors')->nullable();
            $t->unsignedBigInteger('trainee_id')->nullable();
            $t->timestamps();
            $t->index(['import_batch_id', 'status']);
        });

        Schema::create('import_mapping_profiles', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->json('mapping');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_mapping_profiles');
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
    }
};
