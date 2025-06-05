<?php

namespace App\Http\Requests;

use App\Rules\ValidFrom;
use App\Rules\ValidTo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankSeriesRequest extends FormRequest
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
        $currentId = $this->route('bank_series') ? $this->route('bank_series')->id : null;

        return [
            'bank_id' => [
                'required',
                Rule::exists('banks', 'id')->whereNull('deleted_at')
            ],
            'category' => 'required|in:blank stock,prenumbered stock',
            'document_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('bank_series', 'document_name')->where(function ($query) {
                    return $query->where('bank_id', $this->bank_id)
                        ->where('category', $this->category);
                })->ignore($currentId)
            ],
//            'year' => [
//                'required',
//                'digits:4',
//                Rule::unique('bank_series', 'year')->where(function ($query) {
//                    return $query->where('bank_id', $this->bank_id)
//                        ->where('from', $this->from)
//                        ->where('to', $this->to);
//                })->ignore($currentId)
//            ],
            'from' => array_filter([
                'required',
                new ValidFrom('bank_series', $this->to, $this->bank_id, $currentId, $this->category),
                $this->category === 'blank stock' ? function ($attribute, $value, $fail) use ($currentId) {
                    $notUsed = \App\Models\BankSeries::where('bank_id', $this->bank_id)
                        ->where('category', 'blank stock')
                        ->where('from', $value)
                        ->where('is_used', 0) // Only block if there's an unused one
//                        ->when($currentId, fn($query) => $query->where('id', '!=', $currentId))
                            ->when($currentId, function ($query) use ($currentId) {
                                return $query->where('id', '!=', $currentId);
                            })
                        ->exists();

                    if ($notUsed) {
                        $fail('This blank stock cheque number is already registered but not yet used. You can only reuse it once it has been marked as used.');
                    }
                } : null,
            ]),

            'to' => array_filter([
                'required_if:category,prenumbered stock',
                $this->category === 'prenumbered stock'
                    ? new ValidTo('bank_series', $this->from, $this->to, $this->bank_id, $currentId, $this->category)
                    : null,
            ]),
        ];
    }

    public function messages()
    {
        return [
            'year.unique' => 'Year already taken for this bank',
            'bank_id.exists' => 'Bank does not exist'
        ];
    }
}
