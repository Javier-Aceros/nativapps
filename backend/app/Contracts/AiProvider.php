<?php

namespace App\Contracts;

use App\Domain\ValueObjects\Summary;

interface AiProvider
{
    /**
     * System prompt enforces ≤ 100 characters output.
     * Shared across all provider implementations.
     */
    public const SYSTEM_PROMPT = 'Resume el texto del usuario en una sola oración natural y fluida. La respuesta debe tener un máximo estricto de 80 caracteres (contando espacios) para garantizar brevedad absoluta.. '
        .'REGLAS CRÍTICAS: '
        .'No uses punto final (ahorra espacio). '
        .'No uses introducciones ni comentarios. '
        .'Prioriza verbos y sustantivos, elimina adjetivos innecesarios. '
        .'Si la frase se ve larga, recórtala de nuevo antes de enviarla. '
        .'Ejemplo de respuesta válida (78 chars): '
        .'"Empresa lanza app para gestión de inventario con sincronización en tiempo real"';

    /**
     * Summarize the given content into ≤ 100 characters.
     *
     * @throws \RuntimeException when the AI API call fails or returns an empty response.
     */
    public function summarize(string $content): Summary;
}
