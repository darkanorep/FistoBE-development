<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountNumberRequest extends FormRequest
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
            "account_no" => [
                'required',
                'string',
                Rule::unique('account_numbers','account_no')->ignore($this->route('account_number'))
            ],
            "location_id" => [
                'required',
                'numeric',
                Rule::exists('utility_locations','id')->where(function($query){
                    $query->whereNull('deleted_at');
                })
            ],
            "category_id" => [
                'required',
                'numeric',
                Rule::exists('utility_categories','id')->where(function($query){
                    $query->whereNull('deleted_at');
                })
            ],
            "supplier_id" => [
                'required',
                'numeric',
                Rule::exists('suppliers','id')->where(function($query){
                    $query->whereNull('deleted_at');
                })
            ],
        ];
    }

    public function messages()
    {
        return [
            "account_no.required"=>"Account number field is required",
            "account_no.string"=>'Account number must be string',
            "location_id.required"=>'Location ID must be in number format',
            "category_id.required"=>'Category ID must be in number format',
            "supplier_id.required"=>'Supplier ID must be in number format'
        ];
    }
}
