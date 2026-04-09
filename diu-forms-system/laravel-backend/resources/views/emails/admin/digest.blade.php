<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DIU Admin Digest</title>
<style>
  body { margin:0; padding:0; background:#f1f5f9; font-family:'Segoe UI',Arial,sans-serif; font-size:14px; color:#1e293b; }
  .wrapper { max-width:640px; margin:32px auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,.08); }
  .header { background:#0f2744; padding:20px 32px; display:flex; align-items:center; justify-content:space-between; }
  .header-logo { font-size:13px; font-weight:800; color:#fff; letter-spacing:.3px; }
  .header-badge { background:#1d4ed8; color:#fff; font-size:11px; font-weight:700; padding:4px 10px; border-radius:20px; letter-spacing:.3px; }
  .summary-bar { background:#eff6ff; border-bottom:1px solid #bfdbfe; padding:14px 32px; display:flex; align-items:center; gap:8px; }
  .summary-count { font-size:22px; font-weight:800; color:#1d4ed8; }
  .summary-text { font-size:13px; color:#1e40af; }
  .body { padding:24px 32px; }
  .greeting { font-size:14px; color:#475569; margin-bottom:20px; }
  table.items { width:100%; border-collapse:collapse; margin-bottom:20px; }
  table.items th { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#94a3b8; padding:8px 10px; background:#f8fafc; border-bottom:1px solid #e2e8f0; text-align:left; }
  table.items td { font-size:13px; padding:10px 10px; border-bottom:1px solid #f1f5f9; vertical-align:top; }
  table.items tr:last-child td { border-bottom:none; }
  .event-badge { display:inline-block; padding:2px 8px; border-radius:20px; font-size:11px; font-weight:700; }
  .badge-new     { background:#eff6ff; color:#1d4ed8; }
  .badge-resub   { background:#f0fdf4; color:#15803d; }
  .badge-setting { background:#f5f3ff; color:#6d28d9; }
  .badge-comment { background:#fff7ed; color:#b45309; }
  .badge-sla     { background:#fff1f2; color:#be123c; }
  .ref-text  { font-weight:600; color:#1e293b; font-size:12px; }
  .meta-text { color:#64748b; font-size:11px; margin-top:2px; }
  .cta-wrap { text-align:center; margin:24px 0 8px; }
  .cta-btn { display:inline-block; background:#1d4ed8; color:#fff; text-decoration:none; font-size:14px; font-weight:700; padding:11px 26px; border-radius:6px; }
  .divider { border:none; border-top:1px solid #e2e8f0; margin:20px 0; }
  .footer { padding:14px 32px 20px; text-align:center; }
  .footer-text { font-size:11px; color:#94a3b8; line-height:1.7; }
  .unsub-link { color:#94a3b8; text-decoration:underline; }
</style>
</head>
<body>
<div class="wrapper">

  <div class="header">
    <div class="header-logo">DIU Student Services — Admin</div>
    <div class="header-badge">{{ $delivery === 'digest_hourly' ? 'HOURLY' : 'DAILY' }} DIGEST</div>
  </div>

  <div class="summary-bar">
    <span class="summary-count">{{ $itemCount }}</span>
    <span class="summary-text">update{{ $itemCount !== 1 ? 's' : '' }} from {{ $periodLabel }}</span>
  </div>

  <div class="body">
    <p class="greeting">Hi {{ $adminName }}, here is a summary of activity from {{ $periodLabel }}.</p>

    <table class="items">
      <thead>
        <tr>
          <th>Event</th>
          <th>Reference</th>
          <th>Student</th>
          <th>Time</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($items as $item)
        @php
          $badgeClass = match($item['event_type'] ?? '') {
            'new_submission'      => 'badge-new',
            'submission_resubmit' => 'badge-resub',
            'setting_change'      => 'badge-setting',
            'admin_comment'       => 'badge-comment',
            'sla_warning'         => 'badge-sla',
            default               => 'badge-new',
          };
        @endphp
        <tr>
          <td>
            <span class="event-badge {{ $badgeClass }}">{{ $eventLabel($item['event_type'] ?? '') }}</span>
          </td>
          <td>
            <div class="ref-text">{{ $item['ref'] ?? 'N/A' }}</div>
            <div class="meta-text">{{ $item['title'] ?? '' }}</div>
          </td>
          <td>
            <div class="ref-text">{{ $item['student'] ?? '—' }}</div>
            <div class="meta-text">{{ $item['department'] ?? '' }}</div>
          </td>
          <td>
            <div class="meta-text" style="white-space:nowrap">{{ $item['created_at'] ?? '' }}</div>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>

    <div class="cta-wrap">
      <a href="{{ $dashboardUrl }}" class="cta-btn">Open Admin Dashboard</a>
    </div>

    <hr class="divider">

    <p style="font-size:12px;color:#94a3b8;text-align:center">
      This is a batched digest. You can switch to immediate delivery or adjust your digest schedule in
      <a href="{{ $unsubscribeUrl }}" style="color:#1d4ed8">notification settings</a>.
    </p>
  </div>

  <div class="footer">
    <div class="footer-text">
      Daffodil International University · Student Services Admin<br>
      <a href="{{ $unsubscribeUrl }}" class="unsub-link">Manage notification preferences</a>
      &nbsp;&middot;&nbsp;
      <a href="{{ $dashboardUrl }}" class="unsub-link">Admin Dashboard</a>
    </div>
  </div>

</div>
</body>
</html>
