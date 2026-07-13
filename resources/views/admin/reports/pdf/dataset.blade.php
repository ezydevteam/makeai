@extends('admin.reports.pdf.layout')
@section('content')
<div class="report-title">{{ $title }}</div>
@if($dateFrom || $dateTo)
<div class="report-subtitle">{{ $dateFrom }} — {{ $dateTo }}</div>
@endif

@if(!empty($stats))
<div class="stats-row">
  @foreach($stats as $label => $value)
  <div class="stat-box">
    <div class="stat-label">{{ $label }}</div>
    <div class="stat-value">{{ $value }}</div>
  </div>
  @endforeach
</div>
@endif

<table>
  <thead>
    <tr>
      @foreach($headers as $header)
      <th>{{ $header }}</th>
      @endforeach
    </tr>
  </thead>
  <tbody>
    @forelse($rows as $row)
    <tr>
      @foreach($row as $cell)
      <td>{{ $cell }}</td>
      @endforeach
    </tr>
    @empty
    <tr>
      <td colspan="{{ count($headers) }}" style="text-align:center; color:#9ca3af; padding:16px;">
        {{ translate('No records for the selected range.') }}
      </td>
    </tr>
    @endforelse
  </tbody>
</table>
@endsection
