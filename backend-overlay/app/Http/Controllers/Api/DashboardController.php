<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Statistiques calculées uniquement à partir des données réelles.
 * Sans données, les compteurs valent 0 : aucune valeur fictive.
 */
class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $totalTrainees = DB::table('trainees')->whereNull('deleted_at')->count();

        $byCategory = DB::table('candidate_categories as c')
            ->leftJoin('trainees as t', function ($j) {
                $j->on('t.category_id', '=', 'c.id')->whereNull('t.deleted_at');
            })
            ->groupBy('c.id', 'c.label')
            ->orderBy('c.id')
            ->get(['c.label', DB::raw('COUNT(t.id) as total')]);

        $byLevel = DB::table('levels as l')
            ->leftJoin('enrollments as e', 'e.level_id', '=', 'l.id')
            ->groupBy('l.id', 'l.name', 'l.sort_order')
            ->orderBy('l.sort_order')
            ->get(['l.name', DB::raw('COUNT(DISTINCT e.trainee_id) as total')]);

        $byPromotion = DB::table('promotions as p')
            ->leftJoin('enrollments as e', 'e.promotion_id', '=', 'p.id')
            ->groupBy('p.id', 'p.label')
            ->orderBy('p.label')
            ->get(['p.label', DB::raw('COUNT(DISTINCT e.trainee_id) as total')]);

        $byFiliereGroup = DB::table('training_groups as g')
            ->join('filieres as f', 'f.id', '=', 'g.filiere_id')
            ->leftJoin('enrollments as e', 'e.training_group_id', '=', 'g.id')
            ->groupBy('g.id', 'g.name', 'f.name')
            ->orderBy('f.name')->orderBy('g.name')
            ->get(['f.name as filiere', 'g.name as groupe', DB::raw('COUNT(DISTINCT e.trainee_id) as total')]);

        $checklists = DB::table('checklist_statuses as s')
            ->leftJoin('diploma_checks as d', 's.id', '=', 'd.status_id')
            ->groupBy('s.id', 's.label', 's.sort_order')
            ->orderBy('s.sort_order')
            ->get(['s.label', DB::raw('COUNT(d.id) as total')]);

        return response()->json([
            'total_trainees' => $totalTrainees,
            'by_category' => $byCategory,
            'by_level' => $byLevel,
            'by_promotion' => $byPromotion,
            'by_filiere_group' => $byFiliereGroup,
            'checklists_by_status' => $checklists,
            'generated_documents' => DB::table('generated_documents')->count(),
        ]);
    }
}
