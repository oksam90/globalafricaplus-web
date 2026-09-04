<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // handled by policy in controller
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:200'],
            'summary' => ['sometimes', 'string', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'country' => ['sometimes', 'string', 'max:120'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'category_id' => ['sometimes', 'exists:categories,id'],
            'sub_category_id' => ['sometimes', 'nullable', 'exists:sub_categories,id'],
            'amount_needed' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'legal_status' => ['sometimes', 'nullable', 'string', 'max:80'],
            'rccm_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'tax_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            // Compte de réception — canal principal : mobile money (PawaPay)
            'payout_mobile_number'   => ['sometimes', 'nullable', 'string', 'min:8', 'max:20', 'regex:/^[0-9 +]+$/'],
            'payout_mobile_provider' => ['sometimes', 'nullable', 'string', 'max:40'],
            'payout_mobile_country'  => ['sometimes', 'nullable', 'string', 'size:2'],
            'payout_mobile_holder'   => ['sometimes', 'nullable', 'string', 'max:200'],
            // Canal secondaire : virement bancaire (utilisé pour les paiements CB)
            'payout_account_holder' => ['sometimes', 'nullable', 'string', 'max:200'],
            'payout_bank_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'payout_iban' => ['sometimes', 'nullable', 'string', 'min:15', 'max:34', 'regex:/^[A-Z0-9 ]+$/i'],
            'payout_bic' => ['sometimes', 'nullable', 'string', 'min:8', 'max:11', 'regex:/^[A-Z0-9]+$/i'],
            'payout_bank_country' => ['sometimes', 'nullable', 'string', 'size:2'],
            'stage' => ['sometimes', 'in:idea,mvp,launch,scaling'],
            'stage_details' => ['sometimes', 'nullable', 'array'],
            'stage_details.*' => ['nullable', 'string', 'max:5000'],
            'jobs_target' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['string', 'max:40'],
            'website' => ['sometimes', 'nullable', 'url', 'max:200'],
            'video_url' => ['sometimes', 'nullable', 'url', 'max:200'],
            'pitch_deck_url' => ['sometimes', 'nullable', 'url', 'max:200'],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'sdg_ids' => ['sometimes', 'nullable', 'array'],
            'sdg_ids.*' => ['integer', 'exists:sdgs,id'],
            'status' => ['sometimes', 'in:draft,pending,published,closed'],
        ];
    }
}
