<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Staudenmeir\EloquentJsonRelations\HasJsonRelationships;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes, LogsActivity, HasJsonRelationships;

    protected $fillable = [
        "id_prefix",
        "id_no",
        "role",
        "first_name",
        "middle_name",
        "last_name",
        "suffix",
        "department",
        "subunit_name",
        "position",
        "permissions",
        "document_types",
        'transaction_report_id',
        "username",
        "password",
    ];

    protected $hidden = ["password", "remember_token", "pivot", 'transaction_report_id', "created_at"];

    protected $casts = [
        "email_verified_at" => "datetime",
        "permissions" => "array",
        "document_types" => "array",
        'transaction_report_id' => 'array',
        "department" => "array",
    ];

    protected static $logAttributes = [
        "id_no",
        "role",
        "first_name",
        "middle_name",
        "last_name",
        "suffix",
        "department",
        "position",
        "permissions",
        "document_types",
        "username",
        "password",
    ];

    protected static $logName = "User";

    protected static $logOnlyDirty = true;

    public function setUsernameAttribute($value)
    {
        $this->attributes["username"] = strtolower($value);
    }

    public function documents()
    {
        return $this->belongsToMany(Document::class, "user_documents", "user_id", "document_id")->select(
            "documents.id",
            "documents.id as document_id"
        );
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, "user_permission", "user_id", "permission_id");
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return "User has been {$eventName}.";
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class, "company_users", "user_id", "company_id")
            ->withTrashed()
            ->select(
            "companies.id",
            "companies.company as name"
        );
    }

    public function business_units(): BelongsToMany
    {
        return $this->belongsToMany(BusinessUnit::class, "business_unit_users", "user_id", "business_unit_id")->select(
            "business_units.sync_id",
            "business_units.business_unit as name"
        );
    }

    public function journalUser() {
        return $this->hasManyJson(JournalUser::class, 'user_id')->select('approver_id');
    }

    public function transactionReport() {
        return $this->belongsToJson(TransactionReport::class, 'transaction_report_id')->select('id', 'name');
    }

    public function permissionsJson()
    {
        return $this->belongsToJson(Permission::class, 'permissions')->select('id', 'name');
    }

}
