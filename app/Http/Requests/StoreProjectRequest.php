<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['entrepreneur', 'admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'summary' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'country' => ['required', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'category_id' => ['required', 'exists:categories,id'],
            'sub_category_id' => ['nullable', 'exists:sub_categories,id'],
            'amount_needed' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            // Formalisation juridique (persistée dans le RoleProfile entrepreneur)
            'legal_status' => ['nullable', 'string', 'max:80'],
            'rccm_number' => ['nullable', 'string', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            // Compte de réception — canal principal : mobile money (PawaPay)
            'payout_mobile_number'   => ['nullable', 'string', 'min:8', 'max:20', 'regex:/^[0-9 +]+$/'],
            'payout_mobile_provider' => ['nullable', 'string', 'max:40'],
            'payout_mobile_country'  => ['nullable', 'string', 'size:2'],
            'payout_mobile_holder'   => ['nullable', 'string', 'max:200'],
            // Canal secondaire : virement bancaire (utilisé pour les paiements CB)
            'payout_account_holder' => ['nullable', 'string', 'max:200'],
            'payout_bank_name' => ['nullable', 'string', 'max:150'],
            'payout_iban' => ['nullable', 'string', 'min:15', 'max:34', 'regex:/^[A-Z0-9 ]+$/i'],
            'payout_bic' => ['nullable', 'string', 'min:8', 'max:11', 'regex:/^[A-Z0-9]+$/i'],
            'payout_bank_country' => ['nullable', 'string', 'size:2'],
            'stage' => ['required', 'in:idea,mvp,launch,scaling'],
            'stage_details' => ['nullable', 'array'],
            'stage_details.*' => ['nullable', 'string', 'max:5000'],
            'jobs_target' => ['nullable', 'integer', 'min:0'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:40'],
            'website' => ['nullable', 'url', 'max:200'],
            'video_url' => ['nullable', 'url', 'max:200'],
            'pitch_deck_url' => ['nullable', 'url', 'max:200'],
            'deadline' => ['nullable', 'date', 'after:today'],
            'sdg_ids' => ['nullable', 'array'],
            'sdg_ids.*' => ['integer', 'exists:sdgs,id'],
            'status' => ['nullable', 'in:draft,pending,published'],
        ];
    }
}
