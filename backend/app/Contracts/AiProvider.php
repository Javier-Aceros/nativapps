<?php

namespace App\Contracts;

use App\Domain\ValueObjects\Summary;

interface AiProvider
{
    /**
     * System prompt enforces ≤ 100 characters output.
     * Shared across all provider implementations.
     */
    public const SYSTEM_PROMPT = 'Eres un asistente de síntesis. '
        .'Escribe UNA SOLA FRASE completa e informativa que resuma el texto del usuario. '
        .'Máximo 100 caracteres contando los espacios. '
        .'Si te pasas de los 100 caracteres seras penalizado. '
        .'No uses introducciones como \'Aquí tienes el resumen:\' ni puntos finales innecesarios. '
        .'y tampoco hagas preguntas u ofrecimientos al finalizar.'
        .'La frase debe capturar la idea principal del texto.';

    /**
     * Summarize the given content into ≤ 100 characters.
     *
     * @throws \RuntimeException when the AI API call fails or returns an empty response.
     */
    public function summarize(string $content): Summary;
}
