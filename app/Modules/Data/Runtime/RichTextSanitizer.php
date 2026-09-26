<?php

declare(strict_types=1);

namespace App\Modules\Data\Runtime;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/** Allowlist tag untuk field rich_text (docs/05 §4 kontrol 2: cegah stored XSS). */
final class RichTextSanitizer
{
    private ?HtmlSanitizer $sanitizer = null;

    public function sanitize(string $html): string
    {
        $this->sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowElement('p')
                ->allowElement('br')
                ->allowElement('strong')
                ->allowElement('em')
                ->allowElement('u')
                ->allowElement('ul')
                ->allowElement('ol')
                ->allowElement('li')
                ->allowElement('h3')
                ->allowElement('h4')
                ->allowElement('blockquote')
                ->allowElement('a', ['href'])
                ->allowLinkSchemes(['https', 'mailto'])
                ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
                ->withMaxInputLength(100_000),
        );

        return $this->sanitizer->sanitize($html);
    }
}
