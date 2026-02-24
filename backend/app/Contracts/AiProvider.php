<?php

namespace App\Contracts;

use App\Domain\ValueObjects\Summary;

interface AiProvider
{
    /**
     * System prompt enforces ≤ 100 characters output.
     * Shared across all provider implementations.
     */
    public const SYSTEM_PROMPT = 'Eres un sintetizador de información ultra-conciso. '
        .'Resume el texto del usuario en menos de 100 caracteres, incluyendo espacios. '
        .'Si el resumen excede el límite, serás penalizado. '
        .'No uses introducciones como \'Aquí tienes el resumen:\' ni puntos finales innecesarios. '
        .'y tampoco hagas preguntas u ofrecimientos al finalizar.';

    /**
     * Summarize the given content into ≤ 100 characters.
     *
     * @throws \RuntimeException when the AI API call fails or returns an empty response.
     */
    public function summarize(string $content): Summary;
}
