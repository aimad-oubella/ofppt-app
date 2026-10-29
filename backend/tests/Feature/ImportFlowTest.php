<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportFlowTest extends TestCase
{
    use RefreshDatabase;

    private int $establishmentId;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(\Database\Seeders\ReferenceSeeder::class);
        $this->establishmentId = DB::table('establishments')->insertGetId([
            'name' => 'Établissement de test', 'code' => 'TEST', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs(User::factory()->create(['role' => 'gestionnaire']));
    }

    /** Fichier de test aux en-têtes volontairement quelconques : le mapping est choisi par l'utilisateur. */
    private function upload(array $rows): array
    {
        $ss = new Spreadsheet();
        $ss->getActiveSheet()->fromArray($rows, null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'imp');
        (new Xlsx($ss))->save($path);
        $file = UploadedFile::fake()->createWithContent('fichier.xlsx', file_get_contents($path));

        $res = $this->post('/api/v1/imports', ['file' => $file], ['Accept' => 'application/json'])->assertCreated();

        return $res->json();
    }

    private function mapAndValidate(int $id, array $fields, array $options = []): array
    {
        $options = array_replace_recursive([
            'defaults' => ['establishment' => $this->establishmentId],
            'on_duplicate' => 'skip', 'create_missing' => false, 'as_laureates' => false, 'required_fields' => [],
        ], $options);

        $this->putJson("/api/v1/imports/$id/mapping", ['fields' => $fields, 'options' => $options])->assertOk();

        return $this->postJson("/api/v1/imports/$id/validate")->assertOk()->json('totals');
    }

    private const HEADERS = ['Famille', 'Prénom', 'Identifiant', 'Né le', 'Type'];
    private const FIELDS = ['last_name' => 0, 'first_name' => 1, 'cef' => 2, 'birth_date' => 3, 'category' => 4];

    private function sampleRows(): array
    {
        return [
            self::HEADERS,
            ['Alaoui', 'Sara', 'C001', '12/03/2002', 'Régulier'],
            ['Bennani', 'Omar', 'C002', '2001-07-30', 'Libre'],
            ['Alaoui', 'Sara', 'C001', '12/03/2002', 'Régulier'],   // doublon dans le fichier
            ['Cherkaoui', 'Lina', 'C003', '31/02/2000', 'Libre'],   // date invalide
            ['Daoudi', 'Yassine', 'C004', null, null],              // catégorie absente
        ];
    }

    public function test_apercu_avant_importation(): void
    {
        $batch = $this->upload($this->sampleRows());

        $this->assertSame(self::HEADERS, $batch['mapping']['headers']);
        $this->assertCount(5, $batch['preview']);
        $this->assertSame('Alaoui', $batch['preview'][0]['cells'][0]);
    }

    public function test_validation_detecte_doublons_erreurs_et_categorie_manquante(): void
    {
        $batch = $this->upload($this->sampleRows());
        $totals = $this->mapAndValidate($batch['id'], self::FIELDS);

        $this->assertSame(3, $totals['valides']);
        $this->assertSame(1, $totals['doublons']);
        $this->assertSame(1, $totals['rejetees']);
        $this->assertSame(1, $totals['sans_categorie']);
        $this->assertDatabaseCount('trainees', 0); // la validation n'écrit rien

        $rejected = $this->getJson("/api/v1/imports/{$batch['id']}/rows?status=rejetee")->json('data');
        $this->assertStringContainsString('Date de naissance', $rejected[0]['messages']['errors'][0]);
    }

    public function test_importation_complete_et_rapport(): void
    {
        $batch = $this->upload($this->sampleRows());
        $this->mapAndValidate($batch['id'], self::FIELDS);

        $this->postJson("/api/v1/imports/{$batch['id']}/execute", [])->assertStatus(422); // confirmation requise
        $res = $this->postJson("/api/v1/imports/{$batch['id']}/execute", ['confirm' => true])->assertOk();

        $this->assertSame(3, $res->json('totals.importees'));
        $this->assertDatabaseCount('trainees', 3);
        $this->assertDatabaseHas('trainees', ['cef' => 'C002', 'birth_date' => '2001-07-30']);
        $this->assertDatabaseHas('trainees', ['cef' => 'C004', 'category_id' => null]);

        $report = $this->getJson("/api/v1/imports/{$batch['id']}/report")->assertOk();
        $this->assertCount(1, $report->json('rejected'));
        $this->assertSame(1, $report->json('without_category'));
        $this->get("/api/v1/imports/{$batch['id']}/report/export")->assertOk();
    }

    public function test_reimporter_le_meme_fichier_ne_cree_pas_de_doublons(): void
    {
        $first = $this->upload($this->sampleRows());
        $this->mapAndValidate($first['id'], self::FIELDS);
        $this->postJson("/api/v1/imports/{$first['id']}/execute", ['confirm' => true])->assertOk();

        $second = $this->upload($this->sampleRows());
        $totals = $this->mapAndValidate($second['id'], self::FIELDS);
        $this->assertSame(4, $totals['doublons']);
        $this->assertSame(0, $totals['valides']);

        $this->postJson("/api/v1/imports/{$second['id']}/execute", ['confirm' => true])->assertOk();
        $this->assertDatabaseCount('trainees', 3);
    }

    public function test_categorie_par_defaut_choisie_par_lutilisateur(): void
    {
        $libre = DB::table('candidate_categories')->where('code', 'libre')->value('id');
        $batch = $this->upload([['Nom', 'Prénom'], ['Idrissi', 'Hamza']]);
        $totals = $this->mapAndValidate($batch['id'], ['last_name' => 0, 'first_name' => 1], ['defaults' => ['category' => $libre]]);

        $this->assertSame(0, $totals['sans_categorie']);
        $this->postJson("/api/v1/imports/{$batch['id']}/execute", ['confirm' => true])->assertOk();
        $this->assertDatabaseHas('trainees', ['last_name' => 'Idrissi', 'category_id' => $libre]);
    }

    public function test_correction_dune_ligne_rejetee_sans_recommencer(): void
    {
        $batch = $this->upload($this->sampleRows());
        $this->mapAndValidate($batch['id'], self::FIELDS);
        $row = $this->getJson("/api/v1/imports/{$batch['id']}/rows?status=rejetee")->json('data.0');

        $this->patchJson("/api/v1/imports/{$batch['id']}/rows/{$row['id']}", ['overrides' => ['birth_date' => '28/02/2000']])
            ->assertOk()->assertJsonPath('status', 'valide');
    }

    public function test_obligatoire_choisi_par_lutilisateur(): void
    {
        $batch = $this->upload([['Nom', 'Prénom', 'CIN'], ['Tazi', 'Rim', null]]);
        $totals = $this->mapAndValidate($batch['id'], ['last_name' => 0, 'first_name' => 1, 'cin' => 2], ['required_fields' => ['cin']]);

        $this->assertSame(1, $totals['rejetees']);
    }

    public function test_mode_completer_ne_remplace_jamais_une_valeur_existante(): void
    {
        DB::table('trainees')->insert([
            'cef' => 'C100', 'last_name' => 'Ancien', 'first_name' => 'Nom', 'establishment_id' => $this->establishmentId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $batch = $this->upload([['Nom', 'Prénom', 'CEF', 'Ville'], ['Nouveau', 'Prénom', 'C100', 'Agadir']]);
        $totals = $this->mapAndValidate($batch['id'], ['last_name' => 0, 'first_name' => 1, 'cef' => 2, 'birth_place' => 3], ['on_duplicate' => 'complete']);
        $this->assertSame(1, $totals['doublons']);

        $this->postJson("/api/v1/imports/{$batch['id']}/execute", ['confirm' => true])->assertOk()
            ->assertJsonPath('totals.mises_a_jour', 1);

        $this->assertDatabaseHas('trainees', ['cef' => 'C100', 'last_name' => 'Ancien', 'birth_place' => 'Agadir']);
    }

    public function test_creation_des_filieres_et_groupes_manquants_si_autorise(): void
    {
        $level = DB::table('levels')->where('name', 'Technicien')->value('id');
        $batch = $this->upload([['Nom', 'Prénom', 'Filière', 'Promotion', 'Groupe'], ['Lahlou', 'Nada', 'Développement digital', '2025', 'DD101']]);
        $fields = ['last_name' => 0, 'first_name' => 1, 'filiere' => 2, 'promotion' => 3, 'group' => 4];

        // Sans autorisation : rejet
        $totals = $this->mapAndValidate($batch['id'], $fields, ['defaults' => ['level' => $level]]);
        $this->assertSame(1, $totals['rejetees']);

        // Avec autorisation : création à l'importation
        $this->mapAndValidate($batch['id'], $fields, ['defaults' => ['level' => $level], 'create_missing' => true]);
        $this->postJson("/api/v1/imports/{$batch['id']}/execute", ['confirm' => true])->assertOk()->assertJsonPath('totals.importees', 1);
        $this->assertDatabaseHas('filieres', ['name' => 'Développement digital', 'level_id' => $level]);
        $this->assertDatabaseHas('training_groups', ['name' => 'DD101']);
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_fichier_non_excel_refuse(): void
    {
        $file = UploadedFile::fake()->createWithContent('malveillant.php', '<?php echo 1;');
        $this->post('/api/v1/imports', ['file' => $file], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_la_consultation_ne_peut_pas_importer(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'consultation']));
        $this->getJson('/api/v1/imports')->assertForbidden();
    }
}
