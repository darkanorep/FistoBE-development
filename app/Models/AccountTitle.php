<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Carbon\Carbon;

class AccountTitle extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'account_titles';
    protected $fillable = [
        'code',
        'title',
//        'category',
        'account_title_ggparent_id',
        'account_title_gparent_id',
        'account_title_parent_id',
        'account_title_child_id',
        'account_title_pnl_id',
        'account_title_unit_id',
    ];

    protected $hidden = [
        'created_at',
        'account_title_ggparent_id',
        'account_title_gparent_id',
        'account_title_parent_id',
        'account_title_child_id',
        'account_title_pnl_id',
        'account_title_unit_id',
    ];

    public function getCreatedAtAttribute($value)
    {
        $date = Carbon::parse($value);
        return $date->format('Y-m-d H:i');
    }

    public function getUpdatedAtAttribute($value)
    {
        $date = Carbon::parse($value);
        return $date->format('Y-m-d H:i');
    }

    public function greatGrandParents() {
        return $this->belongsTo(AccountTitleGreatGrandParent::class, 'account_title_ggparent_id')->withTrashed();
    }

    public function grandParents() {
        return $this->belongsTo(AccountTitleGrandParent::class, 'account_title_gparent_id')->withTrashed();
    }

    public function parents() {
        return $this->belongsTo(AccountTitleParent::class, 'account_title_parent_id')->withTrashed();
    }

    public function children() {
        return $this->belongsTo(AccountTitleChild::class, 'account_title_child_id')->withTrashed();
    }

    public function pnl() {
        return $this->belongsTo(AccountTitlePnl::class, 'account_title_pnl_id')->withTrashed();
    }

    public function units() {
        return $this->belongsTo(AccountTitleUnit::class, 'account_title_unit_id')->withTrashed();
    }
}
