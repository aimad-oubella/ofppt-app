<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trainee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'cef', 'registration_number', 'matricule', 'last_name', 'first_name', 'name_ar',
        'birth_date', 'birth_place', 'gender', 'cin', 'establishment_id', 'category_id',
        'admin_status', 'import_batch_id',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date:Y-m-d'];
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CandidateCategory::class, 'category_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function latestEnrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class)->latestOfMany();
    }

    public function graduations(): HasMany
    {
        return $this->hasMany(Graduation::class);
    }

    /** Crée une nouvelle inscription seulement si elle diffère de la dernière (historique conservé). */
    public function applyEnrollment(array $attrs): ?Enrollment
    {
        $keys = ['training_group_id', 'filiere_id', 'level_id', 'promotion_id', 'training_year'];
        $new = [];
        foreach ($keys as $k) {
            $new[$k] = $attrs[$k] ?? null;
        }
        if (count(array_filter($new, fn ($v) => $v !== null)) === 0) {
            return null;
        }

        $last = $this->enrollments()->latest('id')->first();
        if ($last && collect($keys)->every(fn ($k) => (string) ($last->$k ?? '') === (string) ($new[$k] ?? ''))) {
            return $last;
        }

        return $this->enrollments()->create($new);
    }

    /** Filtres combinables : recherche texte, établissement, catégorie, niveau, filière, groupe, promotion, année, archives. */
    public function scopeFilter(Builder $query, array $f): Builder
    {
        if (! empty($f['q'])) {
            foreach (preg_split('/\s+/', trim((string) $f['q'])) as $term) {
                if ($term === '') {
                    continue;
                }
                $like = '%'.$term.'%';
                $query->where(function ($w) use ($like) {
                    $w->where('last_name', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('cef', 'like', $like)
                        ->orWhere('registration_number', 'like', $like)
                        ->orWhere('matricule', 'like', $like)
                        ->orWhere('cin', 'like', $like);
                });
            }
        }
        if (! empty($f['establishment_id'])) {
            $query->where('establishment_id', $f['establishment_id']);
        }
        if (! empty($f['category_id'])) {
            $query->where('category_id', $f['category_id']);
        }
        if (! empty($f['uncategorized'])) {
            $query->whereNull('category_id');
        }

        $enrollmentFilters = array_filter([
            'level_id' => $f['level_id'] ?? null,
            'filiere_id' => $f['filiere_id'] ?? null,
            'training_group_id' => $f['group_id'] ?? null,
            'promotion_id' => $f['promotion_id'] ?? null,
            'training_year' => $f['training_year'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
        if ($enrollmentFilters) {
            $query->whereHas('enrollments', fn ($e) => $e->where($enrollmentFilters));
        }

        if (($f['archived'] ?? null) === 'only') {
            $query->onlyTrashed();
        } elseif (($f['archived'] ?? null) === 'with') {
            $query->withTrashed();
        }

        return $query;
    }
}
