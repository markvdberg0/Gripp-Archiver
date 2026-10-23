<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('relaties.bewerken');
    }

    public function rules(): array
    {
        $company = $this->route('relatie');
        $address = [];
        foreach (['visit', 'postal', 'invoice'] as $p) {
            $address += [
                "{$p}_street" => ['nullable', 'string', 'max:255'],
                "{$p}_house_number" => ['nullable', 'string', 'max:20'],
                "{$p}_postcode" => ['nullable', 'string', 'max:15'],
                "{$p}_city" => ['nullable', 'string', 'max:100'],
                "{$p}_country" => ['nullable', 'string', 'max:60'],
            ];
        }
        $rules = $address + [
            'name' => ['required', 'string', 'max:255'],
            'number' => ['nullable', 'integer', 'min:1', Rule::unique('companies', 'number')->ignore($company?->id)],
            'relation_type' => ['required', Rule::in(['bedrijf', 'particulier'])],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'is_customer' => ['boolean'], 'is_supplier' => ['boolean'], 'is_prospect' => ['boolean'], 'is_lead' => ['boolean'],
            'email' => ['nullable', 'email', 'max:255'],
            'invoice_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'string', 'max:255'],
            'coc_number' => ['nullable', 'string', 'max:20'],
            'vat_number' => ['nullable', 'string', 'max:30'],
            'vat_shifted' => ['boolean'],
            'invoice_company_name' => ['nullable', 'string', 'max:255'],
            'invoice_attention' => ['nullable', 'string', 'max:255'],
            'invoice_send_by' => ['required', Rule::in(['email', 'post'])],
            'payment_method' => ['required', Rule::in(array_keys(Company::BETAALWIJZEN))],
            'payment_term_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'supplier_payment_term_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'founded_on' => ['nullable', 'date'],
            'account_manager_id' => ['nullable', 'exists:employees,id'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'active' => ['boolean'],
            'tags' => ['nullable', 'string', 'max:500'],
        ];
        if ($this->user()->can('relaties.financieel')) {
            $rules += [
                'iban' => ['nullable', 'string', 'max:40', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9 ]{8,32}$/i'],
                'bic' => ['nullable', 'string', 'max:15'],
                'bank_account_holder' => ['nullable', 'string', 'max:255'],
            ];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('iban')) {
            $this->merge(['iban' => strtoupper(str_replace(' ', '', (string) $this->input('iban')))]);
        }
    }

    public function attributes(): array
    {
        return ['name' => 'naam', 'number' => 'relatienummer', 'iban' => 'IBAN', 'coc_number' => 'KvK-nummer', 'vat_number' => 'btw-nummer'];
    }
}
