    <h2>CERTIFICATION</h2>

    <p>
        This is to certify that this {{ ucfirst($project->type) }} entitled "{{ $project->title }}"
        has been duly carried out and presented by {{ $project->user->name }} ({{ $project->student_id ?? 'Student ID' }})
        in the Department of {{ $project->course }}, Faculty of {{ ucwords($project->faculty ?? 'Science') }},
        {{ $project->full_university_name }}, under my supervision.
    </p>

    @if($project->certification_signatories && count($project->certification_signatories) > 0)
        @foreach($project->certification_signatories as $signatory)
            <div class="certification-entry">
                @if(isset($signatory['name']) && $signatory['name'])
                    <div class="role">{{ $signatory['name'] }}</div>
                @endif
                <div class="signature-line">
                    {{ $signatory['title'] ?? 'Signatory' }} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Signature & Date
                </div>
            </div>
        @endforeach
    @else
        <div class="certification-entry">
            <div class="role">{{ $project->supervisor_name ?? 'Dr. [Supervisor Name]' }}</div>
            <div class="signature-line">
                Supervisor &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Signature & Date
            </div>
        </div>

        <div class="certification-entry">
            <div class="signature-line">
                Center Director &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Signature & Date
            </div>
        </div>

        <div class="certification-entry">
            <div class="signature-line">
                Head of Department &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Signature & Date
            </div>
        </div>

        <div class="certification-entry">
            <div class="signature-line">
                Dean Faculty of {{ ucwords($project->faculty ?? 'Science') }} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Signature & Date
            </div>
        </div>

        <div class="certification-entry">
            <div class="signature-line">
                Dean School of Postgraduate Studies &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Signature & Date
            </div>
        </div>
    @endif
