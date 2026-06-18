@extends('admin.reports.pdf.layout')
@section('content')
<div class="report-title">AI Usage Report</div>
<div class="report-subtitle">{{ $dateFrom }} — {{ $dateTo }}  ·  {{ $totalRows }} records</div>

<div class="stats-row">
  <div class="stat-box">
    <div class="stat-label">Total Requests</div>
    <div class="stat-value">{{ number_format($stats['total_requests']) }}</div>
  </div>
  <div class="stat-box">
    <div class="stat-label">Total Tokens</div>
    <div class="stat-value">{{ number_format($stats['total_tokens']) }}</div>
  </div>
  <div class="stat-box">
    <div class="stat-label">Total Cost (USD)</div>
    <div class="stat-value">${{ number_format($stats['total_cost'], 2) }}</div>
  </div>
  <div class="stat-box">
    <div class="stat-label">Unique Users</div>
    <div class="stat-value">{{ number_format($stats['unique_users']) }}</div>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th>Date</th><th>User</th><th>Tool</th><th>Model</th>
      <th>Tokens In</th><th>Tokens Out</th><th>Cost (USD)</th><th>Status</th>
    </tr>
  </thead>
  <tbody>
    @foreach($rows as $row)
    <tr>
      <td>{{ $row->created_at->format('Y-m-d H:i') }}</td>
      <td>{{ $row->user?->name ?? 'Deleted' }}</td>
      <td>{{ $row->tool_slug }}</td>
      <td>{{ $row->model }}</td>
      <td>{{ number_format($row->input_tokens) }}</td>
      <td>{{ number_format($row->output_tokens) }}</td>
      <td>${{ number_format($row->cost_usd, 6) }}</td>
      <td>
        <span class="badge {{ $row->status === 'completed' ? 'badge-green' : ($row->status === 'failed' ? 'badge-red' : 'badge-yellow') }}">
          {{ ucfirst($row->status) }}
        </span>
      </td>
    </tr>
    @endforeach
  </tbody>
</table>
@endsection
