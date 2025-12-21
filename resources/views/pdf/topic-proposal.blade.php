<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;900&family=Roboto:wght@300;400;500;700&display=swap');

        @page {
            margin: 0;
            size: A4;
        }

        body {
            font-family: 'Roboto', sans-serif;
            font-size: 9pt;
            line-height: 1.5;
            color: #1f2937;
            background: #ffffff;
            margin: 0;
            padding: 0;
        }

        /* Accent Bar */
        .accent-bar {
            height: 5px;
            background: linear-gradient(to right, #f59e0b, #d97706);
            width: 100%;
            position: absolute;
            top: 0;
            left: 0;
        }

        .container {
            padding: 2cm 2cm 1.5cm 2cm;
            position: relative;
            overflow: hidden;
        }

        /* Watermark */
        .watermark {
            position: absolute;
            top: 2cm;
            right: 1.5cm;
            font-family: 'Poppins', sans-serif;
            font-size: 48pt;
            font-weight: 900;
            color: #f59e0b; /* Amber brand color */
            text-transform: uppercase;
            letter-spacing: 3px;
            transform: rotate(-12deg);
            z-index: 0;
            pointer-events: none;
            user-select: none;
            opacity: 0.12; /* Transparent effect */
        }

        /* Header */
        .header {
            margin-bottom: 25px;
            border-bottom: 2px solid #f3f4f6;
            padding-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            position: relative;
            z-index: 1;
        }

        .university-info h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 15pt;
            font-weight: 700;
            color: #111827;
            margin: 0 0 4px 0;
            text-transform: uppercase;
            letter-spacing: -0.3px;
        }

        .university-info h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 10pt;
            font-weight: 500;
            color: #4b5563;
            margin: 0 0 2px 0;
        }

        .university-info h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 9pt;
            font-weight: 400;
            color: #6b7280;
            margin: 0;
        }

        /* Section Styling */
        .section {
            margin-bottom: 25px;
            position: relative;
            z-index: 1;
        }

        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .section-title {
            font-family: 'Poppins', sans-serif;
            font-size: 8.5pt;
            font-weight: 700;
            text-transform: uppercase;
            color: #9ca3af;
            letter-spacing: 1.1px;
        }

        .section-line {
            flex-grow: 1;
            height: 1px;
            background-color: #f3f4f6;
            margin-left: 10px;
        }

        /* Info Grid */
        .info-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 6px;
        }

        .label-cell {
            width: 130px;
            color: #6b7280;
            font-weight: 500;
            font-size: 9pt;
        }

        .svalue-cell {
            color: #111827;
            font-weight: 600;
            font-size: 9.5pt;
        }

        /* Topic Box */
        .topic-container {
            background-color: #f9fafb;
            border-left: 4px solid #f59e0b;
            padding: 15px 20px;
            border-radius: 0 6px 6px 0;
        }

        .topic-title {
            font-family: 'Poppins', sans-serif;
            font-size: 11pt;
            font-weight: 700;
            color: #111827;
            margin-bottom: 10px;
            line-height: 1.35;
        }

        .topic-description {
            font-size: 9pt;
            color: #374151;
            text-align: justify;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #e5e7eb;
            line-height: 1.6;
            font-family: 'Roboto', sans-serif;
        }
        
        .topic-description p {
            margin: 0 0 10px 0;
        }
        .topic-description p:last-child {
            margin-bottom: 0;
        }

        /* Approval Box */
        .approval-grid {
            width: 100%;
            margin-top: 30px;
        }

        .signature-box {
            border-top: 1px solid #d1d5db;
            width: 85%;
            padding-top: 8px;
            text-align: center;
        }

        .signature-label {
            font-family: 'Poppins', sans-serif;
            font-size: 7.5pt;
            font-weight: 600;
            text-transform: uppercase;
            color: #6b7280;
            letter-spacing: 0.5px;
        }

        .date-line {
            margin-top: 35px;
            font-size: 8pt;
            color: #9ca3af;
        }

        /* Footer */
        .footer {
            position: fixed;
            bottom: 1cm;
            left: 2cm;
            right: 2cm;
            border-top: 1px solid #f3f4f6;
            padding-top: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 7.5pt;
            color: #9ca3af;
            z-index: 10;
        }
        
        /* Helpers */
        .text-right { text-align: right; }
        .w-half { width: 50%; }
    </style>
