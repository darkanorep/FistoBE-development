<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class ValidFrom implements Rule
{
    /**
     * @var mixed
     */
    private $to;
    /**
     * @var mixed
     */
    private $bank_id;
    /**
     * @var mixed
     */
    private $table;
    /**
     * @var \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    private $bank;
    /**
     * @var mixed
     */
    private $currentId;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($table, $to, $bank_id, $currentId = null, $category = null)
    {
        $this->table = $table;
//        $this->year = $year;
        $this->to = $to;
        $this->bank_id = $bank_id;
        $this->currentId = $currentId;
        $this->bank = DB::table('banks')->where('id', $bank_id)->first();
        $this->category = $category;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
//        $year = $this->year;
        $from = $value;
        $to = $this->to;
        $bank_id = $this->bank_id;
        $currentId = $this->currentId;
        $category = $this->category;

        $bank_series = DB::table($this->table)
            ->where('category', $category)
            ->where('bank_id', $bank_id)
            ->where('from', '<=', $from)
            ->where('to', '>=', $from)
//            ->where('year', $year)
            ->when($currentId, function ($query) use ($currentId) {
                return $query->where('id', '!=', $currentId);
            })
            ->first();

        return !$bank_series;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Bank series already exists for ' . $this->bank->name;
    }
}
