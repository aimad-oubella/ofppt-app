<?php

/*
 * Configuration du moteur d'importation Excel.
 * Pour ajouter un champ importable : ajoutez-le dans « fields » puis traitez-le dans
 * App\Services\Import\RowProcessor. Aucun nom de colonne du fichier source n'est supposé :
 * « aliases » ne sert qu'à PRÉ-SÉLECTIONNER une colonne, que l'utilisateur peut toujours changer.
 */
return [
    'preview_rows' => 20,
    'chunk_size' => 500,
    'max_upload_kb' => 20480,

    // Imposés par la base de données (colonnes NOT NULL). Les autres champs sont facultatifs
    // sauf si l'utilisateur les déclare obligatoires dans l'assistant.
    'always_required' => ['last_name', 'first_name'],

    // Combinaisons de champs utilisées pour détecter les doublons (toutes les valeurs doivent être renseignées).
    'duplicate_keys' => [
        ['cef'],
        ['registration_number'],
        ['matricule'],
        ['last_name', 'first_name', 'birth_date'],
    ],

    'fields' => [
        'cef' => ['label' => 'CEF', 'group' => 'Identification', 'type' => 'string', 'max' => 30, 'aliases' => ['cef']],
        'registration_number' => ['label' => "Numéro d'inscription", 'group' => 'Identification', 'type' => 'string', 'max' => 50, 'aliases' => ["n° d'inscription", 'n° inscription', 'numero inscription']],
        'matricule' => ['label' => 'Matricule', 'group' => 'Identification', 'type' => 'string', 'max' => 50, 'aliases' => []],
        'last_name' => ['label' => 'Nom', 'group' => 'Identité', 'type' => 'string', 'max' => 255, 'aliases' => ['nom de famille']],
        'first_name' => ['label' => 'Prénom', 'group' => 'Identité', 'type' => 'string', 'max' => 255, 'aliases' => []],
        'name_ar' => ['label' => 'Nom en arabe', 'group' => 'Identité', 'type' => 'string', 'max' => 255, 'aliases' => []],
        'birth_date' => ['label' => 'Date de naissance', 'group' => 'Identité', 'type' => 'date', 'aliases' => ['date naissance', 'naissance']],
        'birth_place' => ['label' => 'Lieu de naissance', 'group' => 'Identité', 'type' => 'string', 'max' => 255, 'aliases' => []],
        'gender' => ['label' => 'Sexe', 'group' => 'Identité', 'type' => 'gender', 'aliases' => ['genre']],
        'cin' => ['label' => 'CIN', 'group' => 'Identité', 'type' => 'string', 'max' => 30, 'aliases' => []],
        'admin_status' => ['label' => 'Statut administratif', 'group' => 'Identité', 'type' => 'string', 'max' => 50, 'aliases' => []],
        'establishment' => ['label' => 'Établissement', 'group' => 'Formation', 'type' => 'ref', 'aliases' => []],
        'level' => ['label' => 'Niveau', 'group' => 'Formation', 'type' => 'ref', 'aliases' => []],
        'filiere' => ['label' => 'Filière', 'group' => 'Formation', 'type' => 'ref', 'aliases' => []],
        'group' => ['label' => 'Groupe', 'group' => 'Formation', 'type' => 'ref', 'aliases' => []],
        'promotion' => ['label' => 'Promotion', 'group' => 'Formation', 'type' => 'ref', 'aliases' => []],
        'training_year' => ['label' => 'Année de formation', 'group' => 'Formation', 'type' => 'int', 'aliases' => []],
        'category' => ['label' => 'Catégorie du candidat', 'group' => 'Catégorie', 'type' => 'ref', 'aliases' => ['categorie']],
        'graduation_year' => ['label' => "Année d'obtention du diplôme", 'group' => 'Lauréat', 'type' => 'int', 'aliases' => []],
    ],
];
