@extends('pdf.layout')

@section('content')

<div class="ref-block">
    <strong>Ref. No.:</strong> {{ $reference_no }}<br>
    <strong>Date:</strong> {{ $issue_date }}
</div>

<div class="to-block">
    <strong>To,</strong><br>
    {{ $addressed_to }}<br>
    @if(!empty($org_address)){{ $org_address }}@endif
</div>

<div class="subject-block">
    Subject: Letter of Support for Internship — {{ $student_name }} ({{ $student_id }})
</div>

<div class="salutation">Dear Sir/Madam,</div>

<div class="body-para">
    We are pleased to introduce <strong>{{ $student_name }}</strong>, Student ID
    <strong>{{ $student_id }}</strong>, currently enrolled in the
    <strong>{{ $program }}</strong> programme, {{ $semester }} semester,
    at the Department of <strong>{{ $department }}</strong>,
    Daffodil International University.
</div>

<div class="body-para">
    The student has applied for an internship position at your esteemed organisation
    for a duration of <strong>{{ $duration }}</strong>, commencing
    <strong>{{ $start_date }}</strong>.
    We understand the internship will focus on <strong>{{ $focus_area }}</strong>.
</div>

<div class="body-para">
    We are confident that the student possesses the requisite academic background and personal
    qualities to contribute meaningfully to your organisation. We request your kind consideration
    and support for this internship placement. Please feel free to contact us should you require
    any further information.
</div>

<div class="closing">Yours faithfully,</div>

<div class="signatory">
    <div class="sig-line">
        @if(!empty($signatory['signature_image']))
            <img src="{{ storage_path('app/private/signatures/' . $signatory['signature_image']) }}"
                 height="12mm" style="max-width: 55mm;">
        @endif
    </div>
    <div class="sig-name">{{ $signatory['name'] }}</div>
    <div class="sig-title">{{ $signatory['title'] }}</div>
    <div class="sig-title">{{ $signatory['department'] }}</div>
    <div class="sig-title">{{ $signatory['institution'] }}</div>
</div>

@endsection
