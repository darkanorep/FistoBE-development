<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia\HasMediaTrait;

class ConfidentialYearEndJournal extends Model
{
    use HasFactory, softDeletes, HasMediaTrait;

    protected $guarded = [];

    public function journals()
    {
        return $this->morphMany(Journal::class, 'journable');
    }
}
