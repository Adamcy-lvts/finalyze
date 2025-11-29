    <h2>ABSTRACT</h2>

    @if($project->abstract)
        <p>{!! nl2br(e($project->abstract)) !!}</p>
    @else
        <p>
            This {{ $project->type }} investigated {{ strtolower($project->title) }}.
            The research was conducted at {{ $project->full_university_name }} in the
            Department of {{ $project->course }}.
        </p>
    @endif
