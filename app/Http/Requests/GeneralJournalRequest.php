<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GeneralJournalRequest extends FormRequest
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
//            'voucher_no' => 'nullable|unique:general_journals,voucher_no',
            'voucher_no' => [
                'nullable',
                Rule::unique('general_journals', 'voucher_no') ->whereNull('deleted_at')
            ],
            'account_titles.*.entry' => 'required',
            'account_titles.*.amount' => 'required',
            'account_titles.*.account_title.id' => 'required',
            'account_titles.*.account_title.code' => 'required',
            'account_titles.*.account_title.name' => 'required|exists:account_titles,title',
            'account_titles.*.company.id' => 'required',
            'account_titles.*.company.code' => 'required',
            'account_titles.*.company.name' => 'required|exists:companies,company',
            'account_titles.*.department.id' => 'required',
            'account_titles.*.department.code' => 'required',
            'account_titles.*.department.name' => 'required|exists:departments,department',
            'account_titles.*.location.id' => 'required',
            'account_titles.*.location.code' => 'required',
            'account_titles.*.location.name' => 'required|exists:locations,location',
            'account_titles.*.business_unit.id' => 'nullable',
            'account_titles.*.business_unit.code' => 'nullable',
            'account_titles.*.business_unit.name' => 'nullable',
            'account_titles.*.sub_unit.id' => 'nullable',
            'account_titles.*.sub_unit.code' => 'nullable',
            'account_titles.*.sub_unit.name' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'voucher_no.unique' => 'This voucher number has already been adjusted.',
            'account_titles.*.entry.required' => 'The entry field is required.',
            'account_titles.*.amount.required' => 'The amount field is required.',
            'account_titles.*.account_title.id.required' => 'The account title id field is required.',
            'account_titles.*.account_title.code.required' => 'The account title code field is required.',
            'account_titles.*.account_title.name.required' => 'The account title name field is required.',
            'account_titles.*.account_title.name.exists' => 'The selected account title name is invalid.',
            'account_titles.*.company.id.required' => 'The company id field is required.',
            'account_titles.*.company.code.required' => 'The company code field is required.',
            'account_titles.*.company.name.required' => 'The company name field is required.',
            'account_titles.*.company.name.exists' => 'The selected company name is invalid.',
            'account_titles.*.department.id.required' => 'The department id field is required.',
            'account_titles.*.department.code.required' => 'The department code field is required.',
            'account_titles.*.department.name.required' => 'The department name field is required.',
            'account_titles.*.department.name.exists' => 'The selected department name is invalid.',
            'account_titles.*.location.id.required' => 'The location id field is required.',
            'account_titles.*.location.code.required' => 'The location code field is required.',
            'account_titles.*.location.name.required' => 'The location name field is required.',
            'account_titles.*.location.name.exists' => 'The selected location name is invalid.',
        ];
    }
}
