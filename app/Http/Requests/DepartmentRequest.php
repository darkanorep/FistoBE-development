<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
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
                Rule::unique('departments', 'code')->ignore($this->route('department'))
            ],
            'department' => [
                'required',
                Rule::unique('departments', 'department')->ignore($this->route('department'))
            ],
            'company' => [
                'required',
                Rule::exists('companies', 'id')->where(function ($query) {
                    $query->whereNull('deleted_at');
                })
            ],
            'voucher_code_id' => [
                'required',
                Rule::exists('voucher_codes', 'id')->where(function ($query) {
                    $query->whereNull('deleted_at');
                })
            ]
        ];
    }
}
