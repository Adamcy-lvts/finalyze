<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Topic Proposal</title>
    <style>
        body {
            font-family: 'Times New Roman', serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #000;
            margin: 20px;
            background: white;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #000;
            padding-bottom: 20px;
        }
        
        .header h1 {
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0 0 8px 0;
            letter-spacing: 1px;
        }
        
        .header h2 {
            font-size: 13pt;
            font-weight: bold;
            margin: 0 0 5px 0;
        }
        
        .header h3 {
            font-size: 12pt;
            margin: 0 0 15px 0;
        }
        
        .proposal-title {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 15px 0 5px 0;
        }
        
        .proposal-subtitle {
            font-size: 10pt;
            margin: 0;
        }
        
        .section-title {
            font-size: 12pt;
            font-weight: bold;
            margin: 25px 0 15px 0;
            border-bottom: 1px solid #000;
            padding-bottom: 3px;
        }
        
        .info-item {
            margin-bottom: 8px;
            font-size: 11pt;
        }
        
        .info-item strong {
            font-weight: bold;
        }
        
        .project-title {
            text-align: center;
            font-weight: bold;
            font-size: 12pt;
            margin: 15px 0;
            padding: 10px;
            border: 1px solid #000;
        }
        
        .topic-description {
            font-size: 11pt;
            line-height: 1.5;
            text-align: justify;
            margin: 10px 0;
            padding: 10px;
            border: 1px solid #000;
        }
    </style>
</head>

<body>
    <!-- Header Section -->
    <div class="header">
        <h1>{{ $project->full_university_name }}</h1>
        <h2>Faculty of {{ ucwords($project->faculty) }}</h2>
        <h3>Department of {{ $project->course }}</h3>
        
        <div class="proposal-title">PROJECT TOPIC PROPOSAL</div>
        <p class="proposal-subtitle">For {{ ucfirst($project->type) }} {{ $project->category->name ?? 'Project' }}</p>
    </div>

    <!-- Student Information -->
    <div>
        <div class="section-title">STUDENT INFORMATION</div>
        <div class="info-item">
            <strong>Student Name:</strong> {{ $project->user->name }}
        </div>
        <div class="info-item">
            <strong>Course of Study:</strong> {{ $project->course }}
        </div>
        <div class="info-item">
            <strong>Field of Study:</strong> {{ $project->field_of_study }}
        </div>
        <div class="info-item">
            <strong>Project Type:</strong> {{ ucfirst($project->type) }}
        </div>
        <div class="info-item">
            <strong>Academic Session:</strong> {{ now()->year }}/{{ now()->year + 1 }}
        </div>
    </div>

    <!-- Project Topic -->
    <div>
        <div class="section-title">PROPOSED PROJECT TOPIC</div>

        @if ($project->topic)
            <div>
                <strong>Project Topic:</strong>
                <div class="project-title">{{ $project->topic }}</div>
            </div>
        @endif

        <div>
            <strong>Topic Description:</strong>
            <div class="topic-description">{{ $project->description }}</div>
        </div>
    </div>
</body>

</html>
