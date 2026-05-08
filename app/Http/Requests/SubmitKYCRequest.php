<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 2 — input validation for the four KYC submission endpoints
 * (basic / biometric / document / aml).
 *
 * The single class covers the four payload shapes by branching on the route
 * name (KycRoute::route('kyc.basic') etc.). This keeps validation rules
 * close to each other and makes the diff against the spec § 6.2/6.3/6.4
 * directly auditable.
 */
class SubmitKYCRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $route = $this->route()?->getName();

        return match (true) {
            str_ends_with((string) $route, '.basic')     => $this->basicRules(),
            str_ends_with((string) $route, '.biometric') => $this->biometricRules(),
            str_ends_with((string) $route, '.document')  => $this->documentRules(),
            str_ends_with((string) $route, '.aml')       => $this->amlRules(),
            default => $this->basicRules(),
        };
    }

    public function messages(): array
    {
        return [
            'selfie.required'            => 'Un selfie est requis.',
            'id_document_front.required' => 'La photo recto du document est requise.',
            'id_number.regex'            => 'Le numéro de document contient des caractères invalides.',
            'birth_year.regex'           => 'L\'année de naissance doit être au format YYYY (ex. 1990).',
        ];
    }

    // ── /api/v1/kyc/basic — Job Type 5 ──
    protected function basicRules(): array
    {
        return [
            'country'    => ['required', 'string', 'size:2'],
            'id_type'    => ['required', Rule::in($this->idTypes())],
            'id_number'  => ['required', 'string', 'min:4', 'max:64', 'regex:/^[A-Za-z0-9\-\/]+$/'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'dob'        => ['required', 'date_format:Y-m-d', 'before:today'],
        ];
    }

    // ── /api/v1/kyc/biometric — Job Type 1 ──
    protected function biometricRules(): array
    {
        return [
            'country'   => ['required', 'string', 'size:2'],
            'id_type'   => ['required', Rule::in($this->idTypes())],
            'id_number' => ['required', 'string', 'min:4', 'max:64', 'regex:/^[A-Za-z0-9\-\/]+$/'],
            'selfie'    => ['required', 'string', 'max:8000000'], // ~8 MB base64
        ];
    }

    // ── /api/v1/kyc/document — Job Type 6 ──
    protected function documentRules(): array
    {
        return [
            'country'           => ['required', 'string', 'size:2'],
            'id_type'           => ['required', Rule::in($this->idTypes())],
            'id_number'         => ['required', 'string', 'min:4', 'max:64', 'regex:/^[A-Za-z0-9\-\/]+$/'],
            'selfie'            => ['required', 'string', 'max:8000000'],
            'id_document_front' => ['required', 'string', 'max:8000000'],
        ];
    }

    // ── /api/v1/kyc/aml — Job Type 10 ──
    protected function amlRules(): array
    {
        return [
            'full_name'   => ['required', 'string', 'min:3', 'max:200'],
            'countries'   => ['required', 'array', 'min:1', 'max:5'],
            'countries.*' => ['string', 'size:2'],
            'birth_year'  => ['required', 'string', 'regex:/^(19|20)\d{2}$/'],
        ];
    }

    /**
     * Whitelist of Smile Identity id_type codes for the markets we serve.
     * Phase 1 = UEMOA, Phase 2 = NG/GH/KE.
     */
    protected function idTypes(): array
    {
        return [
            'NATIONAL_ID', 'PASSPORT', 'DRIVERS_LICENSE',
            'NIN_V2', 'BVN', 'VOTER_ID', 'SSNIT', 'ALIEN_CARD',
        ];
    }

    /**
     * Strip any data: prefix from base64 image strings before they reach the controller.
     * Smile Identity expects raw base64, no data URL header.
     */
    protected function prepareForValidation(): void
    {
        $stripDataUrl = function (?string $value): ?string {
            if ($value === null) return null;
            return preg_replace('/^data:image\/[a-z]+;base64,/i', '', $value);
        };

        $this->merge(array_filter([
            'selfie'            => $stripDataUrl($this->input('selfie')),
            'id_document_front' => $stripDataUrl($this->input('id_document_front')),
            'country'           => $this->input('country') ? strtoupper((string) $this->input('country')) : null,
        ], static fn ($v) => $v !== null));
    }
}
