<?php

namespace App\Services;

class TiptapToHtmlService
{
    /**
     * Convert Tiptap JSON (stored as string) into HTML.
     */
    public function convert(string $content): string
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return '';
        }

        // Already HTML
        if (str_starts_with($trimmed, '<')) {
            return $content;
        }

        $json = json_decode($trimmed, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($json)) {
            return nl2br(e($content));
        }

        return $this->tiptapNodeToHtml($json);
    }

    private function tiptapNodeToHtml(array $node): string
    {
        if (! isset($node['type'])) {
            return '';
        }

        $type = $node['type'];
        $content = $node['content'] ?? [];
        $marks = $node['marks'] ?? [];
        $attrs = $node['attrs'] ?? [];

        if ($type === 'text') {
            $text = htmlspecialchars($node['text'] ?? '', ENT_QUOTES, 'UTF-8');

            foreach ($marks as $mark) {
                $markType = $mark['type'] ?? '';

                $text = match ($markType) {
                    'bold' => "<strong>{$text}</strong>",
                    'italic' => "<em>{$text}</em>",
                    'underline' => "<u>{$text}</u>",
                    'code' => "<code>{$text}</code>",
                    'strike' => "<s>{$text}</s>",
                    'link' => '<a href="'.htmlspecialchars($mark['attrs']['href'] ?? '#', ENT_QUOTES, 'UTF-8').'">'.$text.'</a>',
                    default => $text,
                };
            }

            return $text;
        }

        $childrenHtml = '';
        foreach ($content as $child) {
            if (is_array($child)) {
                $childrenHtml .= $this->tiptapNodeToHtml($child);
            }
        }

        return match ($type) {
            'doc' => $childrenHtml,
            'paragraph' => '<p>'.$childrenHtml.'</p>',
            'heading' => $this->renderHeading($attrs, $childrenHtml),
            'bulletList' => '<ul>'.$childrenHtml.'</ul>',
            'orderedList' => '<ol>'.$childrenHtml.'</ol>',
            'listItem' => '<li>'.$childrenHtml.'</li>',
            'blockquote' => '<blockquote>'.$childrenHtml.'</blockquote>',
            'hardBreak' => '<br/>',
            'horizontalRule' => '<hr/>',
            'codeBlock' => '<pre><code>'.htmlspecialchars($node['text'] ?? strip_tags($childrenHtml), ENT_QUOTES, 'UTF-8').'</code></pre>',
            'image' => $this->renderImage($attrs),
            'table' => '<table><tbody>'.$childrenHtml.'</tbody></table>',
            'tableRow' => '<tr>'.$childrenHtml.'</tr>',
            'tableCell' => $this->renderTableCell($attrs, $childrenHtml),
            'tableHeader' => '<th>'.$childrenHtml.'</th>',
            default => $childrenHtml,
        };
    }

    private function renderHeading(array $attrs, string $childrenHtml): string
    {
        $level = (int) ($attrs['level'] ?? 1);
        $level = max(1, min(6, $level));

        return '<h'.$level.'>'.$childrenHtml.'</h'.$level.'>';
    }

    private function renderImage(array $attrs): string
    {
        $src = $attrs['src'] ?? '';
        if (! is_string($src) || trim($src) === '') {
            return '';
        }

        $alt = htmlspecialchars((string) ($attrs['alt'] ?? ''), ENT_QUOTES, 'UTF-8');

        return '<img src="'.htmlspecialchars($src, ENT_QUOTES, 'UTF-8').'" alt="'.$alt.'" />';
    }

    private function renderTableCell(array $attrs, string $childrenHtml): string
    {
        $colspan = (int) ($attrs['colspan'] ?? 1);
        $rowspan = (int) ($attrs['rowspan'] ?? 1);

        $attrHtml = '';
        if ($colspan > 1) {
            $attrHtml .= ' colspan="'.$colspan.'"';
        }
        if ($rowspan > 1) {
            $attrHtml .= ' rowspan="'.$rowspan.'"';
        }

        return '<td'.$attrHtml.'>'.$childrenHtml.'</td>';
    }
}

