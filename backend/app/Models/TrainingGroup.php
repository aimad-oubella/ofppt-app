<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingGroup extends Model
{
    protected $table = 'training_groups';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
