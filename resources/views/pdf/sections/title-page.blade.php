<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $project->title }} - Title Page</title>
    @include('pdf.sections.base-styles')
</head>
<body>
    <div class="title-page">
        <div class="university">{!! strtoupper($project->title) !!}</div>
        <div class="main-title">BY</div>
        <div class="author-name">{{ strtoupper($project->user->name) }}</div>
        @if($project->student_id)
            <div class="student-id">{{ $project->student_id }}</div>
        @endif

        <div class="dissertation-text">
            A {{ strtoupper($project->type) }} SUBMITTED TO THE SCHOOL OF<br>
            POST-GRADUATE STUDIES IN PARTIAL FULFILMENT FOR THE REQUIREMENTS<br>
            OF THE AWARD OF THE DEGREE OF {{ strtoupper($project->degree ?? 'MASTERS') }} ({{ strtoupper($project->degree_abbreviation ?? 'M.Sc.') }}) IN {{ strtoupper($project->course ?? 'COMPUTER SCIENCE') }}
        </div>

        <div class="institution-details">
            AT THE DEPARTMENT OF<br>
            {{ strtoupper($project->course ?? 'COMPUTER SCIENCE') }}<br>
            FACULTY OF {{ strtoupper($project->faculty ?? 'SCIENCE') }}<br><br>
            {{ strtoupper($project->full_university_name) }}
        </div>

        <div class="date">{{ strtoupper(now()->format('F, Y')) }}</div>
    </div>
</body>
</html>
