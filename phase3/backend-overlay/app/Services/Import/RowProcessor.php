<?php

namespace App\Services\Import;

use App\Models\Filiere;
use App\Models\ImportRow;
use App\Models\Promotion;
use App\Models\Trainee;
use App\Models\TrainingGroup;
use App\Support\Text;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Transforme une ligne Excel en données prêtes à enregistrer, selon le mapping choisi par l'utilisateur.
 * Aucune écriture n'a lieu en mode « validation » ($persist = false).
 */
class RowProcessor
{
    /** Identifiant provisoire d'un élément qui sera créé à l'importation. */
    public const PENDING = 0;

    private array $mapCache = [];
    private ?array $categoryIds = null;
    private array $fields;
    private array $options;
    private array $defaults;
    private array $required;

    public function __construct(array $mapping)
    {
        $this->fields = $mapping['fields'] ?? [];
        $this->options = $mapping['options'] ?? [];
        $this->defaults = $this->options['defaults'] ?? [];
        $this->required = array_values(array_unique(array_merge(
            config('import.always_required'),
            $this->options['required_fields'] ?? []
        )));
    }

    /** Valeurs textuelles de la ligne par champ (cellules + corrections manuelles), sans valeurs par défaut. */
    public function rawValues(ImportRow $row): array
    {
        $cells = $row->raw_data['cells'] ?? [];
        $overrides = $row->raw_data['overrides'] ?? [];
        $out = [];

        foreach (array_keys(config('import.fields')) as $key) {
            if (array_key_exists($key, $overrides)) {
                $out[$key] = Text::clean($overrides[$key]);
                continue;
            }
            $idx = $this->fields[$key] ?? null;
            $out[$key] = ($idx === null || $idx === '') ? null : Text::clean($cells[(int) $idx] ?? null);
        }

        return $out;
    }

