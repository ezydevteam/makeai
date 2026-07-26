@php
    /**
     * Invoice PDF for a single payment.
     *
     * Rendered through ExportService::renderPdfString(), which runs mPDF — so this uses the
     * same table-based layout and DejaVu font as the admin report PDFs. mPDF's flexbox
     * support is unreliable, hence tables for the two-column blocks.
     */
    $isPaid = $payment->status === 'completed';
    $statusClass = match ($payment->status) {
        'completed' => 'badge-green',
        'failed' => 'badge-red',
        'pending' => 'badge-amber',
        'refunded' => 'badge-blue',
        default => 'badge-gray',
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1a1a1a; }
  .header { border-bottom: 2px solid #1f75fe; padding-bottom: 14px; margin-bottom: 24px; }
  .brand { font-size: 20px; font-weight: bold; color: #1f75fe; }
  /* Height-capped rather than width-capped so a wide wordmark and a square icon both sit on
     the same baseline as the INVOICE title opposite. */
  .brand-logo { max-height: 42px; max-width: 220px; margin-bottom: 4px; }
  .brand-meta { font-size: 10px; color: #6b7280; margin-top: 3px; }
  .doc-title { font-size: 22px; font-weight: bold; text-align: right; }
  .doc-meta { font-size: 10px; color: #6b7280; text-align: right; margin-top: 3px; }
  .cols { width: 100%; margin-bottom: 24px; }
  .cols td { vertical-align: top; width: 50%; padding: 0; border: none; }
  .label { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 5px; }
  .value { font-size: 11px; color: #111827; line-height: 1.6; }
  .value strong { font-size: 12px; }
  table.items { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
  table.items th { background: #f9fafb; border-bottom: 1px solid #e5e7eb; padding: 9px 12px; text-align: left; font-size: 9px; font-weight: 600; color: #374151; text-transform: uppercase; letter-spacing: 0.05em; }
  table.items td { border-bottom: 1px solid #f3f4f6; padding: 11px 12px; font-size: 11px; color: #374151; }
  .right { text-align: right; }
  .totals { width: 45%; margin-left: 55%; border-collapse: collapse; }
  .totals td { padding: 7px 12px; font-size: 11px; border: none; }
  .totals tr.grand td { border-top: 2px solid #111827; font-size: 14px; font-weight: bold; color: #111827; padding-top: 10px; }
  .badge { display: inline-block; padding: 3px 9px; border-radius: 10px; font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
  .badge-green { background: #d1fae5; color: #065f46; }
  .badge-red   { background: #fee2e2; color: #991b1b; }
  .badge-amber { background: #fef3c7; color: #92400e; }
  .badge-blue  { background: #dbeafe; color: #1e40af; }
  .badge-gray  { background: #f3f4f6; color: #374151; }
  .notice { margin-top: 20px; padding: 10px 12px; border-radius: 6px; background: #fef3c7; color: #92400e; font-size: 10px; }
  .footer { border-top: 1px solid #e5e7eb; padding-top: 10px; margin-top: 36px; font-size: 9px; color: #9ca3af; text-align: center; line-height: 1.6; }
</style>
</head>
<body>

{{-- Explicit column widths: with `width:auto` mPDF sizes each column to its content, so the
     right-hand block sat next to the brand instead of against the right margin. 55/45 with
     the right cell right-aligned is what actually produces justify-between here. --}}
<table class="header" style="width:100%; border-collapse:collapse; table-layout:fixed;">
  <tr>
    <td style="border:none; padding:0; width:55%; vertical-align:top; text-align:left;">
      @if ($company['logo'])
        <img src="{{ $company['logo'] }}" alt="{{ $company['name'] }}" class="brand-logo">
      @else
        <div class="brand">{{ $company['name'] }}</div>
      @endif
      @if ($company['email'])
        <div class="brand-meta">{{ $company['email'] }}</div>
      @endif
      @if ($company['url'])
        <div class="brand-meta">{{ $company['url'] }}</div>
      @endif
    </td>
    <td style="border:none; padding:0; width:45%; vertical-align:top; text-align:right;">
      <div class="doc-title">{{ translate('INVOICE') }}</div>
      <div class="doc-meta">{{ $invoiceNumber }}</div>
      <div class="doc-meta">{{ $payment->created_at->format('F j, Y') }}</div>
    </td>
  </tr>
</table>

<table class="cols">
  <tr>
    <td>
      <div class="label">{{ translate('Billed to') }}</div>
      <div class="value">
        <strong>{{ $user->name }}</strong><br>
        {{ $user->email }}
        @if ($user->country)
          <br>{{ $user->country }}
        @endif
      </div>
    </td>
    <td>
      <div class="label">{{ translate('Payment details') }}</div>
      <div class="value">
        {{ translate('Status') }}: <span class="badge {{ $statusClass }}">{{ translate(ucfirst($payment->status)) }}</span><br>
        {{ translate('Method') }}: {{ $gatewayLabel }}<br>
        {{ translate('Reference') }}: {{ $payment->ulid }}
      </div>
    </td>
  </tr>
</table>

<table class="items">
  <thead>
    <tr>
      <th>{{ translate('Description') }}</th>
      <th class="right" style="width:120px;">{{ translate('Amount') }}</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>
        <strong>{{ $lineItem['title'] }}</strong>
        @if ($lineItem['subtitle'])
          <br><span style="color:#6b7280; font-size:10px;">{{ $lineItem['subtitle'] }}</span>
        @endif
      </td>
      <td class="right">{{ $formattedAmount }}</td>
    </tr>
  </tbody>
</table>

<table class="totals">
  <tr>
    <td style="color:#6b7280;">{{ translate('Subtotal') }}</td>
    <td class="right">{{ $formattedAmount }}</td>
  </tr>
  <tr class="grand">
    <td>{{ translate('Total') }}</td>
    <td class="right">{{ $formattedAmount }}</td>
  </tr>
</table>

{{-- An invoice for an unsettled or reversed payment must say so on its face, or it reads
     as a receipt for money that was never collected. --}}
@unless ($isPaid)
  <div class="notice">
    @if ($payment->status === 'pending')
      {{ translate('This payment has not settled yet. This document is not proof of payment.') }}
    @elseif ($payment->status === 'refunded')
      {{ translate('This payment was refunded in full.') }}
    @elseif ($payment->status === 'failed')
      {{ translate('This payment attempt failed and no amount was collected.') }}
    @else
      {{ translate('This payment is not completed.') }}
    @endif
  </div>
@endunless

<div class="footer">
  {{ translate('Thank you for being with us.') }}<br>
  {{ translate('Generated on :date', ['date' => now()->format('F j, Y')]) }}
  @if ($company['support'])
    &middot; {{ translate('Questions? Contact :email', ['email' => $company['support']]) }}
  @endif
</div>

</body>
</html>
