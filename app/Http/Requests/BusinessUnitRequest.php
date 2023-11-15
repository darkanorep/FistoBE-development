<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BusinessUnitRequest extends FormRequest
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
            'company_id' => [
                'required',
                Rule::exists('companies', 'id')
                    ->where(function ($query) {
                        $query->where('id', $this->company_id)->whereNull('deleted_at');
                    })
            ],
            'code' => [
                'required',
                Rule::unique('business_units', 'code')->ignore($this->route('business_unit'))
            ],
            'business_unit' => [
                'required',
                Rule::unique('business_units', 'business_unit')->ignore($this->route('business_unit'))
            ],
            'associates' => [
                'array',
                Rule::exists('users', 'id')
                    ->where(function ($query) {
                        $query->whereNull('deleted_at');
                    })
            ],
        ];
    }

    public function messages()
    {
        return [
            'company_id.exists' => 'Company is not exists.',
        ];
    }
}
