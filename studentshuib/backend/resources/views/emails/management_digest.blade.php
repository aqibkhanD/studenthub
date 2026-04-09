<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>StudentsHub Weekly Digest</title>
  <style>
    body { margin: 0; padding: 0; background: #f3f4f6; font-family: Arial, sans-serif; color: #111827; }
    .wrapper { max-width: 600px; margin: 32px auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
    .header { background: #1d4ed8; padding: 28px 32px; }
    .header-title { color: #fff; font-size: 20px; font-weight: 700; margin: 0; }
    .header-sub    { color: #bfdbfe; font-size: 13px; margin: 4px 0 0; }
    .body { padding: 28px 32px; }
    .greeting { font-size: 14px; color: #374151; margin-bottom: 20px; }

    .stat-row { display: table; width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; }
    .stat-cell { display: table-cell; text-align: center; background: #f9fafb; border: 1px solid #e5e7eb; padding: 16px 8px; }
    .stat-cell:first-child { border-radius: 8px 0 0 8px; }
    .stat-cell:last-child  { border-radius: 0 8px 8px 0; }
    .stat-value { font-size: 24px; font-weight: 700; color: #1d4ed8; display: block; }
    .stat-label { font-size: 11px; color: #9ca3af; display: block; margin-top: 3px; }
    .stat-red   .stat-value { color: #dc2626; }
    .stat-green .stat-value { color: #16a34a; }

    .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #6b7280; margin: 24px 0 10px; border-bottom: 1px solid #e5e7eb; padding-bottom: 6px; }

    table.data { width: 100%; border-collapse: collapse; font-size: 13px; }
    table.data th { text-align: left; font-size: 11px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.04em; padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
    table.data td { padding: 8px 8px; border-bottom: 1px solid #f9fafb; vertical-align: middle; }
    table.data tr:last-child td { border-bottom: none; }
    .mono { font-family: monospace; font-size: 12px; color: #2563eb; }
    .red  { color: #dc2626; font-weight: 600; }
    .green { color: #16a34a; }

    .footer { background: #f9fafb; padding: 18px 32px; font-size: 11px; color: #9ca3af; text-align: center; line-height: 1.6; }
    .footer a { color: #2563eb; text-decoration: none; }
  </style>
</head>
<body>
  <div class="wrapper">

    <!-- Header -->
    <div class="header">
      <p class="header-title">StudentsHub Weekly Digest</p>
      <p class="header-sub">{{ $dateRange }} &nbsp;·&nbsp; Daffodil International University</p>
    </div>

    <div class="body">
      <p class="greeting">Hello {{ $recipientName }},</p>
      <p style="font-size:13px;color:#6b7280;margin-bottom:20px">
        Here is your {{ $days }}-day operational summary for the student services portal.
      </p>

      <!-- Key numbers -->
      <div class="stat-row">
        <div class="stat-cell">
          <span class="stat-value">{{ number_format($submissionsInPeriod) }}</span>
          <span class="stat-label">Submitted</span>
        </div>
        <div class="stat-cell">
          <span class="stat-value stat-green">{{ number_format($resolvedInPeriod) }}</span>
          <span class="stat-label">Resolved</span>
        </div>
        <div class="stat-cell">
          <span class="stat-value">{{ number_format($pendingCount) }}</span>
          <span class="stat-label">Pending</span>
        </div>
        <div class="stat-cell {{ $slaBreachedCount > 0 ? 'stat-red' : '' }}">
          <span class="stat-value">{{ number_format($slaBreachedCount) }}</span>
          <span class="stat-label">SLA Breaches</span>
        </div>
        <div class="stat-cell">
          <span class="stat-value">{{ $avgResolution !== null ? $avgResolution . 'h' : '—' }}</span>
          <span class="stat-label">Avg Resolution</span>
        </div>
      </div>

      <!-- Department breakdown -->
      @if($departments->count() > 0)
      <div class="section-title">Top Departments This Period</div>
      <table class="data">
        <thead>
          <tr>
            <th>Department</th>
            <th style="text-align:right">Submitted</th>
            <th style="text-align:right">Open</th>
            <th style="text-align:right">SLA Breached</th>
          </tr>
        </thead>
        <tbody>
          @foreach($departments as $dept)
          <tr>
            <td>{{ $dept->name }}</td>
            <td style="text-align:right">{{ $dept->week_submissions }}</td>
            <td style="text-align:right">{{ $dept->open_submissions }}</td>
            <td style="text-align:right" class="{{ $dept->sla_breached > 0 ? 'red' : 'green' }}">
              {{ $dept->sla_breached }}
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @endif

      <!-- Most overdue -->
      @if(count($overdue) > 0)
      <div class="section-title">Most Overdue Submissions</div>
      <table class="data">
        <thead>
          <tr>
            <th>Reference</th>
            <th>Form Type</th>
            <th>Department</th>
            <th style="text-align:right">Hours Over</th>
          </tr>
        </thead>
        <tbody>
          @foreach($overdue as $item)
          <tr>
            <td class="mono">{{ $item['reference_no'] }}</td>
            <td style="color:#6b7280">{{ $item['form_type'] }}</td>
            <td style="color:#6b7280">{{ $item['department'] }}</td>
            <td style="text-align:right" class="red">+{{ $item['hours_overdue'] }}h</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @else
      <p style="font-size:13px;color:#16a34a;margin-top:16px">
        No overdue submissions at the time of this report.
      </p>
      @endif

    </div><!-- /body -->

    <div class="footer">
      Generated {{ $generatedAt }} &nbsp;·&nbsp; StudentsHub &nbsp;·&nbsp; Daffodil International University<br>
      You are receiving this because you hold a management or administrator role.<br>
      To unsubscribe, ask your system administrator to update your account role.
    </div>

  </div>
</body>
</html>
