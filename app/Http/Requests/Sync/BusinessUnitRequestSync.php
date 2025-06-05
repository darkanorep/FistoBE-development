<?php

namespace App\Http\Requests\Sync;

use Illuminate\Foundation\Http\FormRequest;

class BusinessUnitRequestSync extends FormRequest
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
            '*.company' => ['required', 'exists:rdf_companies,company'],
        ];
    }

    public function messages()
    {
        return [
            '*.company.exists' => 'Company :input does not exist.',
        ];
    }
}
