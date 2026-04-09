{{--
  Master PDF layout — DIU Student Services
  Used by all certificate/letter templates.
  Rendered by mPDF via PdfGenerationService::renderTemplate()
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
/* ── Page setup ─────────────────────────────────────── */
@page {
    margin: 18mm 22mm 22mm 22mm;
    margin-header: 8mm;
    margin-footer: 8mm;
    border: 1.2pt solid #0D2B4E;
    border-spacing: 2pt;
}

/* ── Typography ─────────────────────────────────────── */
body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 9.5pt;
    color: #1A1A1A;
    line-height: 1.55;
}
h1, h2, h3, h4 { font-family: Arial, Helvetica, sans-serif; }

/* ── Colours ─────────────────────────────────────────── */
.navy   { color: #0D2B4E; }
.gold   { color: #B8922A; }
.muted  { color: #555555; }

/* ── Header block ────────────────────────────────────── */
.letterhead {
    background-color: #0D2B4E;
    width: 100%;
    padding: 8mm 6mm;
    margin-bottom: 0;
}
.letterhead-logo-cell {
    width: 24mm;
    vertical-align: middle;
    text-align: center;
}
.logo-circle {
    width: 22mm;
    height: 22mm;
    border: 0.8pt solid #FFFFFF;
    border-radius: 50%;
    display: inline-block;
    line-height: 22mm;
    text-align: center;
    font-size: 7pt;
    color: #FFFFFF;
}
.letterhead-text-cell {
    vertical-align: middle;
    text-align: center;
}
.uni-name {
    font-size: 15pt;
    font-weight: bold;
    color: #FFFFFF;
    letter-spacing: 0.5pt;
}
.uni-address {
    font-size: 8pt;
    color: #BDD3EC;
    margin-top: 1mm;
}
.uni-contact {
    font-size: 7.5pt;
    color: #BDD3EC;
    margin-top: 0.5mm;
}

/* ── Gold rule ───────────────────────────────────────── */
.gold-rule {
    border: none;
    border-top: 2pt solid #B8922A;
    margin: 0 0 4mm 0;
}

/* ── Reference block ─────────────────────────────────── */
.ref-block {
    text-align: right;
    font-size: 8pt;
    color: #555555;
    margin-bottom: 4mm;
}

/* ── Document title ─────────────────────────────────── */
.doc-title {
    text-align: center;
    font-size: 14pt;
    font-weight: bold;
    color: #0D2B4E;
    letter-spacing: 0.8pt;
    margin-bottom: 1mm;
}
.doc-title-rule {
    text-align: center;
    margin-bottom: 5mm;
}
.doc-title-rule span {
    display: inline-block;
    border-bottom: 1.2pt solid #B8922A;
    width: 90mm;
}

/* ── Body text ───────────────────────────────────────── */
.salutation  { margin-bottom: 4mm; }
.body-para   { text-align: justify; margin-bottom: 4mm; }
.closing     { margin-top: 6mm; margin-bottom: 1mm; }

/* ── Student info panel ─────────────────────────────── */
.student-panel {
    background-color: #F7F9FC;
    border: 0.5pt solid #D0D9E4;
    border-left: 2.5pt solid #0D2B4E;
    padding: 3mm 4mm;
    margin: 5mm 0;
    border-radius: 2pt;
}
.student-panel table {
    width: 100%;
    border-collapse: collapse;
    font-size: 8.5pt;
}
.student-panel td {
    padding: 1.5mm 2mm;
    vertical-align: top;
    border-bottom: 0.3pt solid #E2E8F0;
}
.student-panel tr:last-child td { border-bottom: none; }
.panel-label {
    width: 42mm;
    font-weight: bold;
    color: #555555;
}
.panel-value { color: #1A1A1A; }

/* ── Signatory block ─────────────────────────────────── */
.signatory {
    margin-top: 8mm;
    font-size: 9pt;
}
.sig-line {
    border-bottom: 0.6pt solid #1A1A1A;
    width: 55mm;
    margin-bottom: 2mm;
    height: 12mm;   /* space for a signature image */
}
.sig-name  { font-weight: bold; font-size: 9pt; color: #1A1A1A; }
.sig-title { color: #555555; font-size: 8.5pt; }

/* ── Footer ─────────────────────────────────────────── */
.pdf-footer {
    border-top: 0.5pt solid #D0D9E4;
    padding-top: 2mm;
    font-size: 6.5pt;
    color: #777777;
    text-align: center;
    margin-top: 5mm;
}
.verify-code {
    font-family: Courier, monospace;
    font-size: 7pt;
    color: #0D2B4E;
    font-weight: bold;
}

/* ── Subject line (for letters) ─────────────────────── */
.subject-block {
    border-bottom: 0.8pt solid #B8922A;
    padding-bottom: 2mm;
    margin-bottom: 4mm;
    font-size: 9.5pt;
    font-weight: bold;
    color: #0D2B4E;
}
.to-block {
    font-size: 9pt;
    margin-bottom: 5mm;
    line-height: 1.6;
}
</style>
</head>
<body>

{{-- Letterhead --}}
<table class="letterhead" cellpadding="0" cellspacing="0">
    <tr>
        <td class="letterhead-logo-cell">
            <div class="logo-circle">
                @if(!empty($signatory['logo_path']) && file_exists(storage_path('app/' . $signatory['logo_path'])))
                    <img src="{{ storage_path('app/' . $signatory['logo_path']) }}" width="22mm" height="22mm">
                @else
                    DIU<br>LOGO
                @endif
            </div>
        </td>
        <td class="letterhead-text-cell">
            <div class="uni-name">DAFFODIL INTERNATIONAL UNIVERSITY</div>
            <div class="uni-address">Birulia, Savar, Dhaka-1216, Bangladesh</div>
            <div class="uni-contact">
                Tel: {{ $uni_phone ?? '+880-2-9138234' }}
                &nbsp;&nbsp;|&nbsp;&nbsp;
                Email: {{ $uni_email ?? 'info@diu.edu.bd' }}
                &nbsp;&nbsp;|&nbsp;&nbsp;
                {{ $uni_web ?? 'www.daffodilvarsity.edu.bd' }}
            </div>
        </td>
    </tr>
</table>

<hr class="gold-rule">

@yield('content')

{{-- Footer --}}
<div class="pdf-footer">
    <div>
        This is a system-generated document issued by {{ $signatory['department'] }},
        Daffodil International University.
    </div>
    <div style="margin-top: 1mm;">
        Verification Code: <span class="verify-code">{{ $verify_code }}</span>
        &nbsp;&nbsp;|&nbsp;&nbsp;
        Verify online at: <strong>forms.diu.edu.bd/verify/{{ $reference_no }}</strong>
    </div>
    <div style="margin-top: 1mm; color: #AAAAAA; font-size: 6pt;">
        Generated: {{ now()->format('d F Y, h:i A') }}
        &nbsp;&nbsp;|&nbsp;&nbsp;
        Unauthorised reproduction or alteration of this document constitutes a criminal offence.
    </div>
</div>

</body>
</html>
