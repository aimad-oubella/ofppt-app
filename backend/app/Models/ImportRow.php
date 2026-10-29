<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRow extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        // raw_data : {"cells": [...], "overrides": {champ: valeur}}
        // errors   : {"errors": [...], "warnings": [...], "duplicate": {...}|null, "flags": {...}}
        return ['raw_data' => 'array', 'errors' => 'array'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}