    /**
     * @param  array<string,int>  $seen  signatures déjà vues dans le fichier (signature => n° de ligne)
     * @return array{status:string,errors:array,warnings:array,duplicate:?array,flags:array,pending:bool,signatures:array,data:?array}
     */
    public function process(ImportRow $row, array $seen = [], bool $persist = false): array
    {
        $defs = config('import.fields');
        $raw = $this->rawValues($row);
        $errors = [];
        $warnings = [];
        $pending = false;
        $flags = ['category_missing' => false];
        $v = [];

        // 1. Champs obligatoires déclarés
        foreach ($this->required as $key) {
            if (isset($defs[$key]) && $raw[$key] === null && ! $this->hasDefault($key)) {
                $errors[] = 'Champ obligatoire manquant : '.$defs[$key]['label'].'.';
            }
        }

        // 2. Textes
        foreach (['cef', 'registration_number', 'matricule', 'last_name', 'first_name', 'name_ar', 'birth_place', 'cin', 'admin_status'] as $key) {
            $val = $raw[$key];
            $max = $defs[$key]['max'] ?? 255;
            if ($val !== null && mb_strlen($val) > $max) {
                $errors[] = $defs[$key]['label']." trop long (maximum $max caractères).";
                $val = null;
            }
            $v[$key] = $val;
        }

        // 3. Dates, sexe, années
        $v['birth_date'] = $this->safe($raw['birth_date'], fn ($x) => ValueNormalizer::date($x), $errors);
        $v['gender'] = $this->safe($raw['gender'], fn ($x) => ValueNormalizer::gender($x), $errors);
        $trainingYear = $this->safe(
            $raw['training_year'] ?? $this->defaultText('training_year'),
            fn ($x) => ValueNormalizer::integerInRange($x, 1, 9, 'Année de formation'),
            $errors
        );
        $graduationYear = $this->safe(
            $raw['graduation_year'] ?? $this->defaultText('graduation_year'),
            fn ($x) => ValueNormalizer::integerInRange($x, 1970, (int) date('Y') + 1, "Année d'obtention"),
            $errors
        );

        // 4. Référentiels
        $createMissing = (bool) ($this->options['create_missing'] ?? false);

        $est = $this->ref('establishments', ['name', 'code'], ['deleted_at' => null], $raw['establishment'],
            $this->defaults['establishment'] ?? null, 'Établissement', $errors, $warnings, $pending, null, $persist);
        if ($est === null && $raw['establishment'] === null) {
            $errors[] = "Établissement non indiqué : choisissez une colonne ou un établissement par défaut.";
        }

        $level = $this->ref('levels', ['name'], [], $raw['level'],
            $this->defaults['level'] ?? null, 'Niveau', $errors, $warnings, $pending, null, $persist);

        $promotion = null;
        if ($raw['promotion'] !== null || $this->hasDefault('promotion')) {
            if ($est === null) {
                $errors[] = 'Promotion : un établissement valide est nécessaire pour l\'identifier.';
            } else {
                $promotion = $this->ref('promotions', ['label'], ['establishment_id' => $est], $raw['promotion'],
                    $this->defaults['promotion'] ?? null, 'Promotion', $errors, $warnings, $pending,
                    $createMissing ? fn ($val) => Promotion::create(['establishment_id' => $est, 'label' => $val])->id : null,
                    $persist);
            }
        }

        $filiere = null;
        if ($raw['filiere'] !== null || $this->hasDefault('filiere')) {
            if ($est === null) {
                $errors[] = 'Filière : un établissement valide est nécessaire pour l\'identifier.';
            } else {
                $where = ['establishment_id' => $est];
                if ($level) {
                    $where['level_id'] = $level;
                }
                $filiere = $this->ref('filieres', ['name', 'code'], $where, $raw['filiere'],
                    $this->defaults['filiere'] ?? null, 'Filière', $errors, $warnings, $pending,
                    ($createMissing && $level) ? fn ($val) => Filiere::create(['establishment_id' => $est, 'level_id' => $level, 'name' => $val])->id : null,
                    $persist);
            }
        }

        $group = null;
        if ($raw['group'] !== null || $this->hasDefault('group')) {
            if ($raw['group'] === null) {
                $group = (int) $this->defaults['group'];
            } elseif ($filiere === null || $promotion === null) {
                $errors[] = 'Groupe : la filière et la promotion sont nécessaires pour identifier le groupe.';
            } elseif ($filiere === self::PENDING || $promotion === self::PENDING) {
                $group = self::PENDING; // sera créé avec sa filière/promotion
                $pending = true;
            } else {
                $group = $this->ref('training_groups', ['name'], ['filiere_id' => $filiere, 'promotion_id' => $promotion], $raw['group'],
                    null, 'Groupe', $errors, $warnings, $pending,
                    $createMissing ? fn ($val) => TrainingGroup::create([
                        'filiere_id' => $filiere, 'promotion_id' => $promotion, 'name' => $val, 'training_year' => $trainingYear,
                    ])->id : null,
                    $persist);
            }
        }

        $category = $this->category($raw['category'], $this->defaults['category'] ?? null, $errors, $warnings, $flags);

        if ($errors) {
            return $this->result('rejetee', $errors, $warnings, null, $flags, $pending, [], null);
        }

        // 5. Données à enregistrer
        $trainee = $v + ['establishment_id' => $est, 'category_id' => $category];
        $enrollment = [
            'training_group_id' => $group,
            'filiere_id' => $filiere,
            'level_id' => $level,
            'promotion_id' => $promotion,
            'training_year' => $trainingYear,
        ];
        $enrollment = count(array_filter($enrollment, fn ($x) => $x !== null)) ? $enrollment : null;

        $graduation = null;
        if (! empty($this->options['as_laureates'])) {
            $graduation = [
                'filiere_id' => $filiere,
                'promotion_id' => $promotion,
                'category_id' => $category,
                'graduation_year' => $graduationYear,
            ];
            if ($category === null) {
                $warnings[] = 'Lauréat sans catégorie : à confirmer.';
            }
        }
        $data = ['trainee' => $trainee, 'enrollment' => $enrollment, 'graduation' => $graduation];

        // 6. Doublons
        $signatures = $this->signatures($trainee);
        $duplicate = null;
        foreach ($signatures as $s) {
            if (isset($seen[$s['sig']])) {
                $duplicate = [
                    'trainee_id' => null,
                    'row' => $seen[$s['sig']],
                    'reason' => 'Doublon dans le fichier : même '.$s['label'].' que la ligne '.$seen[$s['sig']].'.',
                ];
                break;
            }
            $existing = $this->findExisting($s['combo'], $trainee);
            if ($existing) {
                $duplicate = [
                    'trainee_id' => $existing->id,
                    'row' => null,
                    'reason' => 'Doublon potentiel : même '.$s['label'].' que le dossier n°'.$existing->id
                        .' ('.$existing->last_name.' '.$existing->first_name.')'.($existing->deleted_at ? ' archivé' : '').'.',
                ];
                break;
            }
        }

        return $this->result($duplicate ? 'doublon' : 'valide', [], $warnings, $duplicate, $flags, $pending,
            array_column($signatures, 'sig'), $data);
    }

    /** Mémorise les signatures d'une ligne valide pour détecter les doublons internes au fichier. */
    public function register(array $result, array &$seen, int $rowNumber): void
    {
        foreach ($result['signatures'] as $sig) {
            $seen[$sig] ??= $rowNumber;
        }
    }

