<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentRequest extends FormRequest
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
            'type' => [
                'required',
                Rule::unique('documents', 'type')->ignore($this->document)
            ],
            'description' => [
                'required',
                Rule::unique('documents', 'description')->ignore($this->document)
            ],
            'categories' => 'required|array|min:1',
        ];
    }
}
