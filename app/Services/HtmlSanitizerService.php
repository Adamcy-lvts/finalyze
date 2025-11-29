<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

class HtmlSanitizerService
{
    protected HTMLPurifier $purifier;

    public function __construct()
    {
        $config = HTMLPurifier_Config::createDefault();

        // Allow common formatting and structure tags
        $config->set('HTML.Allowed', implode(',', [
            // Text formatting
            'p', 'br', 'strong', 'em', 'u', 's', 'sub', 'sup', 'mark',
            // Headings
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            // Lists
            'ul', 'ol', 'li',
            // Tables
            'table', 'thead', 'tbody', 'tr', 'th', 'td',
            // Links and media
            'a[href|title|target]',
            'img[src|alt|title|width|height]',
            // Quotes and code
            'blockquote', 'q', 'code', 'pre',
            // Other
            'div[class]', 'span[class]', 'hr',
        ]));

        // Allow style attribute for color and highlighting
        $config->set('HTML.AllowedAttributes', [
            'a.href',
            'a.title',
            'a.target',
            'img.src',
            'img.alt',
            'img.title',
            'img.width',
            'img.height',
            'div.class',
            'span.class',
            'td.colspan',
            'td.rowspan',
            'th.colspan',
            'th.rowspan',
            '*.style', // Allow inline styles for colors etc.
        ]);

        // Allow color and background-color in style
        $config->set('CSS.AllowedProperties', [
            'color',
            'background-color',
            'text-align',
            'font-weight',
            'font-style',
            'text-decoration',
        ]);

        // Safe URL protocols
        $config->set('URI.AllowedSchemes', [
            'http' => true,
            'https' => true,
            'mailto' => true,
        ]);

        // Auto-paragraph disabled since content already has proper tags
        $config->set('AutoFormat.AutoParagraph', false);
        $config->set('AutoFormat.RemoveEmpty', false);

        // UTF-8 encoding
        $config->set('Core.Encoding', 'UTF-8');

        // Cache directory
        $cacheDir = storage_path('app/htmlpurifier');
        if (! file_exists($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        $config->set('Cache.SerializerPath', $cacheDir);

        $this->purifier = new HTMLPurifier($config);
    }

    /**
     * Sanitize HTML content.
     */
    public function sanitize(?string $html): ?string
    {
        if (empty($html)) {
            return $html;
        }

        return $this->purifier->purify($html);
    }

    /**
     * Sanitize multiple HTML fields.
     *
     * @param  array<string, string|null>  $fields
     * @return array<string, string|null>
     */
    public function sanitizeFields(array $fields): array
    {
        $sanitized = [];

        foreach ($fields as $key => $value) {
            $sanitized[$key] = $this->sanitize($value);
        }

        return $sanitized;
    }
}
