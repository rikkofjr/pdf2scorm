<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    protected $fillable = ['package_id', 'question'];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(Option::class);
    }
}