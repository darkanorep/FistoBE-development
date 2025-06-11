<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Staudenmeir\EloquentJsonRelations\HasJsonRelationships;

class Permission extends Model
{
    use HasFactory, HasJsonRelationships, SoftDeletes;

    public function users() {
        return $this->hasManyJson(User::class, 'permissions');
    }

    public function bookOfAccounts() {
        return $this->belongsToMany(BookOfAccount::class, 'book_of_account_permissions', 'permission_id', 'book_of_account_id');
    }
}
