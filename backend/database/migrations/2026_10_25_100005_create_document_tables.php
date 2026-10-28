<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('label');
            $t->timestamps();
        });

        Schema::create('document_templates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('document_type_id')->constrained();
            $t->foreignId('category_id')->nullable()->constrained('candidate_categories');
            $t->foreignId('establishment_id')->nullable()->constrained();
            $t->string('name');
            $t->string('file_path')->nullable(); // .docx déposé par l'administrateur
            $t->unsignedSmallInteger('version')->default(1);
            $t->json('placeholders')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->index(['document_type_id', 'category_id', 'establishment_id', 'active'], 'tpl_lookup');
        });

        Schema::create('generated_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('document_type_id')->constrained();
            $t->foreignId('document_template_id')->nullable()->constrained('document_templates');
            $t->foreignId('trainee_id')->nullable()->constrained();
            $t->uuid('batch_uuid')->nullable()->index();
            $t->foreignId('user_id')->constrained();
            $t->string('file_path');
            $t->string('format', 10); // pdf, docx
            $t->json('data_snapshot')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_documents');
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('document_types');
    }
};
