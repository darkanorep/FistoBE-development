<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationRequest extends FormRequest
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
                Rule::unique('locations', 'code')->ignore($this->route('location'))
            ],
            'location' => [
                'required',
                Rule::unique('locations', 'location')->ignore($this->route('location'))
            ],
            'departments' => [
                'required',
                'array',
                Rule::exists('departments', 'id')
                    ->where(function ($query) {
                    $query->whereNull('deleted_at');
                })
            ]
        ];
    }
}
