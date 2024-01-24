<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
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
                'string',
                Rule::unique('suppliers', 'code')->ignore($this->supplier)
            ],
            'name' => [
                'required',
                'string',
                Rule::unique('suppliers', 'name')->ignore($this->supplier)
            ],
            'terms' => 'required|string',
            'supplier_type_id' => [
                'required',
                'numeric',
                Rule::exists('supplier_types', 'id')->where(function ($query) {
                    $query->whereNull('deleted_at');
                })
            ],
            'references' => 'required|array',
            'receipt_type' => 'required|string'
        ];
    }
}
