<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Staudenmeir\EloquentJsonRelations\HasJsonRelationships;

class TransactionReport extends Model
{
    use HasFactory, SoftDeletes, HasJsonRelationships;

    protected $fillable = ['name'];

    protected $hidden = ['created_at'];

    public function setNameAttribute($value) {

        $this->attributes['name'] = ucwords($value);
    }

    public function users() {
        return $this->hasManyJson(User::class, 'transaction_report_id');
    }
}
