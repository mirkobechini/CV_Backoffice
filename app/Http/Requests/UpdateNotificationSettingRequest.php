<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AdminOnlyAccess;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationSettingRequest extends FormRequest
{
    use AdminOnlyAccess;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'report_email' => 'required|email',
            'report_frequency' => 'required|in:daily,weekly,monthly',
            'reminder_days_before' => 'required|integer|min:1|max:90',
            'notify_on_maintenance' => 'nullable|boolean',
            'notify_on_deadline' => 'nullable|boolean',
            'notify_on_issue' => 'nullable|boolean',
            'notify_on_equipment' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'report_email.required' => "L'indirizzo email per il report è obbligatorio.",
            'report_email.email' => "Inserisci un indirizzo email valido.",
            'report_frequency.required' => 'La frequenza del report è obbligatoria.',
            'report_frequency.in' => 'La frequenza deve essere daily, weekly o monthly.',
            'reminder_days_before.required' => 'I giorni di preavviso sono obbligatori.',
            'reminder_days_before.integer' => 'I giorni di preavviso devono essere un numero.',
            'reminder_days_before.min' => 'I giorni di preavviso devono essere almeno 1.',
            'reminder_days_before.max' => 'I giorni di preavviso non possono superare 90.',
            'notify_on_maintenance.boolean' => 'Il valore notify_on_maintenance non è valido.',
            'notify_on_deadline.boolean' => 'Il valore notify_on_deadline non è valido.',
            'notify_on_issue.boolean' => 'Il valore notify_on_issue non è valido.',
            'notify_on_equipment.boolean' => 'Il valore notify_on_equipment non è valido.',
        ];
    }
}