    private function result(string $status, array $errors, array $warnings, ?array $dup, array $flags, bool $pending, array $sigs, ?array $data): array
    {
        return [
            'status' => $status, 'errors' => $errors, 'warnings' => $warnings, 'duplicate' => $dup,
            'flags' => $flags, 'pending' => $pending, 'signatures' => $sigs, 'data' => $data,
        ];
    }

    private function safe(?string $value, callable $fn, array &$errors): mixed
    {
        if ($value === null) {
            return null;
        }
        try {
            return $fn($value);
        } catch (InvalidArgumentException $e) {
            $errors[] = $e->getMessage();

            return null;
        }
    }

    private function hasDefault(string $key): bool
    {
        return isset($this->defaults[$key]) && $this->defaults[$key] !== '';
    }

    private function defaultText(string $key): ?string
    {
        return $this->hasDefault($key) ? Text::clean($this->defaults[$key]) : null;
    }

    private function signatures(array $trainee): array
    {
        $labels = config('import.fields');
        $out = [];
        foreach (config('import.duplicate_keys') as $combo) {
            $parts = [];
            foreach ($combo as $f) {
                if (($trainee[$f] ?? null) === null) {
                    continue 2;
                }
                $parts[] = $f === 'birth_date' ? $trainee[$f] : Text::key((string) $trainee[$f]);
            }
            $out[] = [
                'sig' => implode('|', $combo).'#'.implode('|', $parts),
                'combo' => $combo,
                'label' => implode(' + ', array_map(fn ($f) => $labels[$f]['label'] ?? $f, $combo)),
            ];
        }

        return $out;
    }

    private function findExisting(array $combo, array $trainee): ?Trainee
    {
        $q = Trainee::withTrashed();
        foreach ($combo as $f) {
            $q->where($f, $trainee[$f]);
        }

        return $q->first(['id', 'last_name', 'first_name', 'deleted_at']);
    }

    private function category(?string $value, mixed $defaultId, array &$errors, array &$warnings, array &$flags): ?int
    {
        if ($value === null) {
            if ($defaultId !== null && $defaultId !== '') {
                return (int) $defaultId;
            }
            $flags['category_missing'] = true;
            $warnings[] = 'Catégorie non renseignée : à confirmer (régulier ou libre).';

            return null;
        }

        $this->categoryIds ??= DB::table('candidate_categories')->pluck('id', 'code')->all();
        $k = Text::key($value);
        $code = str_contains($k, 'libre') ? 'libre' : (str_contains($k, 'regul') ? 'regulier' : null);
        if ($code === null || ! isset($this->categoryIds[$code])) {
            $errors[] = "Catégorie non reconnue : « $value » (attendu : régulier ou libre).";

            return null;
        }

        return (int) $this->categoryIds[$code];
    }

    /**
     * Résout un texte vers l'identifiant d'un référentiel.
     * Retourne null si introuvable (erreur ajoutée), PENDING si l'élément sera créé à l'importation.
     */
    private function ref(string $table, array $nameFields, array $where, ?string $value, mixed $defaultId,
        string $label, array &$errors, array &$warnings, bool &$pending, ?callable $creator, bool $persist): ?int
    {
        if ($value === null) {
            return ($defaultId !== null && $defaultId !== '') ? (int) $defaultId : null;
        }

        $map = $this->map($table, $nameFields, $where);
        $k = Text::key($value);
        $ids = $map[$k] ?? [];

        if (count($ids) === 1) {
            return $ids[0];
        }
        if (count($ids) > 1) {
            $errors[] = "$label « $value » est ambigu : plusieurs correspondances existent.";

            return null;
        }
        if ($creator) {
            if (! $persist) {
                $warnings[] = "$label « $value » n'existe pas : il sera créé lors de l'importation.";
                $pending = true;

                return self::PENDING;
            }
            $id = (int) $creator($value);
            $this->mapCache[$this->cacheKey($table, $where)][$k] = [$id];

            return $id;
        }

        $errors[] = "$label « $value » est introuvable dans les référentiels.";

        return null;
    }

    private function cacheKey(string $table, array $where): string
    {
        return $table.'|'.json_encode($where);
    }

    private function map(string $table, array $fields, array $where): array
    {
        $ck = $this->cacheKey($table, $where);
        if (! isset($this->mapCache[$ck])) {
            $q = DB::table($table);
            foreach ($where as $col => $val) {
                $q->where($col, $val);
            }
            $map = [];
            foreach ($q->get() as $r) {
                foreach ($fields as $f) {
                    $key = Text::key((string) ($r->$f ?? ''));
                    if ($key !== '') {
                        $map[$key][$r->id] = $r->id;
                    }
                }
            }
            $this->mapCache[$ck] = array_map('array_values', $map);
        }

        return $this->mapCache[$ck];
    }
}
