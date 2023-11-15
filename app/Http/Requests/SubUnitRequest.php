<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubUnitRequest extends FormRequest
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
                Rule::unique('sub_units', 'code')->ignore($this->route('sub_unit'))
            ],
            'sub_unit' => [
                'required',
                Rule::unique('sub_units', 'subunit')->ignore($this->route('sub_unit'))
            ],
            'department_id' => [
                'required',
                Rule::exists('departments', 'id')
                    ->where(function ($query) {
                        $query->where('id', $this->department_id)->whereNull('deleted_at');
                    })
            ]
        ];
    }

    public function messages()
    {
        return [
            'department_id.exists' => 'Department is not exists.',
        ];
    }
}
