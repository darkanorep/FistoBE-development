<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitRequest extends FormRequest
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
            'code' => 'required|unique:units,code,' . $this->route('unit'),
            'name' => 'required|unique:units,name,' . $this->route('unit'),
            'department_id' => Rule::exists('departments', 'id')->whereNull('deleted_at'),
        ];
    }

    public function messages()
    {
        return [
            'department_id.exists' => 'Department does not exist.',
        ];
    }
}
