<?php

namespace App\Services;

use App\Models\Project;

class ProjectPrelimService
{
    public function __construct(
        protected PreliminaryPageTemplateService $templateService,
        protected TemplateVariableService $variableService
    ) {}

    /**
     * Resolve preliminary pages for a project using:
     * 1) Project overrides
     * 2) Default templates
     * 3) Hardcoded fallbacks
     * Then substitute template variables.
     *
     * @return array<int, array{slug:string,title:string,html:string}>
     */
    public function resolve(Project $project): array
    {
        $defaults = $this->templateService->getAllTemplates();

        $pages = [
            'declaration' => 'Declaration',
            'certification' => 'Certification',
            'dedication' => 'Dedication',
            'acknowledgements' => 'Acknowledgements',
            'abstract' => 'Abstract',
        ];

        $resolved = [];

        foreach ($pages as $slug => $title) {
            $content = $project->{$slug} ?? null;

            if (empty($content) && isset($defaults[$slug])) {
                $content = $defaults[$slug];
            }

            if (empty($content)) {
                $content = $this->fallbackContent($slug, $project);
            }

            $resolved[] = [
                'slug' => $slug,
                'title' => $title,
                'html' => $this->variableService->substituteVariables($content, $project),
            ];
        }

        return $resolved;
    }

    private function fallbackContent(string $slug, Project $project): string
    {
        return match ($slug) {
            'declaration' => $this->fallbackDeclaration(),
            'certification' => $this->fallbackCertification($project),
            'dedication' => $this->fallbackDedication(),
            'acknowledgements' => $this->fallbackAcknowledgements(),
            'abstract' => $this->fallbackAbstract(),
            default => '<p></p>',
        };
    }

    private function fallbackDeclaration(): string
    {
        return <<<'HTML'
<p>
    I, <strong>{{student_name}}</strong>, with Student Registration Number
    <strong>{{student_id:or:[....................]}}</strong>, declare that this {{project_type}}
    titled <strong>"{{project_title}}"</strong> is my original work and has not been
    submitted for the award of any degree or diploma in this or any other university.
</p>
<div class="signature-line">
    Student's Signature &amp; Date
</div>
HTML;
    }

    private function fallbackCertification(Project $project): string
    {
        if ($project->certification_signatories && count($project->certification_signatories) > 0) {
            $entries = '<p>
    This is to certify that this {{project_type}} entitled "{{project_title}}"
    has been duly carried out and presented by {{student_name}}{{student_id_inline}}
    in the Department of {{course}}, Faculty of {{faculty}},
    {{full_university_name}}, under my supervision.
</p>';

            foreach ($project->certification_signatories as $signatory) {
                $name = $signatory['name'] ?? '';
                $title = $signatory['title'] ?? 'Signatory';

                $entries .= <<<HTML
<div class="certification-entry">
    <div class="role">{$name}</div>
    <div class="signature-line">
        {$title} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Signature &amp; Date
    </div>
</div>
HTML;
            }

            return $entries;
        }

        return <<<'HTML'
<p>
    This is to certify that this {{project_type}} entitled "{{project_title}}"
    has been duly carried out and presented by {{student_name}}{{student_id_inline}}
    in the Department of {{course}}, Faculty of {{faculty}},
    {{full_university_name}}, under my supervision.
</p>

<div class="certification-entry">
    <div class="role">{{supervisor_name:or:[Supervisor]}}</div>
    <div class="signature-line">
        Supervisor &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Signature &amp; Date
    </div>
</div>

<div class="certification-entry">
    <div class="signature-line">
        Center Director &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Signature &amp; Date
    </div>
</div>

<div class="certification-entry">
    <div class="signature-line">
        Head of Department &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Signature &amp; Date
    </div>
</div>

<div class="certification-entry">
    <div class="signature-line">
        Dean Faculty of {{faculty}} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Signature &amp; Date
    </div>
</div>

<div class="certification-entry">
    <div class="signature-line">
        Dean School of Postgraduate Studies &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Signature &amp; Date
    </div>
</div>
HTML;
    }

    private function fallbackDedication(): string
    {
        return <<<'HTML'
<p>
    I dedicate this research work firstly to God Almighty the maker of heaven and the earth
    and also to my family members for their unwavering support throughout this journey.
</p>
HTML;
    }

    private function fallbackAcknowledgements(): string
    {
        return <<<'HTML'
<p>
    First and foremost, I am thankful to God Almighty for enabling me achieve this dream.
    This work has been a journey enriched by the presence of many people.
</p>
<p>
    I am grateful to my supervisor {{supervisor_name:or:[Dr. [Supervisor Name]]}} for the
    invaluable scholarly advice and timeless effort despite a tight schedule. The contribution made it
    possible for the smooth completion of my research.
</p>
<p>
    I profoundly thank and appreciate the enormous support of {{full_university_name}}
    management for their timeless effort and guidance.
</p>
HTML;
    }

    private function fallbackAbstract(): string
    {
        return <<<'HTML'
<p>
    This {{project_type}} investigated {{project_topic}}.
    The research was conducted at {{full_university_name}} in the
    Department of {{course}}.
</p>
HTML;
    }
}
