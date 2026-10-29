<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TraineeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canWrite() ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('trainee')?->id;

        return [
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'cef' => ['nullable', 'string', 'max:30', Rule::unique('trainees', 'cef')->ignore($id)],
            'registration_number' => ['nullable', 'string', 'max:50'],
            'matricule' => ['nullable', 'string', 'max:50'],
            'cin' => ['nullable', 'string', 'max:30'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after:1900-01-01'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['M', 'F'])],
            'admin_status' => ['nullable', 'string', 'max:50'],
            'establishment_id' => ['required', 'integer', 'exists:establishments,id'],
            'category_id' => ['nullable', 'integer', 'exists:candidate_categories,id'],
            'enrollment' => ['nullable', 'array'],
            'enrollment.training_group_id' => ['nullable', 'integer', 'exists:training_groups,id'],
            'enrollment.filiere_id' => ['nullable', 'integer', 'exists:filieres,id'],
            'enrollment.level_id' => ['nullable', 'integer', 'exists:levels,id'],
            'enrollment.promotion_id' => ['nullable', 'integer', 'exists:promotions,id'],
            'enrollment.training_year' => ['nullable', 'integer', 'between:1,9'],
        ];
    }
}
