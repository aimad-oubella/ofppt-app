<?php

namespace App\Services\Import;

use App\Models\Graduation;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Trainee;
use App\Services\XlsxExporter;
use App\Support\Audit;
use App\Support\Text;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportService
{
    public function __construct(private SpreadsheetReader $reader)
    {
    }

    public static function defaultOptions(): array
    {
        return [
            'defaults' => [],
            'required_fields' => [],
            'create_missing' => false,
            'on_duplicate' => 'skip', // skip = ignorer ; complete = compléter uniquement les champs vides
            'as_laureates' => false,
        ];
    }

    /** Lit la feuille choisie et recrée les lignes de l'importation. Réinitialise le mapping des colonnes. */
    public function parse(ImportBatch $batch, int $sheetIndex = 0, int $headerRow = 1): void
    {
        $path = Storage::disk('local')->path($batch->stored_path);
        $data = $this->reader->read($path, $sheetIndex);
        $rows = $data['rows'];

        if ($headerRow < 1 || ! isset($rows[$headerRow - 1])) {
            throw new \RuntimeException("La ligne d'en-têtes n°$headerRow n'existe pas dans cette feuille.");
        }

        $headers = [];
        foreach (array_values($rows[$headerRow - 1]) as $i => $c) {
            $headers[] = Text::clean($c) ?? 'Colonne '.($i + 1);
        }
        $width = count($headers);

        DB::transaction(function () use ($batch, $rows, $headerRow, $width) {
            $batch->rows()->delete();
            $chunk = [];
            $now = now();
            foreach (array_slice($rows, $headerRow, null, true) as $i => $r) {
                $cells = array_pad(array_slice(array_values($r), 0, $width), $width, null);
                if (collect($cells)->every(fn ($c) => Text::clean($c) === null)) {
                    continue;
                }
                $chunk[] = [
                    'import_batch_id' => $batch->id,
                    'row_number' => $i + 1,
                    'raw_data' => json_encode(['cells' => $cells, 'overrides' => new \stdClass()], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                    'status' => 'en_attente',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if (count($chunk) >= config('import.chunk_size')) {
                    ImportRow::insert($chunk);
                    $chunk = [];
                }
            }
            if ($chunk) {
                ImportRow::insert($chunk);
            }
        });

        $old = $batch->mapping ?? [];
        $batch->mapping = [
            'sheets' => $data['sheets'],
            'sheet' => $sheetIndex,
            'header_row' => $headerRow,
            'headers' => $headers,
            'fields' => $this->suggestMapping($headers),
            'options' => $old['options'] ?? self::defaultOptions(),
        ];
        $batch->status = 'televerse';
        $batch->totals = null;
        $batch->save();
    }

    /** Pré-sélection indicative : compare les en-têtes aux libellés des champs. L'utilisateur peut tout modifier. */
    public function suggestMapping(array $headers): array
    {
        $fields = [];
        $used = [];
        foreach (config('import.fields') as $key => $def) {
            $candidates = array_map(fn ($x) => Text::key($x), array_merge([$def['label']], $def['aliases'] ?? []));
            $fields[$key] = null;
            foreach ($headers as $i => $h) {
                if (isset($used[$i])) {
                    continue;
                }
                if (in_array(Text::key($h), $candidates, true)) {
                    $fields[$key] = $i;
                    $used[$i] = true;
                    break;
                }
            }
        }

        return $fields;
    }

    /** Valide toutes les lignes sans rien écrire en base. */
    public function validateAll(ImportBatch $batch): array
    {
        $proc = new RowProcessor($batch->mapping ?? []);
        $seen = [];
        $warnings = 0;
        $noCategory = 0;

        $batch->rows()->where('status', '!=', 'ignoree')->chunkById(config('import.chunk_size'), function ($rows) use ($proc, &$seen, &$warnings, &$noCategory) {
            foreach ($rows as $row) {
                $r = $proc->process($row, $seen, false);
                if ($r['status'] === 'valide') {
                    $proc->register($r, $seen, $row->row_number);
                }
                if ($r['warnings']) {
                    $warnings++;
                }
                if ($r['flags']['category_missing'] ?? false) {
                    $noCategory++;
                }
                $row->update(['status' => $r['status'], 'errors' => $this->messages($r)]);
            }
        });

        $totals = $this->countByStatus($batch) + ['avertissements' => $warnings, 'sans_categorie' => $noCategory];
        $batch->update(['status' => 'valide', 'totals' => $totals]);

        return $totals;
    }

    /** Revalide une seule ligne (après correction). Les doublons internes au fichier sont revérifiés à la validation complète. */
    public function validateRow(ImportBatch $batch, ImportRow $row): ImportRow
    {
        $proc = new RowProcessor($batch->mapping ?? []);
        $r = $proc->process($row, [], false);
        $row->update(['status' => $r['status'], 'errors' => $this->messages($r)]);
        $batch->update(['totals' => $this->countByStatus($batch) + collect($batch->totals ?? [])->only(['avertissements', 'sans_categorie'])->all()]);

        return $row->fresh();
    }

    /** Écrit les lignes valides en base. Une ligne en erreur n'empêche pas les autres. */
    public function execute(ImportBatch $batch): array
    {
        $proc = new RowProcessor($batch->mapping ?? []);
        $opts = $batch->mapping['options'] ?? [];
        $onDuplicate = $opts['on_duplicate'] ?? 'skip';
        $asLaureates = (bool) ($opts['as_laureates'] ?? false);
        $done = ['importees' => 0, 'mises_a_jour' => 0, 'doublons_ignores' => 0, 'erreurs_execution' => 0];

        $batch->rows()->whereIn('status', ['valide', 'doublon'])->chunkById(200, function ($rows) use ($proc, $batch, $onDuplicate, $asLaureates, &$done) {
            foreach ($rows as $row) {
                try {
                    DB::transaction(function () use ($proc, $batch, $row, $onDuplicate, $asLaureates, &$done) {
                        $r = $proc->process($row, [], false);

                        $needsCreation = $r['pending'] && $r['status'] !== 'rejetee'
                            && ! ($r['status'] === 'doublon' && $onDuplicate === 'skip');
                        if ($needsCreation) {
                            $r = $proc->process($row, [], true);
                        }

                        if ($r['status'] === 'rejetee') {
                            $row->update(['status' => 'rejetee', 'errors' => $this->messages($r)]);

                            return;
                        }

                        if ($r['status'] === 'doublon') {
                            $existingId = $r['duplicate']['trainee_id'] ?? null;
                            if ($onDuplicate === 'complete' && $existingId) {
                                $this->completeExisting($existingId, $r['data'], $asLaureates);
                                $row->update(['status' => 'mise_a_jour', 'trainee_id' => $existingId, 'errors' => $this->messages($r)]);
                                $done['mises_a_jour']++;
                            } else {
                                $row->update(['status' => 'ignoree', 'errors' => $this->messages($r)]);
                                $done['doublons_ignores']++;
                            }

                            return;
                        }

                        $trainee = Trainee::create($r['data']['trainee'] + ['import_batch_id' => $batch->id]);
                        if ($r['data']['enrollment']) {
                            $trainee->enrollments()->create($r['data']['enrollment']);
                        }
                        if ($asLaureates && $r['data']['graduation']) {
                            $trainee->graduations()->create($r['data']['graduation']);
                        }
                        $row->update(['status' => 'importee', 'trainee_id' => $trainee->id, 'errors' => $this->messages($r)]);
                        $done['importees']++;
                    });
                } catch (\Throwable $e) {
                    Log::error('Import : erreur à la ligne '.$row->row_number, ['exception' => $e->getMessage()]);
                    $row->update(['status' => 'rejetee', 'errors' => [
                        'errors' => ["Erreur technique lors de l'enregistrement : ".$e->getMessage()],
                        'warnings' => [], 'duplicate' => null, 'flags' => [],
                    ]]);
                    $done['erreurs_execution']++;
                }
            }
        });

        $totals = $this->countByStatus($batch) + [
            'avertissements' => $batch->totals['avertissements'] ?? 0,
            'sans_categorie' => Trainee::where('import_batch_id', $batch->id)->whereNull('category_id')->count(),
        ] + $done;
        $batch->update(['status' => 'importe', 'totals' => $totals]);
        Audit::log('import.executed', $batch, [], $totals);

        return $totals;
    }

    /** Complète uniquement les champs vides du dossier existant. Rien n'est écrasé. */
    private function completeExisting(int $traineeId, array $data, bool $asLaureates): void
    {
        $t = Trainee::withTrashed()->findOrFail($traineeId);
        $fill = [];
        foreach ($data['trainee'] as $k => $val) {
            if ($val !== null && $t->getAttribute($k) === null) {
                $fill[$k] = $val;
            }
        }
        if ($fill) {
            $t->update($fill);
        }
        if ($data['enrollment']) {
            $t->applyEnrollment($data['enrollment']);
        }
        if ($asLaureates && $data['graduation']) {
            $g = $data['graduation'];
            $exists = Graduation::where('trainee_id', $t->id)
                ->where('filiere_id', $g['filiere_id'])
                ->where('promotion_id', $g['promotion_id'])
                ->exists();
            if (! $exists) {
                $t->graduations()->create($g);
            }
        }
    }

    private function messages(array $r): array
    {
        return [
            'errors' => $r['errors'],
            'warnings' => $r['warnings'],
            'duplicate' => $r['duplicate'],
            'flags' => $r['flags'],
        ];
    }

    public function countByStatus(ImportBatch $batch): array
    {
        $c = $batch->rows()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return [
            'total' => (int) $c->sum(),
            'valides' => (int) ($c['valide'] ?? 0),
            'doublons' => (int) ($c['doublon'] ?? 0),
            'rejetees' => (int) ($c['rejetee'] ?? 0),
            'ignorees' => (int) ($c['ignoree'] ?? 0),
            'importees' => (int) ($c['importee'] ?? 0),
            'mises_a_jour' => (int) ($c['mise_a_jour'] ?? 0),
        ];
    }

    public function presentRow(ImportRow $row, RowProcessor $proc): array
    {
        $overrides = $row->raw_data['overrides'] ?? [];

        return [
            'id' => $row->id,
            'row_number' => $row->row_number,
            'status' => $row->status,
            'values' => $proc->rawValues($row),
            'cells' => $row->raw_data['cells'] ?? [],
            'overrides' => $overrides ?: new \stdClass(),
            'messages' => $row->errors ?? ['errors' => [], 'warnings' => [], 'duplicate' => null],
            'trainee_id' => $row->trainee_id,
        ];
    }

    public function presentBatch(ImportBatch $batch): array
    {
        $preview = $batch->rows()->orderBy('id')->limit(config('import.preview_rows'))->get()
            ->map(fn ($r) => ['row_number' => $r->row_number, 'cells' => $r->raw_data['cells'] ?? []]);

        return [
            'id' => $batch->id,
            'original_name' => $batch->original_name,
            'status' => $batch->status,
            'mapping' => $batch->mapping,
            'totals' => $batch->totals,
            'row_count' => $batch->rows()->count(),
            'preview' => $preview,
            'created_at' => $batch->created_at,
        ];
    }

    public function report(ImportBatch $batch): array
    {
        $proc = new RowProcessor($batch->mapping ?? []);
        $rows = $batch->rows()->whereIn('status', ['rejetee', 'doublon', 'ignoree', 'mise_a_jour'])->orderBy('id')->limit(1000)->get()
            ->map(fn ($r) => $this->presentRow($r, $proc));

        return [
            'batch' => ['id' => $batch->id, 'original_name' => $batch->original_name, 'status' => $batch->status, 'created_at' => $batch->created_at],
            'totals' => $batch->totals,
            'rejected' => $rows->where('status', 'rejetee')->values(),
            'duplicates' => $rows->filter(fn ($r) => ! empty($r['messages']['duplicate']))->values(),
            'without_category' => Trainee::where('import_batch_id', $batch->id)->whereNull('category_id')->count(),
        ];
    }

    /** Rapport Excel : toutes les lignes non importées avec leurs motifs, plus les cellules d'origine. */
    public function exportReport(ImportBatch $batch)
    {
        $headers = $batch->mapping['headers'] ?? [];
        $statusLabels = [
            'rejetee' => 'Rejetée', 'doublon' => 'Doublon', 'ignoree' => 'Ignorée',
            'mise_a_jour' => 'Dossier existant complété', 'en_attente' => 'Non validée', 'valide' => 'Valide (non importée)',
        ];

        $rows = (function () use ($batch, $statusLabels) {
            foreach ($batch->rows()->where('status', '!=', 'importee')->orderBy('id')->cursor() as $row) {
                $m = $row->errors ?? [];
                $msgs = array_merge($m['errors'] ?? [], $m['warnings'] ?? [], isset($m['duplicate']['reason']) ? [$m['duplicate']['reason']] : []);
                yield array_merge([$row->row_number, $statusLabels[$row->status] ?? $row->status, implode(' | ', $msgs)], $row->raw_data['cells'] ?? []);
            }
        })();

        return XlsxExporter::download(
            array_merge(['Ligne', 'Statut', 'Motifs'], $headers),
            $rows,
            'rapport-importation-'.$batch->id.'.xlsx'
        );
    }
}
