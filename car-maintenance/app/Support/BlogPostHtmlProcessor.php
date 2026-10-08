<?php

namespace App\Support;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;

class BlogPostHtmlProcessor
{
    /**
     * Inject anchor ids into h2/h3 headings and extract a table of contents.
     *
     * @return array{html: string, toc: array<int, array{level: int, text: string, id: string}>}
     */
    public function process(string $bodyHtml): array
    {
        if (trim($bodyHtml) === '') {
            return ['html' => $bodyHtml, 'toc' => []];
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"?><div id="blog-root">'.$bodyHtml.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $toc = [];
        $usedIds = [];

        foreach ($xpath->query('//h2|//h3') as $heading) {
            $text = trim((string) $heading->textContent);

            if ($text === '') {
                continue;
            }

            $base = Str::slug($text) ?: 'section';
            $id = $base;
            $suffix = 2;

            while (isset($usedIds[$id])) {
                $id = $base.'-'.($suffix++);
            }

            $usedIds[$id] = true;
            $heading->setAttribute('id', $id);
            $toc[] = [
                'level' => (int) $heading->tagName[1],
                'text' => $text,
                'id' => $id,
            ];
        }

        $root = $document->getElementById('blog-root');
        $html = $root ? $this->innerHtml($document, $root) : $bodyHtml;

        return ['html' => $html, 'toc' => $toc];
    }

    /**
     * Extract a table of contents without mutating the HTML (used at save time
     * when the processed HTML is produced separately).
     *
     * @return array<int, array{level: int, text: string, id: string}>
     */
    public function extractToc(string $bodyHtml): array
    {
        return $this->process($bodyHtml)['toc'];
    }

    private function innerHtml(DOMDocument $document, \DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return trim($html);
    }
}
