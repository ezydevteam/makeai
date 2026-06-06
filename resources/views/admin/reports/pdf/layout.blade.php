<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1a1a1a; }
  .header { border-bottom: 2px solid #10b981; padding-bottom: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
  .header-logo { font-size: 18px; font-weight: bold; color: #10b981; }
  .header-meta { font-size: 10px; color: #6b7280; text-align: right; }
  .report-title { font-size: 16px; font-weight: bold; margin-bottom: 4px; }
  .report-subtitle { font-size: 11px; color: #6b7280; margin-bottom: 20px; }
  .stats-row { display: flex; gap: 12px; margin-bottom: 20px; }
  .stat-box { flex: 1; background: #f3f4f6; border-radius: 6px; padding: 10px 12px; }
  .stat-label { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
  .stat-value { font-size: 18px; font-weight: bold; color: #111827; margin-top: 2px; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  th { background: #f9fafb; border-bottom: 1px solid #e5e7eb; padding: 8px 10px; text-align: left; font-size: 10px; font-weight: 600; color: #374151; text-transform: uppercase; letter-spacing: 0.04em; }
  td { border-bottom: 1px solid #f3f4f6; padding: 7px 10px; font-size: 10px; color: #374151; }
  tr:last-child td { border-bottom: none; }
  .badge { display: inline-block; padding: 2px 7px; border-radius: 10px; font-size: 9px; font-weight: 600; }
  .badge-green  { background: #d1fae5; color: #065f46; }
  .badge-red    { background: #fee2e2; color: #991b1b; }
  .badge-amber  { background: #fef3c7; color: #92400e; }
  .badge-gray   { background: #f3f4f6; color: #374151; }
  .footer { border-top: 1px solid #e5e7eb; padding-top: 8px; margin-top: 24px; font-size: 9px; color: #9ca3af; display: flex; justify-content: space-between; }
</style>
</head>
<body>
<div class="header">
  <div class="header-logo">{{ $appName ?? translate('Application') }}</div>
  <div class="header-meta">
    {{ translate('Generated') }}: {{ now()->format('M d, Y H:i') }}<br>
    {{ translate('By') }}: {{ $adminName ?? translate('Admin') }}
  </div>
</div>
@yield('content')
<div class="footer">
  <span>{{ $appName ?? translate('Application') }} — {{ translate('Admin Report') }}</span>
  <span>Page {PAGENO} of {nbpg}</span>
</div>
</body>
</html>
