<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CandidateCategory;
use App\Models\Establishment;
use App\Models\Filiere;
use App\Models\Level;
use App\Models\Promotion;
use App\Models\TrainingGroup;
use Illuminate\Http\JsonResponse;

/** Listes déroulantes de l'interface, en une seule requête. */
class LookupController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'establishments' => Establishment::where('active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'levels' => Level::where('active', true)->orderBy('sort_order')->get(['id', 'name']),
            'filieres' => Filiere::where('active', true)->orderBy('name')->get(['id', 'name', 'establishment_id', 'level_id']),
            'promotions' => Promotion::where('active', true)->orderBy('label')->get(['id', 'label', 'establishment_id']),
            'groups' => TrainingGroup::where('active', true)->orderBy('name')->get(['id', 'name', 'filiere_id', 'promotion_id']),
            'categories' => CandidateCategory::orderBy('id')->get(['id', 'code', 'label']),
        ]);
    }
}
