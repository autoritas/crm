<?php

namespace App\Integrations\TenderSources;

/**
 * DTO inmutable con los bytes de un pliego ya descargado, listo para adjuntar.
 * Lo producen los providers y lo consume el adjuntador de Kanboard.
 */
final class DownloadedDocument
{
    public function __construct(
        public readonly string $sourceUrl,
        public readonly string $filename,
        public readonly string $content,        // bytes brutos (no base64)
        public readonly ?string $mime = null,
    ) {}

    public function sha256(): string
    {
        return hash('sha256', $this->content);
    }

    public function bytes(): int
    {
        return strlen($this->content);
    }

    public function base64(): string
    {
        return base64_encode($this->content);
    }
}
