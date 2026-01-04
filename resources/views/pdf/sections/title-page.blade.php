<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $project->title }} - Title Page</title>
    @include('pdf.sections.base-styles')
</head>
<body class="preliminary-page">
    <div class="title-page">
        <div class="university">{!! strtoupper($project->title) !!}</div>
        <div class="main-title">BY</div>
        <div class="author-name">{{ strtoupper($project->student_name ?: $project->user->name) }}</div>
        @if($project->student_id)
            <div class="student-id">{{ $project->student_id }}</div>
        @endif

        @php
            $academicLevel = $project->academic_level;
            $isPostgraduate = $academicLevel === 'postgraduate';
            $documentType = strtoupper($project->document_type);

            $rawType = strtolower((string) ($project->type ?? ''));
            $derivedDegree = match ($rawType) {
                'phd', 'doctorate' => 'Doctor of Philosophy',
                'mba' => 'Master of Business Administration',
                'ma' => 'Master of Arts',
                'masters', 'msc' => 'Master of Science',
                'undergraduate', 'bachelor', 'honors', 'hnd', 'nd' => 'Bachelor of Science',
                default => null,
            };

            $derivedDegreeAbbrev = match ($rawType) {
                'phd', 'doctorate' => 'Ph.D.',
                'mba' => 'MBA',
                'ma' => 'M.A.',
                'masters', 'msc' => 'M.Sc.',
                'undergraduate', 'bachelor', 'honors', 'hnd', 'nd' => 'B.Sc.',
                default => null,
            };

            $defaultPostgradDegree = $project->document_type === 'thesis' ? 'Doctor of Philosophy' : 'Master of Science';
            $defaultPostgradAbbrev = $project->document_type === 'thesis' ? 'Ph.D.' : 'M.Sc.';

            $degree = $project->degree ?: ($isPostgraduate ? $defaultPostgradDegree : $derivedDegree);
            $degreeAbbrev = $project->degree_abbreviation ?: ($isPostgraduate ? $defaultPostgradAbbrev : $derivedDegreeAbbrev);
            $course = $project->course ?: $project->field_of_study;
            $departmentLabel = $project->getEffectiveDepartment() ?: data_get($project->settings, 'department') ?: $course;
            $facultyName = $project->getEffectiveFaculty() ?: $project->faculty;
            $universityName = $project->full_university_name
                ?: ($project->universityRelation?->name ?? $project->university);
        @endphp

        <div class="dissertation-text">
            A {{ $documentType }} SUBMITTED TO THE {{ $isPostgraduate ? 'SCHOOL OF' : 'DEPARTMENT OF' }}<br>
            {{ $isPostgraduate ? 'POST-GRADUATE STUDIES' : ($departmentLabel ? strtoupper($departmentLabel) : 'DEPARTMENT') }} IN PARTIAL FULFILMENT FOR THE REQUIREMENTS<br>
            OF THE AWARD OF THE DEGREE OF {{ strtoupper($degree) }}
            @if($degreeAbbrev)
                ({{ strtoupper($degreeAbbrev) }})
            @endif
            @if($course)
                IN {{ strtoupper($course) }}
            @endif
        </div>

        <div class="institution-details">
            AT THE DEPARTMENT OF<br>
            {{ strtoupper($departmentLabel ?? 'DEPARTMENT') }}<br>
            FACULTY OF {{ strtoupper($facultyName ?? 'FACULTY') }}<br><br>
            {{ strtoupper($universityName ?? 'UNIVERSITY') }}
        </div>

        <div class="date">{{ strtoupper(now()->format('F, Y')) }}</div>
    </div>
</body>
</html>
