<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Services\Import\ImportService;
use App\Services\Import\RowProcessor;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ImportController extends Controller
{
    public function __construct(private ImportService $service)
    {
    }

    public function meta(): JsonResponse
    {
        $fields = collect(config('import.fields'))->map(fn ($d, $k) => [
            'key' => $k, 'label' => $d['label'], 'group' => $d['group'], 'type' => $d['type'],
        ])->values();

        return response()->json([
            'fields' => $fields,
            'always_required' => config('import.always_required'),
            'default_options' => ImportService::defaultOptions(),
        ]);
    }

    public function index(): JsonResponse
    {
        return response()->json(
            ImportBatch::query()->latest('id')->limit(30)->get(['id', 'original_name', 'status', 'totals', 'created_at'])
        );
    }

    public function show(ImportBatch $import): JsonResponse
    {
        return response()->json($this->service->presentBatch($import));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xls,xlsx', 'max:'.config('import.max_upload_kb')],
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['xls', 'xlsx'], true)) {
            throw ValidationException::withMessages(['file' => ['Le fichier doit être au format .xls ou .xlsx.']]);
        }

        $path = $file->storeAs('imports', Str::uuid().'.'.$ext, 'local');
        $batch = ImportBatch::create([
            'user_id' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $path,
            'status' => 'televerse',
        ]);

        try {
            $this->service->parse($batch, 0, 1);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            $batch->delete();
            throw ValidationException::withMessages(['file' => ['Fichier illisible : '.$e->getMessage()]]);
        }

        Audit::log('import.uploaded', $batch, [], ['file' => $batch->original_name]);

        return response()->json($this->service->presentBatch($batch->fresh()), 201);
    }

    /** Change la feuille ou la ligne d'en-têtes et relit le fichier. */
    public function source(Request $request, ImportBatch $import): JsonResponse
    {
        $this->ensureEditable($import);
        $data = $request->validate([
            'sheet' => ['required', 'integer', 'min:0'],
            'header_row' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $this->service->parse($import, $data['sheet'], $data['header_row']);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['header_row' => [$e->getMessage()]]);
        }

        return response()->json($this->service->presentBatch($import->fresh()));
    }

    public function mapping(Request $request, ImportBatch $import): JsonResponse
    {
        $this->ensureEditable($import);
        $count = count($import->mapping['headers'] ?? []);

        $data = $request->validate([
            'fields' => ['required', 'array'],
            'fields.*' => ['nullable', 'integer', 'min:0', 'max:'.max(0, $count - 1)],
            'options' => ['required', 'array'],
            'options.defaults' => ['nullable', 'array'],
            'options.defaults.establishment' => ['nullable', 'integer', 'exists:establishments,id'],
            'options.defaults.level' => ['nullable', 'integer', 'exists:levels,id'],
            'options.defaults.filiere' => ['nullable', 'integer', 'exists:filieres,id'],
            'options.defaults.group' => ['nullable', 'integer', 'exists:training_groups,id'],
            'options.defaults.promotion' => ['nullable', 'integer', 'exists:promotions,id'],
            'options.defaults.category' => ['nullable', 'integer', 'exists:candidate_categories,id'],
            'options.defaults.training_year' => ['nullable', 'integer', 'between:1,9'],
            'options.defaults.graduation_year' => ['nullable', 'integer', 'between:1970,2100'],
            'options.required_fields' => ['nullable', 'array'],
            'options.required_fields.*' => ['string', Rule::in(array_keys(config('import.fields')))],
            'options.create_missing' => ['boolean'],
            'options.on_duplicate' => ['required', Rule::in(['skip', 'complete'])],
            'options.as_laureates' => ['boolean'],
        ]);

        $fields = array_intersect_key($data['fields'], config('import.fields'));
        $options = $data['options'];
        $options['defaults'] = array_filter($options['defaults'] ?? [], fn ($v) => $v !== null && $v !== '');
        $options['required_fields'] = array_values($options['required_fields'] ?? []);

        $mapping = $import->mapping;
        $mapping['fields'] = $fields;
        $mapping['options'] = $options;
        $import->update(['mapping' => $mapping, 'status' => 'mappe', 'totals' => null]);
        $import->rows()->where('status', '!=', 'ignoree')->update(['status' => 'en_attente', 'errors' => null]);

        return response()->json($this->service->presentBatch($import->fresh()));
    }

    public function validateBatch(ImportBatch $import): JsonResponse
    {
        $this->ensureEditable($import);
        if (empty($import->mapping['fields']) || ! in_array($import->status, ['mappe', 'valide'], true)) {
            return response()->json(['message' => "Enregistrez d'abord l'association des colonnes."], 422);
        }

        $this->service->validateAll($import);

        return response()->json($this->service->presentBatch($import->fresh()));
    }

    public function rows(Request $request, ImportBatch $import): JsonResponse
    {
        $q = $import->rows()->orderBy('id');
        if ($request->filled('status')) {
            $q->where('status', $request->query('status'));
        }
        $page = $q->paginate(min(max((int) $request->query('per_page', 25), 1), 100));

        $proc = new RowProcessor($import->mapping ?? []);
        $page->getCollection()->transform(fn ($r) => $this->service->presentRow($r, $proc));

        return response()->json($page);
    }

    /** Correction manuelle d'une ligne : les valeurs saisies remplacent celles du fichier, sans le modifier. */
    public function updateRow(Request $request, ImportBatch $import, ImportRow $row): JsonResponse
    {
        $this->ensureEditable($import);
        $this->ensureOwnRow($import, $row);

        $request->validate(['overrides' => ['required', 'array']]);
        $allowed = array_keys(config('import.fields'));
        $new = collect($request->input('overrides'))->only($allowed)->map(fn ($v) => is_scalar($v) ? (string) $v : null)->all();

        $raw = $row->raw_data;
        $raw['overrides'] = array_merge($raw['overrides'] ?? [], $new);
        $row->update(['raw_data' => $raw]);

        $row = $this->service->validateRow($import, $row);

        return response()->json($this->service->presentRow($row, new RowProcessor($import->mapping ?? [])));
    }

    public function ignoreRow(Request $request, ImportBatch $import, ImportRow $row): JsonResponse
    {
        $this->ensureEditable($import);
        $this->ensureOwnRow($import, $row);
        $data = $request->validate(['ignore' => ['required', 'boolean']]);

        if ($data['ignore']) {
            $row->update(['status' => 'ignoree']);
            $import->update(['totals' => $this->service->countByStatus($import) + collect($import->totals ?? [])->only(['avertissements', 'sans_categorie'])->all()]);
        } else {
            $row->update(['status' => 'en_attente']);
            $row = $this->service->validateRow($import, $row);
        }

        return response()->json($this->service->presentRow($row->fresh(), new RowProcessor($import->mapping ?? [])));
    }

    public function execute(Request $request, ImportBatch $import): JsonResponse
    {
        $request->validate(['confirm' => ['required', 'accepted']]);
        if ($import->status !== 'valide') {
            return response()->json(['message' => 'Lancez la validation avant d\'importer.'], 422);
        }
        if ($import->rows()->where('status', 'en_attente')->exists()) {
            return response()->json(['message' => 'Certaines lignes ne sont pas validées : relancez la validation.'], 422);
        }

        $totals = $this->service->execute($import);

        return response()->json(['totals' => $totals, 'batch' => $this->service->presentBatch($import->fresh())]);
    }

    public function report(ImportBatch $import): JsonResponse
    {
        return response()->json($this->service->report($import));
    }

    public function exportReport(ImportBatch $import)
    {
        return $this->service->exportReport($import);
    }

    private function ensureEditable(ImportBatch $import): void
    {
        abort_if($import->status === 'importe', 409, 'Cette importation est terminée et ne peut plus être modifiée.');
    }

    private function ensureOwnRow(ImportBatch $import, ImportRow $row): void
    {
        abort_unless($row->import_batch_id === $import->id, 404);
    }
}
