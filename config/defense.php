<?php

return [
    'default_question_limit' => 10,
    'default_difficulty' => 'undergraduate',
    'chair_id' => 'generalist',
    'panelist_priority' => [
        'methodologist',
        'skeptic',
        'theorist',
        'practitioner',
    ],
    'personas' => [
        'skeptic' => [
            'id' => 'skeptic',
            'name' => 'The Skeptic',
            'role' => 'Critical Reviewer',
            'questioningStyle' => 'aggressive',
            'focusAreas' => ['methodology_flaws', 'sample_size', 'bias', 'generalizability'],
            'difficultyModifier' => 1.2,
        ],
        'methodologist' => [
            'id' => 'methodologist',
            'name' => 'The Methodologist',
            'role' => 'Technical Expert',
            'questioningStyle' => 'methodical',
            'focusAreas' => ['research_design', 'data_analysis', 'validity', 'reliability'],
            'difficultyModifier' => 1.0,
        ],
        'generalist' => [
            'id' => 'generalist',
            'name' => 'The Generalist',
            'role' => 'Value Reviewer',
            'questioningStyle' => 'supportive',
            'focusAreas' => ['contribution', 'practical_implications', 'future_research'],
            'difficultyModifier' => 0.8,
        ],
        'theorist' => [
            'id' => 'theorist',
            'name' => 'The Theorist',
            'role' => 'Framework Expert',
            'questioningStyle' => 'methodical',
            'focusAreas' => ['theoretical_framework', 'literature_gaps', 'conceptual_clarity'],
            'difficultyModifier' => 1.1,
        ],
        'practitioner' => [
            'id' => 'practitioner',
            'name' => 'The Practitioner',
            'role' => 'Industry Expert',
            'questioningStyle' => 'supportive',
            'focusAreas' => ['real_world_application', 'industry_relevance', 'implementation'],
            'difficultyModifier' => 0.9,
        ],
    ],
];
