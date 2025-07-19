<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia\HasMediaTrait;

class CostAndBudget22Journal extends Model
{
    use HasFactory, softDeletes, HasMediaTrait;

    protected $table = 'cost_and_budget22_journals';
    protected $guarded = [];

    public function journals()
    {
        return $this->morphMany(Journal::class, 'journable');
    }
}
