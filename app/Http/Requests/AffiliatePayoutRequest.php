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
        $methods = ! empty($program->payout_methods) ? $program->payout_methods : ['paypal', 'bank_transfer', 'credits'];
        $amountRules = ['required', 'numeric', 'gt:0', 'min:'.$program->min_payout];

        $maxPayout = (float) ($program->max_payout ?? 0);
        if ($maxPayout > 0) {
            $amountRules[] = 'max:'.$maxPayout;
        }

        return [
            'amount' => $amountRules,
            'method' => ['required', Rule::in($methods)],
            'details' => ['required', 'array'],
            'details.paypal_email' => ['nullable', 'required_if:method,paypal', 'email', 'max:255'],
            'details.bank_account' => ['nullable', 'required_if:method,bank_transfer', 'string', 'max:2000'],
        ];
    }
}
