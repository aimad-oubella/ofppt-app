<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TraineeApiTest extends TestCase
{
    use RefreshDatabase;

    private int $establishmentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ReferenceSeeder::class);
        $this->establishmentId = DB::table('establishments')->insertGetId([
            'name' => 'Établissement de test', 'code' => 'TEST', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function as(string $role): static
    {
        return $this->actingAs(User::factory()->create(['role' => $role]));
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'last_name' => 'El Amrani', 'first_name' => 'Zineb', 'cef' => 'T0001',
            'establishment_id' => $this->establishmentId,
        ], $extra);
    }

    public function test_la_consultation_ne_peut_pas_creer(): void
    {
        $this->as('consultation')->postJson('/api/v1/trainees', $this->payload())->assertForbidden();
        $this->assertDatabaseCount('trainees', 0);
    }

    public function test_un_gestionnaire_cree_et_consulte_un_dossier(): void
    {
        $libre = DB::table('candidate_categories')->where('code', 'libre')->value('id');

        $this->as('gestionnaire')->postJson('/api/v1/trainees', $this->payload(['category_id' => $libre]))
            ->assertCreated()
            ->assertJsonPath('category.code', 'libre');

        $this->getJson('/api/v1/trainees?q=amrani zineb')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_un_candidat_libre_peut_avoir_une_inscription_sans_groupe(): void
    {
        $this->as('gestionnaire')->postJson('/api/v1/trainees', $this->payload([
            'enrollment' => ['training_year' => 2],
        ]))->assertCreated()->assertJsonPath('enrollments.0.training_group_id', null);
    }

    public function test_cef_unique(): void
    {
        $this->as('gestionnaire');
        $this->postJson('/api/v1/trainees', $this->payload())->assertCreated();
        $this->postJson('/api/v1/trainees', $this->payload(['first_name' => 'Autre']))
            ->assertStatus(422)->assertJsonValidationErrors('cef');
    }

    public function test_champs_obligatoires_et_date_invalide(): void
    {
        $this->as('gestionnaire')->postJson('/api/v1/trainees', ['birth_date' => '31/02/2000'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['last_name', 'first_name', 'establishment_id', 'birth_date']);
    }

    public function test_archivage_et_restauration(): void
    {
        $this->as('gestionnaire');
        $id = $this->postJson('/api/v1/trainees', $this->payload())->json('id');

        $this->deleteJson("/api/v1/trainees/$id")->assertOk();
        $this->getJson('/api/v1/trainees')->assertJsonPath('total', 0);
        $this->getJson('/api/v1/trainees?archived=only')->assertJsonPath('total', 1);

        $this->postJson("/api/v1/trainees/$id/restore")->assertOk();
        $this->getJson('/api/v1/trainees')->assertJsonPath('total', 1);
    }

    public function test_filtre_sans_categorie_et_accents(): void
    {
        $this->as('gestionnaire');
        $this->postJson('/api/v1/trainees', $this->payload(['last_name' => 'Éloïse']))->assertCreated();

        $this->getJson('/api/v1/trainees?uncategorized=1')->assertJsonPath('total', 1);
        $this->getJson('/api/v1/trainees?q=Éloïse')->assertJsonPath('total', 1);
    }

    public function test_un_laureat_exige_une_categorie(): void
    {
        $this->as('gestionnaire');
        $id = $this->postJson('/api/v1/trainees', $this->payload())->json('id');

        $this->postJson("/api/v1/trainees/$id/graduations", ['graduation_year' => 2025])->assertStatus(422);

        $reg = DB::table('candidate_categories')->where('code', 'regulier')->value('id');
        $this->postJson("/api/v1/trainees/$id/graduations", ['graduation_year' => 2025, 'category_id' => $reg])
            ->assertCreated();
        $this->getJson('/api/v1/graduations?category_id='.$reg)->assertJsonPath('total', 1);
    }

    public function test_les_referentiels_sont_reserves_a_ladministrateur(): void
    {
        $this->as('gestionnaire')->postJson('/api/v1/references/regional-directions', ['name' => 'DR test'])->assertForbidden();
        $this->as('administrateur')->postJson('/api/v1/references/regional-directions', ['name' => 'DR test'])->assertCreated();
    }
}
