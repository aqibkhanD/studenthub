@extends('pdf.layout')

@section('content')

<div class="ref-block">
    <strong>Ref. No.:</strong> {{ $reference_no }}<br>
    <strong>Date:</strong> {{ $issue_date }}
</div>

<div class="doc-title">CERTIFICATE OF BONAFIDE STUDENT</div>
<div class="doc-title-rule"><span></span></div>

<div class="salutation">To Whom It May Concern,</div>

<div class="body-para">
    This is to certify that <strong>{{ $student_name }}</strong> bearing Student ID
    <strong>{{ $student_id }}</strong> is a bonafide student of this university.
    The student is currently enrolled in the <strong>{{ $program }}</strong> programme
    under the Department of <strong>{{ $department }}</strong> and is presently in
    <strong>{{ $semester }}</strong> semester, Batch <strong>{{ $batch }}</strong>.
</div>

<div class="body-para">
    This certificate is issued upon the request of the student for the purpose of
    <strong>{{ $purpose }}</strong> and is valid for the academic session
    <strong>{{ $session }}</strong>.
</div>

<div class="body-para">
    The student's conduct and character, to the best of our knowledge, has been satisfactory
    during the period of study at this institution. We wish the student every success in all
    future endeavours.
</div>

{{-- Student info panel --}}
<div class="student-panel">
    <table>
        <tr>
            <td class="panel-label">Student Name</td>
            <td class="panel-value">{{ $student_name }}</td>
        </tr>
        <tr>
            <td class="panel-label">Student ID</td>
            <td class="panel-value">{{ $student_id }}</td>
        </tr>
        <tr>
            <td class="panel-label">Programme</td>
            <td class="panel-value">{{ $program }}</td>
        </tr>
        <tr>
            <td class="panel-label">Department</td>
            <td class="panel-value">{{ $department }}</td>
        </tr>
        <tr>
            <td class="panel-label">Semester</td>
            <td class="panel-value">{{ $semester }}</td>
        </tr>
        <tr>
            <td class="panel-label">Batch</td>
            <td class="panel-value">{{ $batch }}</td>
        </tr>
        <tr>
            <td class="panel-label">Academic Session</td>
            <td class="panel-value">{{ $session }}</td>
        </tr>
        <tr>
            <td class="panel-label">Enrollment Status</td>
            <td class="panel-value">Active</td>
        </tr>
    </table>
</div>

<div class="closing">Yours sincerely,</div>

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