</head>
<body>
    <div class="accent-bar"></div>

    <div class="container">
        <!-- Watermark Badge -->
        <div class="watermark">Proposal</div>

        <!-- Header -->
        <table style="width: 100%; margin-bottom: 30px; border-bottom: 2px solid #f3f4f6; padding-bottom: 15px; position: relative; z-index: 1;">
            <tr>
                <td style="vertical-align: bottom;">
                    <div class="university-info">
                        <h1>{{ $project->universityRelation->name ?? 'University Name' }}</h1>
                        <h2>Faculty of {{ $project->facultyRelation->name ?? 'Faculty' }}</h2>
                        <h3>Department of {{ $project->departmentRelation->name ?? 'Department' }}</h3>
                    </div>
                </td>
                <td>
                    <!-- Space for watermark -->
                </td>
            </tr>
        </table>

        <!-- Student Details -->
        <div class="section">
            <div class="section-header">
                <span class="section-title">Student Profile</span>
                <span class="section-line"></span>
            </div>
            
            <table class="info-table">
                <tr>
                    <td class="label-cell">Student Name</td>
                    <td class="svalue-cell">{{ $project->student_name ?: $project->user->name }}</td>
                </tr>
                @if($project->user->matric_no)
                <tr>
                    <td class="label-cell">Matriculation No.</td>
                    <td class="svalue-cell">{{ $project->user->matric_no }}</td>
                </tr>
                @endif
                @if($project->course)
                <tr>
                    <td class="label-cell">Program</td>
                    <td class="svalue-cell">{{ $project->course }}</td>
                </tr>
                @endif
                <tr>
                    <td class="label-cell">Current Level</td>
                    <td class="svalue-cell">{{ ucfirst($project->type ?? $project->academic_level ?? 'Undergraduate') }}</td>
                </tr>
                <tr>
                    <td class="label-cell">Academic Session</td>
                    <td class="svalue-cell">{{ now()->year }}/{{ now()->year + 1 }}</td>
                </tr>
            </table>
        </div>

        <!-- Research Topic -->
        <div class="section">
            <div class="section-header">
                <span class="section-title">Research Proposal</span>
                <span class="section-line"></span>
            </div>

            <div class="topic-container">
                <div class="topic-title">
                    {!! $project->topic ?? $project->title ?? 'Untitled Project Topic' !!}
                </div>
                
                @if($project->description)
                <div class="topic-description">
                    {!! $project->description !!}
                </div>
                @endif
            </div>
        </div>

        <!-- Approval -->
        <div class="section" style="margin-top: 30px;">
             <div class="section-header">
                <span class="section-title">Supervisor Approval</span>
                <span class="section-line"></span>
            </div>

            <table class="approval-grid">
                <tr>
                    <td class="w-half" style="padding-right: 15px;">
                        <div class="signature-box" style="margin-top: 40px;">
                            <div class="signature-label">Student Signature</div>
                        </div>
                        <div class="date-line">Date: ____________________</div>
                    </td>
                    <td class="w-half" style="padding-left: 15px;">
                        <div class="signature-box" style="margin-top: 40px; margin-left: auto;">
                            <div class="signature-label">Supervisor Signature</div>
                        </div>
                        <div class="date-line text-right">Date: ____________________</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <div style="display: flex; align-items: center;">
            Generated by 
            <svg xmlns="http://www.w3.org/2000/svg" xml:space="preserve" viewBox="0 0 1561.3 468.1" style="height: 14px; margin-left: 6px; fill: #6b7280;">
                <g>
                    <path d="M1560.6 275.4h-167.4c-6.7 0-6.7 0-5 6.5 7.5 28.1 26.5 43.3 54.4 47.7 26.7 4.2 50.8-1.8 70.3-21.8.3-.3.7-.5 1.5-1 10.6 11.4 21.3 22.8 32.8 35.2-7.2 6.1-14.1 12.6-21.7 18.1-24.9 17.9-53.1 22.6-83.1 20.4-20.6-1.5-39.9-7-57.6-17.6-32.2-19.3-50.6-48.1-55.4-84.8-4.3-32.9.1-64.6 19.5-92.9 20-29.2 47.5-46.4 82.8-50.4 30.6-3.5 58.9 2.5 84.3 20.6 22.8 16.3 34.8 39.3 41.1 65.8 4.4 17.7 4.8 35.6 3.5 54.2zm-56.6-39.3c.3-25.1-19.5-55.1-56-55-38.9.2-58 28-60 55h116zM603.3 204.9c-1.8-3.6-3.6-6.8-5.2-10.2-4.5-9-8.7-18.1-13.6-26.9-2.2-3.9-1.3-5.6 2.1-7.8 17.8-11.6 36.8-20.3 58-23.7 23.9-3.8 47.8-3.8 71.2 2.9 36.4 10.4 57.3 34.8 63.2 72 1.2 7.7 1.9 15.6 1.9 23.4.2 46 .1 92 .1 138 0 1.2-.1 2.4-.2 3.8h-53.9v-24.2c-2.6 1.9-4.5 2.9-6 4.4-12.9 12.3-27.9 20.8-45.6 23-32.7 4-63.3-.9-87.1-26.4-10.5-11.3-16.5-25-17.2-40.7-.8-17.2 2.2-33.3 13.3-47.1 11.8-14.7 27.6-23.5 45.8-26.9 13.5-2.5 27.5-3.2 41.3-3.8 15.5-.6 31.1-.2 46.6-.1 3.5 0 4.5-1.1 4.3-4.4-1.7-27.3-16.6-44.8-44.6-47.4-26.1-2.4-49.4 4.7-70.5 19.8-1.2.7-2.4 1.3-3.9 2.3zm119.4 68.7c-18.7 0-36.2-.5-53.6.2-7.5.3-15.2 2.2-22.3 4.7-10.8 3.9-17 12.2-17.8 23.9-.9 12.9 4.3 22.8 15.8 29.2 9.8 5.4 20.3 5.9 31.2 5.4 15.6-.8 28-8 37.7-19.7 3.5-4.2 6.5-9.8 7.4-15.2 1.3-9.3 1.1-18.8 1.6-28.5zM478.5 376.3v-25.2c0-34.5.1-69-.1-103.5-.1-13-1.3-25.9-6.9-37.9-9.2-19.6-26.5-26.4-45.7-24.5-28.6 2.9-45.3 20.1-51.1 49.6-1.2 6.3-1.7 12.9-1.7 19.3-.2 34.8-.1 69.7-.1 104.5v17.7h-58.5V137.5h55.7v26.9c.4.1.8.3 1.1.4 2.9-3 5.5-6.2 8.7-8.8 23.7-19.7 51.3-24.3 81-21.4 20.3 2 37.6 10.6 51.7 24.9 12.5 12.7 18.8 28.9 21.9 46.1 1.8 9.7 2.7 19.7 2.7 29.6.3 45.5.1 91.1.1 136.6 0 1.3-.1 2.6-.2 4.2-19.4.3-38.7.3-58.6.3zM94.8 188.1v188H36.5V188H0v-47.6h36.9c0-8.3-.4-16.2.1-24.1C38.1 96.9 43.5 79 56.4 64c12.5-14.3 28.4-22.4 47-25.5 24.3-4 47.3-.6 69.6 11.3-5.1 15.3-10.2 30.5-15 45.1-8.6-2.4-16.7-5.5-25-6.5-6.5-.8-13.7-.1-19.9 1.9-10 3.2-15.6 11.3-17 21.5-1.2 9.2-1.2 18.5-1.8 28.3h61.4c.2 1.4.5 2.4.4 3.3 0 13.4-.2 26.8 0 40.3 0 3.5-1.2 4.3-4.4 4.3-17.2-.1-34.4 0-51.6 0-1.6.1-3.2.1-5.3.1zM1237.5 185h-95.2c8.7-11.5 17-22.4 25.2-33.2 1.4-1.8 3.1-3.4 4.1-5.4 3.9-7.9 10-9.7 18.6-9.5 39.2.7 78.4.6 117.6.5 3.8 0 4.5 1.2 4.4 4.6-.2 10.1.1 20.2-.1 30.3 0 2.2-.7 4.9-2.1 6.6-21.6 28.1-43.4 56.1-65.2 84.1-15.6 20.1-31.1 40.2-46.6 60.3-.9 1.1-1.6 2.4-3 4.4h120.2v48.5h-196.7c-.1-1.1-.3-2.4-.3-3.7 0-7.8.5-15.7-.2-23.5-.6-8.1 2-14.3 7-20.5 22.1-27.8 43.8-55.9 65.7-83.8 14.6-18.6 29.2-37.1 43.8-55.7 1-.9 1.6-2.1 2.8-4zM886.2 376.4h-58.3V41.3h58.3v335.1zM918.3 163.9c19.6-4.4 39.3-7.8 59.5-6.1 9.1.7 15.2 7 20.1 14 9.4 13.2 18.4 26.7 27.6 40.1.6.9 1.2 1.8 1.8 2.6.3.3.8.4 1.7.9 1.6-2.2 3.3-4.5 4.9-6.8 23.6-34.2 50.3-65.8 78.5-96.1 35.8-38.5 75.5-72.1 121.3-98.2 9.2-5.3 19.3-8.9 29.1-13.3 1.3-.6 2.9-.6 4.7-1-4.8 5.1-9.2 9.9-13.7 14.5-18.3 18.7-37.5 36.7-54.9 56.3-20.3 22.8-39.8 46.4-58.2 70.8-31.8 42-58.9 87.2-85.8 132.4-3 5-6.1 10-9.6 14.7-7.2 9.8-17.2 11.5-25.9 3.3-7.5-7-14.3-15-20.2-23.5-11.2-16.1-21.3-33.1-32.4-49.3-13.2-19.4-29.5-36.1-46.5-52.2-.8-.7-1.7-1.2-2.6-1.8.2-.4.4-.9.6-1.3zM206.6 137.6h57.9v238.7h-57.9V137.6z" />
                    <path d="M995.3 303.3c2.8 2.3 7.2 5.7 11.5 9.2 15.8 13 37.4 11 49.2-5.6 6.8-9.5 11.7-20.2 17.4-30.4.8-1.5 1.4-3.1 3.2-4.4 0 1.1.2 2.2 0 3.3-5.3 27.1-10.5 54.2-16.1 81.3-4.2 20.5-9.5 40.7-18.2 59.7-8.8 19.4-21.3 35.6-41.4 44.5-14 6.2-28.6 7.8-43.7 6.8-18.5-1.3-35.1-7.8-50-18.5-.5-.3-.9-.7-1.6-1.4 7-14.5 14-28.9 21.1-43.5 5.6 3 10.3 5.7 15.3 8.1 8.4 3.9 17.2 5.4 26.4 4 13.2-2 21.3-10.5 25.3-22.4 2.8-8.4 5-17.5 5.2-26.3.3-17-1-34-1.8-51-.5-5.3-1.5-10.7-1.8-13.4zM236.2 100.8c-22.3.3-38.8-18.1-38.3-37.2.5-20.5 17.4-36.5 39.3-36.4 19.8.1 36.9 17.5 36.8 37.5-.1 20.3-16.7 36.2-37.8 36.1z" />
                </g>
            </svg>
        </div>
        <div>
            {{ now()->format('d/m/Y') }}
        </div>
    </div>
</body>
</html>
