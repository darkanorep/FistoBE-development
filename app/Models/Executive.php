<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Executive extends Model
{
  use HasFactory;

  protected $fillable = ["transaction_id", "date_received", "status", "reason_id", "remarks", "user_id", "date_signed"];

  public function executiveSignedBy()
  {
    return $this->belongsTo(User::class, "user_id");
  }

  public function reason()
  {
    return $this->belongsTo(Reason::class, "reason_id");
  }
}
