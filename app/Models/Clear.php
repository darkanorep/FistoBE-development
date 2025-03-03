<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Clear extends Model
{
    use HasFactory;

    protected $fillable = [
        "tag_id",
        "user_id",
        "date_received",
        "status",
        "date_status",
        "date_cleared",
        "transaction_id"
    ];

    public function account_title(){
        return $this->hasMany(ClearingAccountTitle::class,'clear_id','id');
    }

    public function clearUser() {
        return $this->hasOne(User::class, 'id', 'user_id')
            ->select('id', 'first_name', 'last_name', 'id_prefix', 'id_no');
    }
}
