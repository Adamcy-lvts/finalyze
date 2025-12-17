<?php

namespace App\Services;

class PreliminaryPageTemplateService
{
    /**
     * Get default dedication template.
     */
    public function getDedicationTemplate(): string
    {
        return <<<'HTML'
<p>I dedicate this research work firstly to God Almighty for His guidance, protection, and blessings throughout this academic journey.</p>

<p>To my beloved parents, <strong>[Parent Names]</strong>, whose unwavering support, sacrifices, and encouragement have been the foundation of my success.</p>

<p>To my family and friends, <strong>[Names]</strong>, for their constant love and motivation.</p>

<p>This work is dedicated to all those who believed in me and supported me throughout this journey.</p>
HTML;
    }

    /**
     * Get default acknowledgements template.
     */
    public function getAcknowledgementsTemplate(): string
    {
        return <<<'HTML'
<p>First and foremost, I am thankful to God Almighty for His infinite mercy, guidance, and strength throughout the course of this research.</p>

<p>I wish to express my profound gratitude to my supervisor, <strong>{{supervisor_name}}</strong>, for his/her invaluable guidance, constructive criticism, and unwavering support throughout this research work. Your mentorship has been instrumental in shaping this project.</p>

<p>I am deeply grateful to the faculty and staff of <strong>{{faculty}}</strong>, <strong>{{full_university_name}}</strong>, for providing an enabling academic environment and the resources necessary for this research.</p>

<p>My sincere appreciation goes to all the lecturers in the <strong>{{department}}</strong> for their dedication to academic excellence and for imparting the knowledge that formed the foundation of this work.</p>

<p>I profoundly thank <strong>[Names of people to acknowledge]</strong> for their support, encouragement, and contributions to this research.</p>

<p>Special thanks to my colleagues and friends, <strong>[Names]</strong>, for their collaborative spirit, intellectual discussions, and moral support throughout this academic journey.</p>

<p>Finally, I am forever indebted to my family for their unconditional love, patience, and sacrifices. Your support has been my source of strength.</p>

<p>Thank you all.</p>
HTML;
    }

    /**
     * Get default abstract template.
     */
    public function getAbstractTemplate(): string
    {
        return <<<'HTML'
<p>This {{project_type}} investigated <strong>[research topic/problem]</strong> in the context of <strong>{{field_of_study}}</strong>. The study was motivated by <strong>[research gap or problem statement]</strong>.</p>

<p>The research aimed to <strong>[primary objectives]</strong>. The methodology employed included <strong>[research methods, data collection techniques, and analysis approaches]</strong>.</p>

<p>The findings revealed that <strong>[key findings and results]</strong>. The study also identified <strong>[significant patterns, relationships, or insights]</strong>.</p>

<p>Based on these findings, the research concludes that <strong>[main conclusions]</strong>. It is recommended that <strong>[key recommendations for practice, policy, or further research]</strong>.</p>

<p>This study contributes to the field of {{field_of_study}} by <strong>[theoretical or practical contributions]</strong> and provides a foundation for future research in this area.</p>

<p><em>Keywords: [keyword 1], [keyword 2], [keyword 3], [keyword 4], [keyword 5]</em></p>
HTML;
    }

    /**
     * Get default declaration template.
     */
    public function getDeclarationTemplate(): string
    {
        return <<<'HTML'
<p>I, <strong>{{student_name}}</strong>, with Student Registration Number <strong>{{student_id}}</strong>, declare that this {{project_type}} titled <strong>"{{project_title}}"</strong> is my original work and has not been submitted for the award of any degree or diploma in this or any other university.</p>

<p>All sources of information have been duly acknowledged through proper citations and references.</p>

<div class="signature-section">
    <p><strong>Student's Signature:</strong> _____________________</p>
    <p><strong>Date:</strong> _____________________</p>
</div>
HTML;
    }

    /**
     * Get default certification template.
     */
    public function getCertificationTemplate(): string
    {
        return <<<'HTML'
<div class="certification-content">
    <p>
        This is to certify that this <strong>{{project_type}}</strong> entitled <strong>"{{project_title}}"</strong> has been duly
        carried out and presented by <strong>{{student_name}}</strong>{{student_id_inline}} in the Department of {{department:or:[Department]}},
        Faculty of {{faculty:or:[Faculty]}}, {{full_university_name:or:[University]}}, under my supervision.
    </p>

    <p>
        The work is original and has met the required standards for academic research at this institution.
    </p>

    <div class="certification-signatures">
        <div class="signature-block">
            <div class="signature-name"><strong>{{supervisor_name:or:[Supervisor Name]}}</strong></div>
            <div class="signature-role">Supervisor</div>
            <table class="signature-table" role="presentation">
                <tr>
                    <td class="label">Signature:</td>
                    <td class="line"></td>
                    <td class="label">Date:</td>
                    <td class="line"></td>
                </tr>
            </table>
        </div>
    </div>
</div>
HTML;
    }

    /**
     * Get all available templates.
     *
     * @return array<string, string>
     */
    public function getAllTemplates(): array
    {
        return [
            'dedication' => $this->getDedicationTemplate(),
            'acknowledgements' => $this->getAcknowledgementsTemplate(),
            'abstract' => $this->getAbstractTemplate(),
            'declaration' => $this->getDeclarationTemplate(),
            'certification' => $this->getCertificationTemplate(),
        ];
    }

    /**
     * Get template by type.
     */
    public function getTemplate(string $type): ?string
    {
        return match ($type) {
            'dedication' => $this->getDedicationTemplate(),
            'acknowledgements' => $this->getAcknowledgementsTemplate(),
            'abstract' => $this->getAbstractTemplate(),
            'declaration' => $this->getDeclarationTemplate(),
            'certification' => $this->getCertificationTemplate(),
            default => null,
        };
    }
}
