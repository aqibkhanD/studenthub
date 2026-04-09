<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $headingText }}</title>
<style>
  body { margin:0; padding:0; background:#f1f5f9; font-family:'Segoe UI',Arial,sans-serif; font-size:14px; color:#1e293b; }
  .wrapper { max-width:600px; margin:32px auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,.08); }
  .header { background:{{ $accentColor }}; padding:24px 32px; }
  .header-logo { font-size:13px; font-weight:800; color:#fff; letter-spacing:.3px; opacity:.9; }
  .header-title { font-size:20px; font-weight:700; color:#fff; margin-top:12px; line-height:1.3; }
  .body { padding:28px 32px; }
  .greeting { font-size:15px; font-weight:600; margin-bottom:10px; }
  .body-text { font-size:14px; color:#475569; line-height:1.6; }
  .ref-block { background:#f8fafc; border:1px solid #e2e8f0; border-left:3px solid {{ $accentColor }}; border-radius:6px; padding:12px 16px; margin:20px 0; }
  .ref-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#94a3b8; }
  .ref-value { font-size:15px; font-weight:700; color:#1e293b; margin-top:4px; }
  .comment-block { background:#fffbeb; border:1px solid #fde68a; border-radius:6px; padding:14px 16px; margin:20px 0; }
  .comment-label { font-size:11px; font-weight:700; text-transform:uppercase; color:#92400e; letter-spacing:.5px; margin-bottom:6px; }
  .comment-text { font-size:13px; color:#78350f; line-height:1.6; }
  .deadline-block { background:#fff7ed; border:1px solid #fed7aa; border-radius:6px; padding:12px 16px; margin:16px 0; display:flex; align-items:center; gap:10px; }
  .deadline-label { font-size:12px; font-weight:600; color:#9a3412; }
  .deadline-value { font-size:13px; font-weight:700; color:#c2410c; }
  .cta-wrap { text-align:center; margin:28px 0 8px; }
  .cta-btn { display:inline-block; background:{{ $accentColor }}; color:#fff; text-decoration:none; font-size:14px; font-weight:700; padding:12px 28px; border-radius:6px; letter-spacing:.2px; }
  .divider { border:none; border-top:1px solid #e2e8f0; margin:24px 0; }
  .footer { padding:16px 32px 24px; text-align:center; }
  .footer-text { font-size:11px; color:#94a3b8; line-height:1.7; }
  .unsub-link { color:#94a3b8; text-decoration:underline; }
</style>
</head>
<body>
<div class="wrapper">

  <div class="header">
    <div class="header-logo">Daffodil International University — Student Services</div>
    <div class="header-title">{{ $headingText }}</div>
  </div>

  <div class="body">
    <div class="greeting">Dear {{ $studentName }},</div>
    <p class="body-text">{{ $bodyText }}</p>

    <div class="ref-block">
      <div class="ref-label">Reference Number</div>
      <div class="ref-value">{{ $ref }}</div>
    </div>

    @if ($adminComment)
    <div class="comment-block">
      <div class="comment-label">Note from Admin</div>
      <div class="comment-text">{{ $adminComment }}</div>
    </div>
    @endif

    @if ($deadline)
    <div class="deadline-block">
      <div>
        <div class="deadline-label">Response Required By</div>
        <div class="deadline-value">{{ $deadline }}</div>
      </div>
    </div>
    @endif

    @if ($eventType === 'certificate_ready')
    <p class="body-text" style="margin-top:16px">
      Your document is available in the Student Services portal. Please log in to download it. Documents are available for 30 days.
    </p>
    @endif

    @if ($eventType === 'submission_rejected' && $adminComment)
    <p class="body-text" style="margin-top:16px">
      If you believe this decision is incorrect, please contact the relevant department directly or submit a new request with additional supporting documents.
    </p>
    @endif

    <div class="cta-wrap">
      <a href="{{ $ctaUrl }}" class="cta-btn">{{ $ctaLabel }}</a>
    </div>

    <hr class="divider">

    <p class="body-text" style="font-size:12px">
      This email was sent because you have an active form submission in the DIU Student Services portal.
      You are receiving this because your notification preferences are set to immediate delivery for this event type.
    </p>
  </div>

  <div class="footer">
    <div class="footer-text">
      Daffodil International University · Student Services<br>
      102 Sukrabad, Mirpur Road, Dhanmondi, Dhaka-1207, Bangladesh<br><br>
      <a href="{{ $unsubscribeUrl }}" class="unsub-link">Manage notification preferences</a>
      &nbsp;&middot;&nbsp;
      <a href="{{ $ctaUrl }}" class="unsub-link">Student Portal</a>
    </div>
  </div>

</div>
</body>
</html>
