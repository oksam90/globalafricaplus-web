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
            'payout_phone' => ['nullable', 'string', 'max:30'],
            'payout_provider' => ['nullable', 'string', 'max:50'],
            'payout_country' => ['nullable', 'string', 'size:2'],
            'stage' => ['required', 'in:idea,mvp,launch,scaling'],
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
