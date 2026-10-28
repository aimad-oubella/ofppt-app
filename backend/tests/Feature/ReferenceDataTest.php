<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReferenceDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ReferenceSeeder::class);
    }

    public function test_les_quatre_niveaux_sont_presents(): void
    {
        $this->assertEqualsCanonicalizing(
            ['Spécialisation', 'Qualification', 'Technicien', 'Technicien Spécialisé'],
            DB::table('levels')->pluck('name')->all()
        );
    }

    public function test_les_deux_categories_existent(): void
    {
        $this->assertEqualsCanonicalizing(['regulier', 'libre'], DB::table('candidate_categories')->pluck('code')->all());
    }

    public function test_le_modele_libre_contient_la_ligne_ancien_pv_et_les_cinq_informations(): void
    {
        $libre = DB::table('candidate_categories')->where('code', 'libre')->value('id');
        $modelId = DB::table('checklist_models')->where('category_id', $libre)->value('id');

        $line = DB::table('checklist_support_definitions')
            ->where('checklist_model_id', $modelId)
            ->where('label', 'Ancien PV de fin de formation')
            ->first();

        $this->assertNotNull($line);
        $this->assertSame(
            ['Nom et Prénom', "N° d'inscription", 'Filière', 'Non éliminé', 'Année'],
            json_decode($line->info_to_verify, true)
        );
    }

    public function test_le_modele_regulier_na_pas_la_ligne_ancien_pv(): void
    {
        $regulier = DB::table('candidate_categories')->where('code', 'regulier')->value('id');
        $modelId = DB::table('checklist_models')->where('category_id', $regulier)->value('id');

        $this->assertFalse(
            DB::table('checklist_support_definitions')
                ->where('checklist_model_id', $modelId)
                ->where('label', 'Ancien PV de fin de formation')
                ->exists()
        );
    }

    public function test_les_intitules_de_categorie_sont_corrects(): void
    {
        $this->assertSame(
            ['Catégorie : Cours Du Jour/Candidat Régulier', 'Catégorie : Cours Du Jour/Candidat Libre'],
            DB::table('checklist_models')->orderBy('id')->pluck('header_label')->all()
        );
    }
}
