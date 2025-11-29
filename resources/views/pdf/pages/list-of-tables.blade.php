<h2>List of Tables</h2>

    @if(isset($project->tables) && count($project->tables) > 0)
        @foreach($project->tables as $table)
            <div class="list-item">{{ $table }}</div>
        @endforeach
    @else
        <div class="list-item">Table 4.1: Distribution of Respondents by Age</div>
        <div class="list-item">Table 4.2: Academic Qualification of Respondents</div>
        <div class="list-item">Table 4.3: Gender Distribution of the Respondents</div>
    @endif
