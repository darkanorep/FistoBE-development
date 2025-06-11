<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BookOfAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name'];
    protected $hidden = ['created_at'];

    public function permissions(){
        return $this->belongsToMany(Permission::class, 'book_of_account_permissions', 'book_of_account_id', 'permission_id');
    }
}
