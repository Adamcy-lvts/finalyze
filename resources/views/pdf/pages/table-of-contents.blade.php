<h2>Table of Contents</h2>

    <div class="toc-item">
        <span class="toc-chapter">Title Page</span>
        <span class="toc-page-num"></span>
    </div>
    <div class="toc-item">
        <span class="toc-chapter">Declaration</span>
        <span class="toc-page-num">i</span>
    </div>
    <div class="toc-item">
        <span class="toc-chapter">Certification</span>
        <span class="toc-page-num">ii</span>
    </div>
    <div class="toc-item">
        <span class="toc-chapter">Dedication</span>
        <span class="toc-page-num">iii</span>
    </div>
    <div class="toc-item">
        <span class="toc-chapter">Acknowledgements</span>
        <span class="toc-page-num">iv</span>
    </div>
    <div class="toc-item">
        <span class="toc-chapter">Abstract</span>
        <span class="toc-page-num">v</span>
    </div>
    <div class="toc-item">
        <span class="toc-chapter">Table of Contents</span>
        <span class="toc-page-num">vi</span>
    </div>
    <div class="toc-item">
        <span class="toc-chapter">List of Tables</span>
        <span class="toc-page-num">vii</span>
    </div>
    <div class="toc-item">
        <span class="toc-chapter">List of Abbreviations</span>
        <span class="toc-page-num">viii</span>
    </div>

    @foreach($chapters as $chapter)
        <div class="toc-item">
            <span class="toc-chapter">Chapter {{ $chapter->chapter_number }}: {{ $chapter->title }}</span>
            <span class="toc-page-num">{{ $loop->iteration }}</span>
        </div>
    @endforeach

    <div class="toc-item">
        <span class="toc-chapter">References</span>
        <span class="toc-page-num">{{ count($chapters) + 1 }}</span>
    </div>

    <div class="toc-item">
        <span class="toc-chapter">Appendices</span>
        <span class="toc-page-num">{{ count($chapters) + 2 }}</span>
    </div>
