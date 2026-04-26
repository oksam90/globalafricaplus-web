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
            'payout_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'payout_provider' => ['sometimes', 'nullable', 'string', 'max:50'],
            'payout_country' => ['sometimes', 'nullable', 'string', 'size:2'],
            'stage' => ['sometimes', 'in:idea,mvp,launch,scaling'],
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
