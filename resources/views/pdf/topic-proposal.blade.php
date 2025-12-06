<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Topic Proposal</title>
    <style>
        @page {
            margin: 2cm;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.5;
            color: #333;
            background: white;
        }

        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
        }

        .header h1 {
            font-size: 14pt;
            font-weight: 700;
            text-transform: uppercase;
            margin: 0 0 5px 0;
            color: #111;
            letter-spacing: 0.5px;
        }

        .header h2 {
            font-size: 10pt;
            font-weight: 500;
            margin: 0 0 3px 0;
            color: #555;
        }

        .header h3 {
            font-size: 9pt;
            font-weight: 400;
            margin: 0;
            color: #666;
        }

        .proposal-badge {
            display: inline-block;
            background-color: #f3f4f6;
            color: #111;
            font-size: 8pt;
            font-weight: 600;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 4px;
            margin-top: 15px;
            letter-spacing: 1px;
        }

        .section {
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 9pt;
            font-weight: 700;
            text-transform: uppercase;
            color: #888;
            letter-spacing: 1px;
            margin-bottom: 10px;
            border-bottom: 1px solid #eee;
            padding-bottom: 6px;
        }

        .info-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .info-grid td {
            padding: 5px 0;
            vertical-align: top;
        }

        .info-label {
            width: 140px;
            color: #666;
            font-weight: 500;
        }

        .info-value {
            color: #111;
            font-weight: 600;
        }

        .topic-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .topic-title {
            font-size: 12pt;
            font-weight: 700;
            color: #111;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .topic-description {
            font-size: 10pt;
            color: #444;
            text-align: justify;
            white-space: pre-wrap;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 7pt;
            color: #999;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }

        .signature-section {
            margin-top: 40px;
            page-break-inside: avoid;
        }

        .signature-grid {
            width: 100%;
            margin-top: 30px;
        }

        .signature-box {
            border-top: 1px solid #ccc;
            width: 200px;
            padding-top: 8px;
            text-align: center;
            font-size: 8pt;
            color: #666;
        }
    </style>
</head>

<body>
    <!-- Header Section -->
    <div class="header">
        <h1>{{ $project->full_university_name }}</h1>
        <h2>Faculty of {{ ucwords($project->facultyRelation->name) }}</h2>
        <h3>Department of {{ $project->departmentRelation->name }}</h3>
        
        <div class="proposal-badge">Project Topic Proposal</div>
    </div>

    <!-- Student Information -->
    <div class="section">
        <div class="section-title">Student Details</div>
        <table class="info-grid">
            <tr>
                <td class="info-label">Student Name:</td>
                <td class="info-value">{{ $project->user->name }}</td>
            </tr>
            @if($project->user->matric_no)
            <tr>
                <td class="info-label">Student ID:</td>
                <td class="info-value">{{ $project->user->matric_no }}</td>
            </tr>
            @endif
            @if($project->course)
            <tr>
                <td class="info-label">Program:</td>
                <td class="info-value">{{ $project->course }}</td>
            </tr>
            @endif
            @if($project->field_of_study)
            <tr>
                <td class="info-label">Field of Study:</td>
                <td class="info-value">{{ $project->field_of_study }}</td>
            </tr>
            @endif
            @if($project->type)
            <tr>
                <td class="info-label">Level:</td>
                <td class="info-value">{{ ucfirst($project->type) }}</td>
            </tr>
            @endif
            @if($project->session)
            <tr>
                <td class="info-label">Session:</td>
                <td class="info-value">{{ now()->year }}/{{ now()->year + 1 }}</td>
            </tr>
            @endif
        </table>
    </div>

    <!-- Project Topic -->
    <div class="section">
        <div class="section-title">Proposed Research Topic</div>

        <div class="topic-box">
            @if ($project->topic)
                <div class="topic-title">{{ $project->topic }}</div>
            @else
                <div class="topic-title" style="color: #999; font-style: italic;">No topic selected yet</div>
            @endif
            
            @if($project->description)
                <div class="topic-description">{{ $project->description }}</div>
            @endif
        </div>
    </div>

    <!-- Signature Section -->
    <div class="signature-section">
        <div class="section-title">Approval</div>
        <table class="signature-grid">
            <tr>
                <td align="left">
                    <div class="signature-box">
                        Student's Signature & Date
                    </div>
                </td>
                <td align="right">
                    <div class="signature-box">
                        Supervisor's Signature & Date
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Generated by Finalyze • {{ now()->format('F j, Y') }}
    </div>
</body>

</html>
