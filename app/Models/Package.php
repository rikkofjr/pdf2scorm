<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $fillable = ['title', 'pdf_file', 'has_quiz', 'passing_score'];

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function quizzes()
    {
        return $this->hasMany(Question::class, 'package_id'); // atau Question::class sesuaikan dengan nama model Anda
    }
}
