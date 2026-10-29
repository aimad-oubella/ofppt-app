<?php

namespace App\Support;

use App\Models\Establishment;
use App\Models\Filiere;
use App\Models\Level;
use App\Models\Promotion;
use App\Models\RegionalDirection;
use App\Models\TrainingGroup;
use Illuminate\Validation\Rule;

/**
 * Description des référentiels configurables (établissements, niveaux, filières, promotions, groupes...).
 * Ajouter un référentiel = ajouter une entrée ici : l'API et l'écran de gestion s'adaptent seuls.
 */
class ReferenceRegistry
{
    public static function all(): array
    {
        return [
            'regional-directions' => [
                'label' => 'Directions régionales',
                'model' => RegionalDirection::class,
                'order' => 'name',
                'columns' => [
                    ['key' => 'name', 'label' => 'Nom', 'type' => 'text', 'required' => true],
                ],
                'rules' => fn ($id) => [
                    'name' => ['required', 'string', 'max:255', Rule::unique('regional_directions', 'name')->ignore($id)],
                ],
            ],
            'establishments' => [
                'label' => 'Établissements',
                'model' => Establishment::class,
                'order' => 'name',
                'deletable' => false,
                'columns' => [
                    ['key' => 'name', 'label' => 'Nom', 'type' => 'text', 'required' => true],
                    ['key' => 'code', 'label' => 'Code', 'type' => 'text', 'required' => true],
                    ['key' => 'regional_direction_id', 'label' => 'Direction régionale', 'type' => 'select', 'options' => 'regional-directions', 'option_label' => 'name'],
                    ['key' => 'active', 'label' => 'Actif', 'type' => 'boolean'],
                ],
                'rules' => fn ($id) => [
                    'name' => ['required', 'string', 'max:255'],
                    'code' => ['required', 'string', 'max:30', Rule::unique('establishments', 'code')->ignore($id)],
                    'regional_direction_id' => ['nullable', 'integer', 'exists:regional_directions,id'],
                    'active' => ['sometimes', 'boolean'],
                ],
            ],
            'levels' => [
                'label' => 'Niveaux',
                'model' => Level::class,
                'order' => 'sort_order',
                'deletable' => false,
                'columns' => [
                    ['key' => 'name', 'label' => 'Nom', 'type' => 'text', 'required' => true],
                    ['key' => 'sort_order', 'label' => 'Ordre', 'type' => 'number'],
                    ['key' => 'active', 'label' => 'Actif', 'type' => 'boolean'],
                ],
                'rules' => fn ($id) => [
                    'name' => ['required', 'string', 'max:255', Rule::unique('levels', 'name')->ignore($id)],
                    'sort_order' => ['nullable', 'integer', 'min:0'],
                    'active' => ['sometimes', 'boolean'],
                ],
            ],
            'filieres' => [
                'label' => 'Filières',
                'model' => Filiere::class,
                'order' => 'name',
                'columns' => [
                    ['key' => 'establishment_id', 'label' => 'Établissement', 'type' => 'select', 'options' => 'establishments', 'option_label' => 'name', 'required' => true],
                    ['key' => 'level_id', 'label' => 'Niveau', 'type' => 'select', 'options' => 'levels', 'option_label' => 'name', 'required' => true],
                    ['key' => 'name', 'label' => 'Intitulé', 'type' => 'text', 'required' => true],
                    ['key' => 'code', 'label' => 'Code', 'type' => 'text'],
                    ['key' => 'active', 'label' => 'Actif', 'type' => 'boolean'],
                ],
                'rules' => fn ($id) => [
                    'establishment_id' => ['required', 'integer', 'exists:establishments,id'],
                    'level_id' => ['required', 'integer', 'exists:levels,id'],
                    'name' => ['required', 'string', 'max:255', Rule::unique('filieres', 'name')
                        ->where(fn ($q) => $q->where('establishment_id', request('establishment_id'))->where('level_id', request('level_id')))
                        ->ignore($id)],
                    'code' => ['nullable', 'string', 'max:30'],
                    'active' => ['sometimes', 'boolean'],
                ],
            ],
            'promotions' => [
                'label' => 'Promotions',
                'model' => Promotion::class,
                'order' => 'label',
                'columns' => [
                    ['key' => 'establishment_id', 'label' => 'Établissement', 'type' => 'select', 'options' => 'establishments', 'option_label' => 'name', 'required' => true],
                    ['key' => 'label', 'label' => 'Libellé', 'type' => 'text', 'required' => true],
                    ['key' => 'year_start', 'label' => 'Année de début', 'type' => 'number'],
                    ['key' => 'year_end', 'label' => 'Année de fin', 'type' => 'number'],
                    ['key' => 'active', 'label' => 'Actif', 'type' => 'boolean'],
                ],
                'rules' => fn ($id) => [
                    'establishment_id' => ['required', 'integer', 'exists:establishments,id'],
                    'label' => ['required', 'string', 'max:255', Rule::unique('promotions', 'label')
                        ->where(fn ($q) => $q->where('establishment_id', request('establishment_id')))
                        ->ignore($id)],
                    'year_start' => ['nullable', 'integer', 'between:1970,2100'],
                    'year_end' => ['nullable', 'integer', 'between:1970,2100'],
                    'active' => ['sometimes', 'boolean'],
                ],
            ],
            'groups' => [
                'label' => 'Groupes',
                'model' => TrainingGroup::class,
                'order' => 'name',
                'columns' => [
                    ['key' => 'filiere_id', 'label' => 'Filière', 'type' => 'select', 'options' => 'filieres', 'option_label' => 'name', 'required' => true],
                    ['key' => 'promotion_id', 'label' => 'Promotion', 'type' => 'select', 'options' => 'promotions', 'option_label' => 'label', 'required' => true],
                    ['key' => 'name', 'label' => 'Groupe', 'type' => 'text', 'required' => true],
                    ['key' => 'training_year', 'label' => 'Année de formation', 'type' => 'number'],
                    ['key' => 'active', 'label' => 'Actif', 'type' => 'boolean'],
                ],
                'rules' => fn ($id) => [
                    'filiere_id' => ['required', 'integer', 'exists:filieres,id'],
                    'promotion_id' => ['required', 'integer', 'exists:promotions,id'],
                    'name' => ['required', 'string', 'max:255', Rule::unique('training_groups', 'name')
                        ->where(fn ($q) => $q->where('filiere_id', request('filiere_id'))->where('promotion_id', request('promotion_id')))
                        ->ignore($id)],
                    'training_year' => ['nullable', 'integer', 'between:1,9'],
                    'active' => ['sometimes', 'boolean'],
                ],
            ],
        ];
    }
}
