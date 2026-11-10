<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as Sanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Cleans rich text from the notes editor before it is stored and shown in the app.
 * Only the formatting the editor offers is kept; scripts, styles and events are removed.
 */
class HtmlSanitizer
{
    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $config = (new HtmlSanitizerConfig)
            ->allowElement('p')->allowElement('br')->allowElement('strong')->allowElement('b')
            ->allowElement('em')->allowElement('i')->allowElement('u')->allowElement('s')
            ->allowElement('h2')->allowElement('h3')->allowElement('h4')
            ->allowElement('ul')->allowElement('ol')->allowElement('li')->allowElement('blockquote')
            ->allowElement('mark')->allowElement('code')->allowElement('pre')->allowElement('hr')
            ->allowElement('table')->allowElement('thead')->allowElement('tbody')->allowElement('tr')
            ->allowElement('th', ['colspan', 'rowspan'])->allowElement('td', ['colspan', 'rowspan'])
            ->allowElement('a', ['href', 'title'])->allowElement('img', ['src', 'alt', 'title'])
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowMediaSchemes(['https', 'http'])
            ->allowRelativeMedias()
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withMaxInputLength(500_000);

        return (new Sanitizer($config))->sanitize($html);
    }
}
