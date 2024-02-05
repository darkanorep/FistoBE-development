<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountTitleGreatGrandParent extends Model
{
    use HasFactory, softDeletes;

    protected $fillable = ['name'];
    protected $hidden = ['created_at'];
}
