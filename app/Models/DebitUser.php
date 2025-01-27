<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DebitUser extends Model
{
    use HasFactory, softDeletes;

    protected $fillable = ['user_id'];

    protected $hidden = ['user_id', 'created_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
