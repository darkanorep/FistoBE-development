<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia\HasMediaTrait;

class AccrualReversalJournals extends Model
{
    use HasFactory, SoftDeletes, HasMediaTrait;

    protected $guarded = [];
    public function journals()
    {
        return $this->morphMany(Journal::class, 'journable');
    }
}
