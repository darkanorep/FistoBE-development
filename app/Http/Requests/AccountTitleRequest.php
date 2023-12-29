<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountTitleRequest extends FormRequest
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
        return [
            'code'  => [
                'required',
                'string',
                Rule::unique('account_titles', 'code')->ignore($this->route('account_title'))
            ],
            'title' => [
                'required',
                'string',
                Rule::unique('account_titles', 'title')->ignore($this->route('account_title'))
            ],
            'category' => 'required|string'
        ];
    }
}
