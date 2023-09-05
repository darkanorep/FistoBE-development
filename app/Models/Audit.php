<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Audit extends Model
{
  use HasFactory;

  protected $fillable = [
    "transaction_id",
    "type",
    "date_received",
    "status",
    "reason_id",
    "remarks",
    "user_id",
    "date_audited",
  ];

  public function auditedBy()
  {
    return $this->belongsTo(User::class, "user_id");
  }

  public function reason()
  {
    return $this->belongsTo(Reason::class, "reason_id");
  }
}
