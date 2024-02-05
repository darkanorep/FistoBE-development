<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
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
            'code' => [
                'required',
                Rule::unique('companies', 'code')->ignore($this->route('company'))
            ],
            'company' => [
                'required',
                Rule::unique('companies', 'company')->ignore($this->route('company'))
            ],
            'associates' => [
                'required',
                'array',
                Rule::exists('users', 'id')
                ->where(function ($query) {
                    $query->whereNull('deleted_at');
                })
            ]
        ];
    }
}
