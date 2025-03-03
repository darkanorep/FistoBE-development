<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Staudenmeir\EloquentJsonRelations\HasJsonRelationships;

class JournalUser extends Model
{
    use HasFactory, SoftDeletes, HasJsonRelationships;

    protected $fillable = ['approver_id', 'user_id'];
    protected $hidden = ['created_at'];

    protected $casts = [
        'user_id' => 'array'
    ];

    public function approver() {
        return $this->belongsTo(User::class, 'approver_id')->select('id', 'first_name', 'middle_name', 'last_name');
    }

    public function users() {
        return $this->belongsToJson(User::class, 'user_id')->select('id', 'first_name', 'middle_name', 'last_name');
    }
}
