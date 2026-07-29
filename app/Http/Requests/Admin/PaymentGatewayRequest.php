<?php

namespace App\Http\Requests\Admin;

use App\Models\PaymentGateway;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->hasPermission('payments.gateways');
    }

    public function rules(): array
    {
        return [
            'is_enabled' => ['boolean'],
            'is_test_mode' => ['boolean'],
            'processing_fee_type' => ['required', Rule::in(['none', 'percentage', 'fixed'])],
            // A fixed fee is a flat amount in the store base currency (added to the
            // charge). There is no separate fee currency — it is always the base.
            'processing_fee_value' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'credentials' => ['array'],
            'credentials.*' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * A gateway may only be enabled once every credential it declares will be present
     * after this save.
     *
     * The admin UI blocks this too, but a toggle in a Vue component is not a control — it
     * is a courtesy. An enabled gateway with a missing key shows up at checkout, takes the
     * buyer's click, and fails on the gateway call.
     *
     * "Present after this save" is the important part: a blank field means *keep the
     * stored value*, so validating the submitted credentials alone would refuse every save
     * that did not retype all of them — which is the lock this whole change removed.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->boolean('is_enabled')) {
                    return;
                }

                $gateway = $this->route('gateway');

                if (! $gateway instanceof PaymentGateway) {
                    return;
                }

                $submitted = (array) $this->input('credentials', []);

                foreach (config("payment-gateways.{$gateway->slug}.fields", []) as $field) {
                    $key = $field['key'] ?? null;

                    if (! $key) {
                        continue;
                    }

                    $typed = trim((string) ($submitted[$key] ?? ''));

                    // getCredential() returns null when the stored value will not decrypt,
                    // so a credential encrypted under an old APP_KEY counts as missing —
                    // it is, as far as any gateway call is concerned.
                    if ($typed === '' && blank($gateway->getCredential($key))) {
                        $validator->errors()->add(
                            "credentials.{$key}",
                            translate(':field is required before this gateway can be enabled.', [
                                'field' => translate($field['label'] ?? $key),
                            ]),
                        );
                    }
                }
            },
        ];
    }
}
