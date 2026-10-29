<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\ReferenceRegistry;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** CRUD générique des référentiels décrits dans ReferenceRegistry. */
class ReferenceController extends Controller
{
    private function definition(string $resource): array
    {
        $all = ReferenceRegistry::all();
        abort_unless(isset($all[$resource]), 404, 'Référentiel inconnu.');

        return $all[$resource];
    }

    public function schema(): JsonResponse
    {
        return response()->json(collect(ReferenceRegistry::all())->map(fn ($d, $k) => [
            'key' => $k,
            'label' => $d['label'],
            'columns' => $d['columns'],
            'deletable' => $d['deletable'] ?? true,
        ])->values());
    }

    public function index(string $resource): JsonResponse
    {
        $d = $this->definition($resource);

        return response()->json($d['model']::query()->orderBy($d['order'])->get());
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        $d = $this->definition($resource);
        $data = $request->validate(($d['rules'])(null));
        $row = $d['model']::create($data);
        Audit::log("reference.$resource.created", $row, [], $data);

        return response()->json($row, 201);
    }

    public function update(Request $request, string $resource, int $id): JsonResponse
    {
        $d = $this->definition($resource);
        $row = $d['model']::findOrFail($id);
        $old = $row->only(array_column($d['columns'], 'key'));
        $data = $request->validate(($d['rules'])($id));
        $row->update($data);
        Audit::log("reference.$resource.updated", $row, $old, $data);

        return response()->json($row->fresh());
    }

    public function destroy(string $resource, int $id): JsonResponse
    {
        $d = $this->definition($resource);
        if (($d['deletable'] ?? true) === false) {
            return response()->json(['message' => 'Cet élément ne peut pas être supprimé : désactivez-le.'], 405);
        }

        $row = $d['model']::findOrFail($id);
        try {
            $row->delete();
        } catch (QueryException $e) {
            return response()->json(['message' => 'Suppression impossible : cet élément est utilisé. Désactivez-le plutôt.'], 409);
        }
        Audit::log("reference.$resource.deleted", $row);

        return response()->json(['message' => 'Élément supprimé.']);
    }
}
