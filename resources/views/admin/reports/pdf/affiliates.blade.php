@extends('admin.reports.pdf.layout')
@section('content')
<div class="report-title">{{ translate('Affiliate Commissions Report') }}</div>
<div class="report-subtitle">{{ $dateFrom }} — {{ $dateTo }}</div>

<div class="stats-row">
  <div class="stat-box">
    <div class="stat-label">{{ translate('Total Commissions') }}</div>
    <div class="stat-value">${{ number_format($stats['total_commissions'], 2) }}</div>
  </div>
  <div class="stat-box">
    <div class="stat-label">{{ translate('Transactions') }}</div>
    <div class="stat-value">{{ number_format($stats['transaction_count']) }}</div>
  </div>
  <div class="stat-box">
    <div class="stat-label">{{ translate('Unique Referrers') }}</div>
    <div class="stat-value">{{ number_format($stats['unique_referrers']) }}</div>
  </div>
  <div class="stat-box">
    <div class="stat-label">{{ translate('Avg. Commission') }}</div>
    <div class="stat-value">${{ number_format($stats['avg_commission'], 2) }}</div>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th>{{ translate('Date') }}</th>
      <th>{{ translate('Referrer') }}</th>
      <th>{{ translate('Referred User') }}</th>
      <th>{{ translate('Amount') }}</th>
      <th>{{ translate('Status') }}</th>
      <th>{{ translate('Approved At') }}</th>
      <th>{{ translate('Paid At') }}</th>
    </tr>
  </thead>
  <tbody>
    @foreach($rows as $row)
    <tr>
      <td>{{ $row->created_at->format('Y-m-d') }}</td>
      <td>{{ $row->referrer?->name . ' (' . $row->referrer?->email . ')' }}</td>
      <td>{{ $row->referred?->name }}</td>
      <td>${{ number_format($row->amount, 2) }}</td>
      <td>
        <span class="badge {{ match($row->status) { 'approved' => 'badge-green', 'pending' => 'badge-amber', 'paid' => 'badge-green', 'rejected' => 'badge-red', default => 'badge-gray' } }}">
          {{ ucfirst($row->status) }}
        </span>
      </td>
      <td>{{ $row->approved_at?->format('Y-m-d') ?? '—' }}</td>
      <td>{{ $row->paid_at?->format('Y-m-d') ?? '—' }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@endsection
