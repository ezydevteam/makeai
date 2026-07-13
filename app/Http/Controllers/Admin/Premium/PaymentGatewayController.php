<?php

namespace App\Http\Controllers\Admin\Premium;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentGatewayRequest;
use App\Models\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentGatewayController extends Controller
{
    public function index(): Response
    {
        $definitions = config('payment-gateways', []);
        $gateways = PaymentGateway::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (PaymentGateway $gateway) use ($definitions) {
                $definition = $definitions[$gateway->slug] ?? ['fields' => []];
                $webhookPath = $definition['webhook_path'] ?? null;

                return [
                    'id' => $gateway->id,
                    'slug' => $gateway->slug,
                    'name' => translate($gateway->name),
                    'description' => $gateway->description ? translate($gateway->description) : null,
                    'is_enabled' => $gateway->is_enabled,
                    'is_test_mode' => $gateway->is_test_mode,
                    'processing_fee_type' => $gateway->processing_fee_type,
                    'processing_fee_value' => $gateway->processing_fee_value,
                    'sort_order' => $gateway->sort_order,
                    'fields' => collect($definition['fields'] ?? [])
                        ->map(fn (array $field) => [
                            ...$field,
                            'label' => translate($field['label']),
                        ])
                        ->all(),
                    'webhook_url' => $webhookPath ? rtrim(request()->getSchemeAndHttpHost(), '/').$webhookPath : null,
                    'credentials' => $gateway->publicCredentials($definition['fields'] ?? []),
                ];
            });

        return Inertia::render('Admin/Premium/Gateways', [
            'gateways' => $gateways,
        ]);
    }

    public function update(PaymentGatewayRequest $request, PaymentGateway $gateway): RedirectResponse
    {

        $data = $request->validated();
        $credentials = $gateway->credentials ?: [];

        foreach ($data['credentials'] ?? [] as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $credentials[$key] = PaymentGateway::encryptCredentials([$key => $value])[$key];
        }

        $gateway->update([
            'is_enabled' => $data['is_enabled'] ?? false,
            'is_test_mode' => $data['is_test_mode'] ?? true,
            'processing_fee_type' => $data['processing_fee_type'],
            'processing_fee_value' => $data['processing_fee_type'] === 'none' ? 0 : $data['processing_fee_value'],
            'credentials' => $credentials,
        ]);

        return back()->with('success', translate(':gateway settings updated.', ['gateway' => $gateway->name]));
    }

    public function sort(Request $request): RedirectResponse
    {

        $data = $request->validate([
            'gateways' => ['required', 'array'],
            'gateways.*' => ['required', 'integer', 'exists:payment_gateways,id'],
        ]);

        foreach ($data['gateways'] as $index => $gatewayId) {
            PaymentGateway::whereKey($gatewayId)->update(['sort_order' => $index + 1]);
        }

        return back()->with('success', translate('Payment gateway order updated.'));
    }
}
