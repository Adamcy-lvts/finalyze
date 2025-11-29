<h2>List of Abbreviations and Acronyms</h2>

    @if(isset($project->abbreviations) && count($project->abbreviations) > 0)
        @foreach($project->abbreviations as $abbr => $meaning)
            <div class="list-item"><strong>{{ $abbr }}</strong> – {{ $meaning }}</div>
        @endforeach
    @else
        <div class="list-item"><strong>CD</strong> – Compact Disc</div>
        <div class="list-item"><strong>CD-ROM</strong> – Compact Disc-Read-Only Memory</div>
        <div class="list-item"><strong>DIRs</strong> – Digital Information Resources</div>
        <div class="list-item"><strong>DVD</strong> – Digital Versatile Disc</div>
        <div class="list-item"><strong>ICT</strong> – Information and Communication Technologies</div>
        <div class="list-item"><strong>IT</strong> – Information Technology</div>
    @endif
