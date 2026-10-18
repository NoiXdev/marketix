<?php

namespace App\Support\Privacy;

/**
 * One heading plus its paragraphs of a generated privacy notice. Plain text
 * throughout — escaping happens when NoticeComposer renders HTML.
 */
final class NoticeSection
{
    /**
     * @param  list<string>  $paragraphs
     */
    public function __construct(
        public readonly string $heading,
        public readonly array $paragraphs,
    ) {}
}
