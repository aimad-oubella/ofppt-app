<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthAndRolesTest extends TestCase
{
    use RefreshDatabase;

    private function stateful(): array
    {
        return ['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/'];
    }

    public function test_connexion_avec_bons_identifiants(): void
    {
        $user = User::factory()->create(['password' => 'motdepasse-test', 'role' => 'gestionnaire']);

        $this->withHeaders($this->stateful())
            ->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'motdepasse-test'])
            ->assertOk()
            ->assertJsonPath('user.role', 'gestionnaire');
    }

    public function test_connexion_refusee_avec_mauvais_mot_de_passe(): void
    {
        $user = User::factory()->create(['password' => 'motdepasse-test']);

        $this->withHeaders($this->stateful())
            ->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'faux'])
            ->assertStatus(422);
    }

    public function test_visiteur_non_connecte_est_refuse(): void
    {
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }

    public function test_liste_des_utilisateurs_reservee_a_ladministrateur(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'consultation']), ['*'], 'web');
        $this->getJson('/api/v1/users')->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'gestionnaire']), ['*'], 'web');
        $this->getJson('/api/v1/users')->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'administrateur']), ['*'], 'web');
        $this->getJson('/api/v1/users')->assertOk();
    }

    public function test_le_tableau_de_bord_affiche_zero_sans_donnees(): void
    {
        $this->seed(\Database\Seeders\ReferenceSeeder::class);
        Sanctum::actingAs(User::factory()->create(['role' => 'consultation']), ['*'], 'web');

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('total_trainees', 0)
            ->assertJsonPath('generated_documents', 0);
    }
}
