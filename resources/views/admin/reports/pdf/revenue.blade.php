@extends('admin.reports.pdf.layout')
@section('content')
<div class="report-title">Revenue Report</div>
<div class="report-subtitle">{{ $dateFrom }} — {{ $dateTo }}</div>

<div class="stats-row">
  <div class="stat-box">
    <div class="stat-label">Total Revenue</div>
    <div class="stat-value">${{ number_format($stats['total_revenue'], 2) }}</div>
  </div>
  <div class="stat-box">
    <div class="stat-label">Transactions</div>
    <div class="stat-value">{{ number_format($stats['transaction_count']) }}</div>
  </div>
  <div class="stat-box">
    <div class="stat-label">Avg. Transaction</div>
    <div class="stat-value">${{ number_format($stats['avg_transaction'], 2) }}</div>
  </div>
  <div class="stat-box">
    <div class="stat-label">Refunds</div>
    <div class="stat-value">${{ number_format($stats['total_refunds'], 2) }}</div>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th>Date</th><th>Transaction ID</th><th>User</th><th>Plan</th>
      <th>Amount</th><th>Gateway</th><th>Status</th>
    </tr>
  </thead>
  <tbody>
    @foreach($rows as $row)
    <tr>
      <td>{{ $row->created_at->format('Y-m-d') }}</td>
      <td style="font-family: monospace; font-size: 9px;">{{ $row->ulid }}</td>
      <td>{{ $row->user?->name ?? '—' }}</td>
      <td>{{ $row->plan?->name ?? '—' }}</td>
      <td>${{ number_format($row->amount, 2) }} {{ strtoupper($row->currency) }}</td>
      <td>{{ ucfirst($row->gateway) }}</td>
      <td>
        <span class="badge {{ match($row->status) { 'completed' => 'badge-green', 'refunded' => 'badge-red', 'pending' => 'badge-amber', default => 'badge-gray' } }}">
          {{ ucfirst($row->status) }}
        </span>
      </td>
    </tr>
    @endforeach
  </tbody>
</table>
@endsection
