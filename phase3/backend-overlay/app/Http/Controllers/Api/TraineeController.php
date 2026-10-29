<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TraineeRequest;
use App\Models\Trainee;
use App\Services\XlsxExporter;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TraineeController extends Controller
{
    private const SORTABLE = ['last_name', 'first_name', 'cef', 'created_at'];

    private function filters(Request $request): array
    {
        return $request->only([
            'q', 'establishment_id', 'category_id', 'uncategorized', 'level_id', 'filiere_id',
            'group_id', 'promotion_id', 'training_year', 'archived',
        ]);
    }

    private function listQuery(Request $request)
    {
        $sort = in_array($request->query('sort'), self::SORTABLE, true) ? $request->query('sort') : 'last_name';
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        return Trainee::query()
            ->filter($this->filters($request))
            ->with([
                'category:id,code,label',
                'establishment:id,name',
                'latestEnrollment.level:id,name',
                'latestEnrollment.filiere:id,name',
                'latestEnrollment.group:id,name',
                'latestEnrollment.promotion:id,label',
            ])
            ->orderBy($sort, $dir)
            ->orderBy('first_name');
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);

        return response()->json($this->listQuery($request)->paginate($perPage));
    }

    public function show(Trainee $trainee): JsonResponse
    {
        return response()->json($this->detail($trainee->id));
    }

    public function store(TraineeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $enrollment = $data['enrollment'] ?? null;
        unset($data['enrollment']);

        $trainee = DB::transaction(function () use ($data, $enrollment) {
            $t = Trainee::create($data);
            if ($enrollment) {
                $t->applyEnrollment($enrollment);
            }

            return $t;
        });
        Audit::log('trainee.created', $trainee, [], ['cef' => $trainee->cef, 'category_id' => $trainee->category_id]);

        return response()->json($this->detail($trainee->id), 201);
    }

    public function update(TraineeRequest $request, Trainee $trainee): JsonResponse
    {
        $data = $request->validated();
        $enrollment = $data['enrollment'] ?? null;
        unset($data['enrollment']);
        $oldCategory = $trainee->category_id;

        DB::transaction(function () use ($trainee, $data, $enrollment) {
            $trainee->update($data);
            if ($enrollment) {
                $trainee->applyEnrollment($enrollment);
            }
        });

        if ($oldCategory !== $trainee->category_id) {
            Audit::log('trainee.category_changed', $trainee, ['category_id' => $oldCategory], ['category_id' => $trainee->category_id]);
        }

        return response()->json($this->detail($trainee->id));
    }

    /** Archivage (suppression logique) : le dossier reste restaurable. */
    public function destroy(Trainee $trainee): JsonResponse
    {
        $trainee->delete();
        Audit::log('trainee.archived', $trainee);

        return response()->json(['message' => 'Dossier archivé.']);
    }

    public function restore(Trainee $trainee): JsonResponse
    {
        $trainee->restore();
        Audit::log('trainee.restored', $trainee);

        return response()->json($this->detail($trainee->id));
    }

    public function export(Request $request)
    {
        $rows = (function () use ($request) {
            $n = 0;
            foreach ($this->listQuery($request)->lazy(500) as $t) {
                if (++$n > 50000) {
                    break;
                }
                $e = $t->latestEnrollment;
                yield [
                    $t->last_name, $t->first_name, $t->cef, $t->registration_number, $t->matricule,
                    $t->birth_date?->format('d/m/Y'), $t->birth_place, $t->cin,
                    $t->establishment?->name, $t->category?->label,
                    $e?->level?->name, $e?->filiere?->name, $e?->group?->name, $e?->promotion?->label, $e?->training_year,
                ];
            }
        })();

        return XlsxExporter::download(
            ['Nom', 'Prénom', 'CEF', "N° d'inscription", 'Matricule', 'Date de naissance', 'Lieu de naissance', 'CIN',
                'Établissement', 'Catégorie', 'Niveau', 'Filière', 'Groupe', 'Promotion', 'Année de formation'],
            $rows,
            'stagiaires.xlsx'
        );
    }

    private function detail(int $id): Trainee
    {
        return Trainee::withTrashed()->with([
            'establishment:id,name',
            'category:id,code,label',
            'enrollments' => fn ($q) => $q->latest('id')->with(['level:id,name', 'filiere:id,name', 'group:id,name', 'promotion:id,label']),
            'graduations' => fn ($q) => $q->latest('id')->with(['filiere:id,name', 'promotion:id,label', 'category:id,label']),
        ])->findOrFail($id);
    }
}
