<?php

namespace App\Integrations\TenderSources\Contracts;

use App\Integrations\TenderSources\DownloadedDocument;
use App\Models\Offer;

/**
 * Contrato que implementa cada plataforma de contratacion que sepamos tratar
 * (PLACSP, PSCP Catalunya, Vortal, Ariba, etc.).
 *
 * Un provider debe saber:
 *  - Reconocer si una URL le pertenece (por host).
 *  - Descargar la lista de pliegos de una oferta y devolverlos ya como bytes
 *    listos para adjuntar a la tarea Kanboard.
 */
interface TenderSourceProvider
{
    /** Identificador corto unico del provider. Ej: 'PLACSP', 'PSCP'. */
    public function id(): string;

    /** Etiqueta humana para UI/logs. */
    public function label(): string;

    /** Devuelve true si el provider puede procesar la URL dada. */
    public function supports(string $tenderUrl): bool;

    /**
     * Descarga todos los pliegos asociados a la oferta.
     *
     * @return DownloadedDocument[]  Puede ser [] si no hay pliegos publicos.
     *
     * @throws \RuntimeException Cuando falla el fetch/parseo; quien llama
     *                           decide si abortar o continuar con otras ofertas.
     */
    public function fetchDocuments(Offer $offer): array;
}
