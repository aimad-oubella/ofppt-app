<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Graduation;
use App\Models\Trainee;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GraduationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Graduation::query()
            ->with(['trainee:id,last_name,first_name,cef', 'filiere:id,name', 'promotion:id,label', 'category:id,label'])
            ->whereHas('trainee');

        foreach (['category_id', 'promotion_id', 'filiere_id', 'graduation_year'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->query($f));
            }
        }
        if ($request->filled('q')) {
            $q->whereHas('trainee', fn ($t) => $t->filter(['q' => $request->query('q')]));
        }

        return response()->json($q->latest('id')->paginate(min(max((int) $request->query('per_page', 25), 1), 100)));
    }

    private function rules(): array
    {
        return [
            'filiere_id' => ['nullable', 'integer', 'exists:filieres,id'],
            'promotion_id' => ['nullable', 'integer', 'exists:promotions,id'],
            'category_id' => ['nullable', 'integer', 'exists:candidate_categories,id'],
            'graduation_year' => ['nullable', 'integer', 'between:1970,2100'],
            'status' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attestation_eligibility_confirmed' => ['sometimes', 'boolean'],
        ];
    }

    public function store(Request $request, Trainee $trainee): JsonResponse
    {
        $data = $request->validate($this->rules());
        $data['category_id'] ??= $trainee->category_id;

        if ($data['category_id'] === null) {
            return response()->json([
                'message' => 'La catégorie du lauréat (régulier ou libre) doit être confirmée avant l\'enregistrement.',
                'errors' => ['category_id' => ['La catégorie est obligatoire pour un lauréat.']],
            ], 422);
        }

        $graduation = $trainee->graduations()->create($data);
        Audit::log('graduation.created', $graduation, [], $data);

        return response()->json($graduation->load(['filiere:id,name', 'promotion:id,label', 'category:id,label']), 201);
    }

    public function update(Request $request, Graduation $graduation): JsonResponse
    {
        $data = $request->validate($this->rules());
        if (array_key_exists('category_id', $data) && $data['category_id'] === null) {
            return response()->json(['message' => 'La catégorie ne peut pas être vide.', 'errors' => ['category_id' => ['La catégorie est obligatoire.']]], 422);
        }
        $old = $graduation->only(array_keys($data));
        $graduation->update($data);
        Audit::log('graduation.updated', $graduation, $old, $data);

        return response()->json($graduation->fresh(['filiere:id,name', 'promotion:id,label', 'category:id,label']));
    }

    public function destroy(Graduation $graduation): JsonResponse
    {
        $graduation->delete();
        Audit::log('graduation.deleted', $graduation);

        return response()->json(['message' => 'Dossier de lauréat supprimé.']);
    }
}
