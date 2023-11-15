<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankRequest extends FormRequest
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
        $name = request()->get('name');
        return [
            'code' => [
                'required',
                'string',
                Rule::unique('banks', 'code')->ignore($this->route('bank'))
            ],
            'name' => [
                'required',
                'string',
                Rule::unique('banks', 'name')->ignore($this->route('bank'))
            ],
            'branch' => [
                'required',
                'string',
                Rule::unique('banks', 'branch')->where(function ($query) use ($name){
                    $query->where('name', $name);
                })->ignore($this->route('bank'))
            ],
            'account_no' => [
                'required',
                Rule::unique('banks', 'account_no')->where(function ($query) use ($name) {
                    $query->where('name', $name);
                })->ignore($this->route('bank'))
            ],
            'location' => 'required',
            'account_title_1' => 'required',
            'account_title_2' => 'required',
            'company_id_1' => 'nullable',
            'company_id_2' => 'nullable',
            'business_unit_id_1' => 'nullable',
            'business_unit_id_2' => 'nullable',
            'department_id_1' => 'nullable',
            'department_id_2' => 'nullable',
            'sub_unit_id_1' => 'nullable',
            'sub_unit_id_2' => 'nullable',
            'location_id_1' => 'nullable',
            'location_id_2' => 'nullable'
        ];
    }
}
