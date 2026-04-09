<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Analytics Report — StudentsHub</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'DejaVu Sans', Arial, sans-serif;
      font-size: 11px;
      color: #111827;
      background: #fff;
      padding: 32px 36px;
    }

    /* ---- Header ---- */
    .report-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      border-bottom: 2px solid #2563eb;
      padding-bottom: 14px;
      margin-bottom: 20px;
    }
    .report-title { font-size: 18px; font-weight: 700; color: #1e40af; }
    .report-sub   { font-size: 11px; color: #6b7280; margin-top: 3px; }
    .report-meta  { text-align: right; font-size: 10px; color: #9ca3af; line-height: 1.6; }

    /* ---- Section headings ---- */
    .section-title {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #6b7280;
      margin: 20px 0 8px;
    }

    /* ---- Stat grid ---- */
    .stat-grid {
      display: table;
      width: 100%;
      border-collapse: separate;
      border-spacing: 6px;
    }
    .stat-grid-row { display: table-row; }
    .stat-cell {
      display: table-cell;
      width: 16.6%;
      background: #f9fafb;
      border: 1px solid #e5e7eb;
      border-radius: 6px;
      padding: 10px 12px;
      vertical-align: top;
    }
    .stat-value { font-size: 20px; font-weight: 700; color: #111827; }
    .stat-label { font-size: 9px; color: #9ca3af; margin-top: 2px; }
    .stat-green  .stat-value { color: #16a34a; }
    .stat-yellow .stat-value { color: #ca8a04; }
    .stat-red    .stat-value { color: #dc2626; }

    /* ---- Tables ---- */
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 4px;
    }
    th {
      background: #f3f4f6;
      text-align: left;
      font-size: 10px;
      font-weight: 700;
      color: #6b7280;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      padding: 7px 10px;
      border-bottom: 1px solid #e5e7eb;
    }
    td {
      padding: 7px 10px;
      border-bottom: 1px solid #f3f4f6;
      font-size: 11px;
      vertical-align: middle;
    }
    tr:last-child td { border-bottom: none; }
    tr:nth-child(even) td { background: #fafafa; }

    /* ---- SLA bar ---- */
    .bar-wrap  { background: #e5e7eb; border-radius: 4px; height: 6px; width: 120px; display: inline-block; vertical-align: middle; }
    .bar-inner { border-radius: 4px; height: 6px; }
    .bar-green  { background: #16a34a; }
    .bar-yellow { background: #ca8a04; }
    .bar-red    { background: #dc2626; }

    /* ---- Status pills ---- */
    .pill {
      display: inline-block;
      padding: 1px 7px;
      border-radius: 9px;
      font-size: 9px;
      font-weight: 600;
    }
    .pill-blue   { background: #dbeafe; color: #1d4ed8; }
    .pill-green  { background: #dcfce7; color: #166534; }
    .pill-red    { background: #fee2e2; color: #991b1b; }
    .pill-orange { background: #ffedd5; color: #9a3412; }
    .pill-gray   { background: #f3f4f6; color: #374151; }

    /* ---- Two-column layout ---- */
    .two-col { display: table; width: 100%; border-spacing: 12px; }
    .col     { display: table-cell; vertical-align: top; width: 50%; }

    /* ---- Footer ---- */
    .footer {
      margin-top: 28px;
      border-top: 1px solid #e5e7eb;
      padding-top: 8px;
      font-size: 9px;
      color: #d1d5db;
      text-align: center;
    }
  </style>
</head>
<body>

  <!-- Header -->
  <div class="report-header">
    <div>
      <div class="report-title">Analytics Report</div>
      <div class="report-sub">StudentsHub — Daffodil International University</div>
      <div class="report-sub">Scope: {{ $reportScope }} &nbsp;·&nbsp; Period: last {{ $days }} days</div>
    </div>
    <div class="report-meta">
      Generated: {{ $generatedAt }}<br>
      Period: {{ $from->format('d M Y') }} – {{ now()->format('d M Y') }}
    </div>
  </div>

  <!-- Overview stats -->
  <div class="section-title">Overview</div>
  <table class="stat-grid">
    <tr class="stat-grid-row">
      <td class="stat-cell">
        <div class="stat-value">{{ number_format($totalSubmissions) }}</div>
        <div class="stat-label">Total submissions</div>
      </td>
      <td class="stat-cell">
        <div class="stat-value">{{ number_format($submissionsInPeriod) }}</div>
        <div class="stat-label">Submitted ({{ $days }}d)</div>
      </td>
      <td class="stat-cell">
        <div class="stat-value">{{ number_format($resolvedInPeriod) }}</div>
        <div class="stat-label">Resolved ({{ $days }}d)</div>
      </td>
      <td class="stat-cell">
        <div class="stat-value">{{ number_format($pendingCount) }}</div>
        <div class="stat-label">Pending</div>
      </td>
      <td class="stat-cell">
        <div class="stat-value">{{ $avgResolution !== null ? round($avgResolution) . 'h' : '—' }}</div>
        <div class="stat-label">Avg resolution</div>
      </td>
      <td class="stat-cell {{ $slaCompliancePct >= 90 ? 'stat-green' : ($slaCompliancePct >= 70 ? 'stat-yellow' : 'stat-red') }}">
        <div class="stat-value">{{ $slaCompliancePct }}%</div>
        <div class="stat-label">SLA compliance</div>
      </td>
    </tr>
  </table>

  <!-- Department performance -->
  <div class="section-title">Department Performance</div>
  <table>
    <thead>
      <tr>
        <th>Department</th>
        <th style="text-align:right">Submitted ({{ $days }}d)</th>
        <th style="text-align:right">Open</th>
        <th style="text-align:right">SLA Breached</th>
        <th style="text-align:center; width:150px">SLA Health</th>
      </tr>
    </thead>
    <tbody>
      @forelse($departments as $dept)
        @php
          $compliance = $dept->total_submissions > 0
            ? round((($dept->total_submissions - $dept->sla_breached) / $dept->total_submissions) * 100)
            : 100;
          $barClass = $compliance >= 90 ? 'bar-green' : ($compliance >= 70 ? 'bar-yellow' : 'bar-red');
        @endphp
        <tr>
          <td>{{ $dept->name }}</td>
          <td style="text-align:right">{{ $dept->total_submissions }}</td>
          <td style="text-align:right">{{ $dept->open_submissions }}</td>
          <td style="text-align:right; color:{{ $dept->sla_breached > 0 ? '#dc2626' : '#16a34a' }}; font-weight:600">
            {{ $dept->sla_breached }}
          </td>
          <td style="text-align:center">
            <span class="bar-wrap">
              <span class="bar-inner {{ $barClass }}" style="width:{{ $compliance }}%"></span>
            </span>
            <span style="margin-left:6px; font-size:9px; color:#6b7280">{{ $compliance }}%</span>
          </td>
        </tr>
      @empty
        <tr><td colspan="5" style="text-align:center;color:#9ca3af;padding:16px">No department data available.</td></tr>
      @endforelse
    </tbody>
  </table>

  <!-- Status breakdown + Overdue -->
  <div class="two-col" style="margin-top:4px">
    <div class="col">
      <div class="section-title">Submission Status Breakdown</div>
      <table>
        <thead>
          <tr>
            <th>Status</th>
            <th style="text-align:right">Count</th>
          </tr>
        </thead>
        <tbody>
          @forelse($statusBreakdown as $status => $count)
            <tr>
              <td>{{ ucwords(str_replace('_', ' ', $status)) }}</td>
              <td style="text-align:right; font-weight:600">{{ number_format($count) }}</td>
            </tr>
          @empty
            <tr><td colspan="2" style="color:#9ca3af;text-align:center">No data.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="col">
      <div class="section-title">Most Overdue Submissions</div>
      <table>
        <thead>
          <tr>
            <th>Reference</th>
            <th>Dept</th>
            <th style="text-align:right">Hours over</th>
          </tr>
        </thead>
        <tbody>
          @forelse($overdue as $item)
            <tr>
              <td style="font-family:monospace; font-size:10px; color:#2563eb">{{ $item['reference_no'] }}</td>
              <td style="color:#6b7280">{{ $item['department'] ?? '—' }}</td>
              <td style="text-align:right; color:#dc2626; font-weight:600">+{{ $item['hours_overdue'] }}h</td>
            </tr>
          @empty
            <tr><td colspan="3" style="color:#16a34a;text-align:center">No overdue submissions.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="footer">
    This report was generated automatically by StudentsHub. For queries contact the IT department.
  </div>

</body>
</html>
