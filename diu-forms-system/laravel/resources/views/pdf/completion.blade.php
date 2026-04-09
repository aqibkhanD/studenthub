@extends('pdf.layout')

@section('content')

<div class="ref-block">
    <strong>Ref. No.:</strong> {{ $reference_no }}<br>
    <strong>Date:</strong> {{ $issue_date }}
</div>

<div class="doc-title">COMPLETION LETTER</div>
<div style="text-align: center; font-size: 9pt; color: #555555; margin-bottom: 1mm;">
    (Issued in Lieu of Original Degree Certificate)
</div>
<div class="doc-title-rule"><span></span></div>

<div class="salutation">To Whom It May Concern,</div>

<div class="body-para">
    This is to certify that <strong>{{ $student_name }}</strong>, bearing Student ID
    <strong>{{ $student_id }}</strong>, has successfully fulfilled all academic requirements
    for the degree of <strong>{{ $degree }}</strong> from the Department of
    <strong>{{ $department }}</strong>, Daffodil International University.
</div>

<div class="body-para">
    The student completed the programme in the academic session <strong>{{ $session }}</strong>
    and has been found eligible for the award of the aforementioned degree. The original degree
    certificate is currently under processing and will be issued in due course.
</div>

<div class="body-para">
    This letter is issued at the request of the student and may be used in lieu of the original
    certificate for the purpose of employment, further studies, or any other official requirement,
    subject to verification by the receiving authority.
</div>

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
            <td class="panel-label">Degree Awarded</td>
            <td class="panel-value">{{ $degree }}</td>
        </tr>
        <tr>
            <td class="panel-label">Department</td>
            <td class="panel-value">{{ $department }}</td>
        </tr>
        <tr>
            <td class="panel-label">Academic Session</td>
            <td class="panel-value">{{ $session }}</td>
        </tr>
        <tr>
            <td class="panel-label">CGPA</td>
            <td class="panel-value">{{ $cgpa }}</td>
        </tr>
        <tr>
            <td class="panel-label">Completion Date</td>
            <td class="panel-value">{{ $completion_date }}</td>
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
