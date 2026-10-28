<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Données de référence issues du cahier des charges uniquement.
 * Aucun établissement, filière, groupe ni stagiaire fictif n'est créé.
 */
class ReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        foreach (['Spécialisation', 'Qualification', 'Technicien', 'Technicien Spécialisé'] as $i => $name) {
            DB::table('levels')->updateOrInsert(['name' => $name], ['sort_order' => $i + 1, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        $categories = ['regulier' => 'Candidat régulier', 'libre' => 'Candidat libre'];
        foreach ($categories as $code => $label) {
            DB::table('candidate_categories')->updateOrInsert(['code' => $code], ['label' => $label, 'created_at' => $now, 'updated_at' => $now]);
        }

        $statuses = [
            ['a_preparer', 'À préparer', 'secondary'],
            ['en_verification', 'En cours de vérification', 'primary'],
            ['incomplet', 'Incomplet', 'warning'],
            ['rejete', 'Rejeté', 'danger'],
            ['conforme', 'Conforme', 'info'],
            ['valide', 'Validé', 'success'],
        ];
        foreach ($statuses as $i => [$code, $label, $color]) {
            DB::table('checklist_statuses')->updateOrInsert(['code' => $code], ['label' => $label, 'color' => $color, 'sort_order' => $i + 1, 'created_at' => $now, 'updated_at' => $now]);
        }

        foreach (['attestation' => 'Attestation de formation', 'checklist' => 'Check-list de vérification des diplômes', 'emargement' => "Liste d'émargement"] as $code => $label) {
            DB::table('document_types')->updateOrInsert(['code' => $code], ['label' => $label, 'created_at' => $now, 'updated_at' => $now]);
        }

        foreach (['Contrôle', 'EFM'] as $label) {
            DB::table('assessment_types')->updateOrInsert(['label' => $label], ['created_at' => $now, 'updated_at' => $now]);
        }

        $this->seedChecklistModels($categories, $now);
    }

    private function seedChecklistModels(array $categories, $now): void
    {
        $regulier = DB::table('candidate_categories')->where('code', 'regulier')->value('id');
        $libre = DB::table('candidate_categories')->where('code', 'libre')->value('id');

        DB::table('checklist_models')->updateOrInsert(['category_id' => $regulier], [
            'name' => 'Check-list diplôme — candidat régulier',
            'header_label' => 'Catégorie : Cours Du Jour/Candidat Régulier',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('checklist_models')->updateOrInsert(['category_id' => $libre], [
            'name' => 'Check-list diplôme — candidat libre',
            'header_label' => 'Catégorie : Cours Du Jour/Candidat Libre',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $regulierId = DB::table('checklist_models')->where('category_id', $regulier)->value('id');
        $libreId = DB::table('checklist_models')->where('category_id', $libre)->value('id');

        // Supports cités dans le cahier des charges. Intitulés et colonne
        // « Informations à vérifier » À CONFIRMER d'après Check_List_Model.docx.
        $supports = [
            'PV de fin de formation',
            "Pièce d'identité",
            'Justificatifs de scolarité ou diplôme du niveau d\'origine',
            'Diplôme',
        ];

        foreach ([$regulierId, $libreId] as $modelId) {
            foreach ($supports as $i => $label) {
                DB::table('checklist_support_definitions')->updateOrInsert(
                    ['checklist_model_id' => $modelId, 'label' => $label],
                    ['sort_order' => $i + 1, 'info_to_verify' => null, 'needs_confirmation' => true, 'created_at' => $now, 'updated_at' => $now]
                );
            }
        }

        // Spécifique aux candidats libres (exigence explicite du cahier des charges).
        DB::table('checklist_support_definitions')->updateOrInsert(
            ['checklist_model_id' => $libreId, 'label' => 'Ancien PV de fin de formation'],
            [
                'sort_order' => count($supports) + 1,
                'info_to_verify' => json_encode(
                    ['Nom et Prénom', "N° d'inscription", 'Filière', 'Non éliminé', 'Année'],
                    JSON_UNESCAPED_UNICODE
                ),
                'needs_confirmation' => false,
                'created_at' => $now, 'updated_at' => $now,
            ]
        );
    }
}
