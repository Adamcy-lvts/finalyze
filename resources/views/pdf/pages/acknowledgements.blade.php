<h2>ACKNOWLEDGEMENTS</h2>

@if($project->acknowledgements)
    <p>{!! nl2br(e($project->acknowledgements)) !!}</p>
@else
    <p>
        First and foremost, I am thankful to God Almighty for enabling me achieve this dream.
        This work has been a journey enriched by the presence of many people.
    </p>
    <p>
        I am grateful to my supervisor {{ $project->supervisor_name ?? 'Dr. [Supervisor Name]' }} for the
        invaluable scholarly advice and timeless effort despite a tight schedule. The contribution made it
        possible for the smooth completion of my research.
    </p>
    <p>
        I profoundly thank and appreciate the enormous support of {{ $project->full_university_name }}
        management for their timeless effort and guidance.
    </p>
@endif
