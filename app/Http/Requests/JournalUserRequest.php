<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JournalUserRequest extends FormRequest
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
            'approver_id' => 'required|exists:users,id|unique:journal_users,approver_id,' . $this->route('journal_user'),
            'user_id' => 'required|array',
        ];
    }

    public function attributes()
    {
        return [
            'approver_id' => 'appprover',
            'user_id' => 'user',
        ];
    }
}
