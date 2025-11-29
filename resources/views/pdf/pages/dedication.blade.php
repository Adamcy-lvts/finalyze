    <h2>DEDICATION</h2>

    @if($project->dedication)
        <p>{!! nl2br(e($project->dedication)) !!}</p>
    @else
        <p>
            I dedicate this research work firstly to God almighty the maker of heaven and the earth and also to my
            family members for their unwavering support throughout this journey.
        </p>
    @endif
