<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class ValidTo implements Rule
{
    /**
     * @var mixed
     */
    private $from;
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
     * @var mixed
     */
    private $year;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($table, $from, $to, $bank_id, $currentId = null, $category = null)
    {
        $this->table = $table;
//        $this->year = $year;
        $this->from = $from;
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
        $to = $value;
        $from = $this->from;
        $bank_id = $this->bank_id;
        $currentId = $this->currentId;
        $category = $this->category;

        if ($category !== 'prenumbered stock') {

            $bank_series = DB::table($this->table)
                ->where('bank_id', $bank_id)
                ->where('from', $from)
                ->first();

            return !$bank_series ? true : false;
        }

        if ($to <= $from) {
            return false;
        }

        $bank_series = DB::table($this->table)
            ->where('bank_id', $bank_id)
            ->where('category', $category)
            ->where('from', '<=', $to)
            ->where('to', '>=', $to)
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
//        return 'Must be greater than From or Bank series already exists for ' . $this->bank->name;
        if ($this->category == 'prenumbered stock') {
            if ($this->to <= $this->from) {
                return 'Must be greater than From';
            }
        }

        return 'Bank series already exists for ' . $this->bank->name;
    }
}
