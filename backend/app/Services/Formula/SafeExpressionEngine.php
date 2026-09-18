<?php

namespace App\Services\Formula;

use App\Services\Formula\Expression\ExpressionEvaluator;
use App\Services\Formula\Expression\ExpressionParser;
use App\Services\Formula\Expression\Tokenizer;

/**
 * Satu entry point evaluasi custom expression formula. Dipakai identik
 * oleh production calculation (FormulaEngine, Checkpoint 4) dan future
 * preview endpoint (ditunda dari Phase 10) — hasil preview dan production
 * dijamin konsisten karena keduanya lewat jalur evaluasi yang sama persis.
 *
 * Mengembalikan float mentah — BUKAN FormulaResult. Pembungkusan menjadi
 * achievement_pct/deviation adalah tanggung jawab FormulaEngine/Calculator
 * di Checkpoint 4, bukan evaluator ini (separation of concerns).
 */
class SafeExpressionEngine
{
    public function __construct(
        private readonly Tokenizer $tokenizer,
        private readonly ExpressionParser $parser,
        private readonly ExpressionEvaluator $evaluator,
    ) {
    }

    public function evaluate(string $expression, float $target, float $realisasi): float
    {
        $tokens = $this->tokenizer->tokenize($expression);
        $ast = $this->parser->parse($tokens);

        return $this->evaluator->evaluate($ast, $target, $realisasi);
    }
}