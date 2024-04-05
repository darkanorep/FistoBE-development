<?php

namespace App\Http\Requests;

use App\Rules\UniqueEntryAndTitle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentCoaRequest extends FormRequest
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
//        return [
//            'entry' => 'required',
//            'document_id' => [
//                'required',
//                Rule::exists('documents', 'id')->where(function ($query) {
//                    $query->whereNull('deleted_at');
//                })
//            ],
//            'company_id' => [
//                'required',
//                Rule::exists('companies', 'id')->where(function ($query) {
//                    $query->whereNull('deleted_at');
//                })
//            ],
//            'business_unit_id' => [
//                'required',
//                Rule::exists('business_units', 'id')->where(function ($query) {
//                    $query->whereNull('deleted_at');
//                })
//            ],
//            'department_id' => [
//                'required',
//                Rule::exists('departments', 'id')->where(function ($query) {
//                    $query->whereNull('deleted_at');
//                })
//            ],
//            'sub_unit_id' => [
//                'required',
//                Rule::exists('sub_units', 'id')->where(function ($query) {
//                    $query->whereNull('deleted_at');
//                })
//            ],
//            'location_id' => [
//                'required',
//                Rule::exists('locations', 'id')->where(function ($query) {
//                    $query->whereNull('deleted_at');
//                })
//            ],
//            'account_title_id' => [
//                'required',
//                Rule::exists('account_titles', 'id')->where(function ($query) {
//                    $query->whereNull('deleted_at');
//                })
//            ]
//        ];

        return [
            'account.*.entry' => [
                'nullable',
//                Rule::unique('document_coa')->where('account_title_id', request('account.*.account_title_id'))
//                    ->where('entry', request('account.*.entry'))
//                    ->where('document_id', $this->id)
            ],
            'account.*.company_id' => [
                'nullable',
//                Rule::exists('companies', 'id')->where(function ($query) {
//                    $query->whereNull('deleted_at');
//                })
            ],
            'account.*.business_unit_id' => [
                'nullable',
//                Rule::exists('business_units', 'id')->where(function ($query) {
//                    $query->whereNull('deleted_at');
//                })
            ],
            'account.*.department_id' => [
                'nullable',
//                Rule::exists('departments', 'id')->where(function ($query) {
//                    $query->whereNull('deleted_at');
//                })
            ],
            'account.*.sub_unit_id' => [
                'nullable',
//                Rule::exists('sub_units', 'id')->where(function ($query) {
//                    $query->whereNull('deleted_at');
//                })
            ],
            'account.*.location_id' => [
                'nullable',
//                Rule::exists('locations', 'id')->where(function ($query) {
//                    $query->whereNull('deleted_at');
//                })
            ],
//            'account.*.account_title_id' => [
//                'nullable',
////                Rule::exists('account_titles', 'id')->where(function ($query) {
////                    $query->whereNull('deleted_at');
////                })
//            ]
            'account.*.account_title_id' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    $accounts = $this->input('account');
                    $index = str_replace('account.', '', str_replace('.account_title_id', '', $attribute));

                    // Skip if not the last element
                    if ($index != count($accounts) - 1) {
                        return;
                    }

                    $account_title_id = $value;
                    $entry = $accounts[$index]['entry'];

                    $count = collect($accounts)->filter(function ($account) use ($entry, $account_title_id) {
                        return $account['entry'] == $entry && $account['account_title_id'] == $account_title_id;
                    })->count();

                    if ($count > 1) {
                        $fail('Entry and Title combination already exists.');
                    }
                }
            ]
        ];
    }
}
