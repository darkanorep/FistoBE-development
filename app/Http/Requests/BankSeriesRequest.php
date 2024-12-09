<?php

namespace App\Http\Requests;

use App\Rules\ValidFrom;
use App\Rules\ValidTo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankSeriesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $currentId = $this->route('bank_series') ? $this->route('bank_series')->id : null;

        return [
            'bank_id' => [
                'required',
                Rule::exists('banks', 'id')->whereNull('deleted_at')
            ],
            'category' => 'required|in:blank stock,prenumbered stock',
//            'year' => [
//                'required',
//                'digits:4',
//                Rule::unique('bank_series', 'year')->where(function ($query) {
//                    return $query->where('bank_id', $this->bank_id)
//                        ->where('from', $this->from)
//                        ->where('to', $this->to);
//                })->ignore($currentId)
//            ],
            'from' => [
                'required',
                new ValidFrom('bank_series', $this->to, $this->bank_id, $currentId, $this->category)
            ],
            'to' => [
                'required_if:category,prenumbered stock',
                'sometimes',
                new ValidTo('bank_series', $this->from, $this->to, $this->bank_id, $currentId, $this->category),
            ],
        ];
    }

    public function messages()
    {
        return [
            'year.unique' => 'Year already taken for this bank',
            'bank_id.exists' => 'Bank does not exist'
        ];
    }
}
