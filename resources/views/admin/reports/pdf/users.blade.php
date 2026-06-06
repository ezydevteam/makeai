@extends('admin.reports.pdf.layout')
@section('content')
<div class="report-title">{{ translate('User Report') }}</div>
<div class="report-subtitle">{{ $dateFrom }} — {{ $dateTo }}</div>

<div class="stats-row">
  <div class="stat-box">
    <div class="stat-label">{{ translate('Total Users') }}</div>
    <div class="stat-value">{{ number_format($stats['total']) }}</div>
  </div>
  <div class="stat-box">
    <div class="stat-label">{{ translate('New This Period') }}</div>
    <div class="stat-value">{{ number_format($stats['new']) }}</div>
  </div>
  <div class="stat-box">
    <div class="stat-label">{{ translate('Active (30d)') }}</div>
    <div class="stat-value">{{ number_format($stats['active']) }}</div>
  </div>
  <div class="stat-box">
    <div class="stat-label">{{ translate('Pro Subscribers') }}</div>
    <div class="stat-value">{{ number_format($stats['pro']) }}</div>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th>{{ translate('Name') }}</th>
      <th>{{ translate('Email') }}</th>
      <th>{{ translate('Plan') }}</th>
      <th>{{ translate('Credits') }}</th>
      <th>{{ translate('Status') }}</th>
      <th>{{ translate('Joined') }}</th>
    </tr>
  </thead>
  <tbody>
    @foreach($rows as $row)
    <tr>
      <td>{{ $row->name }}</td>
      <td>{{ $row->email }}</td>
      <td>{{ $row->plan?->name ?? translate('Free') }}</td>
      <td>{{ number_format($row->credits) }}</td>
      <td>
        <span class="badge {{ $row->is_active ? 'badge-green' : 'badge-red' }}">
          {{ $row->is_active ? translate('Active') : translate('Inactive') }}
        </span>
      </td>
      <td>{{ $row->created_at->format('Y-m-d') }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@endsection
