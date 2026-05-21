<?php

namespace App\Http\Requests;

use App\Services\AffiliateService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AffiliatePayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && (bool) app(AffiliateService::class)->program()->payouts_enabled;
    }

    public function rules(): array
    {
        $program = app(AffiliateService::class)->program();

        return [
            'amount' => ['required', 'numeric', 'min:'.$program->min_payout],
            'method' => ['required', Rule::in($program->payout_methods ?: ['paypal', 'bank_transfer', 'credits'])],
            'details' => ['required', 'array'],
            'details.paypal_email' => ['nullable', 'required_if:method,paypal', 'email', 'max:255'],
            'details.bank_account' => ['nullable', 'required_if:method,bank_transfer', 'string', 'max:2000'],
        ];
    }
}
