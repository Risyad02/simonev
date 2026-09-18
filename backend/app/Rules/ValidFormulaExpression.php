<?php

namespace App\Rules;

use App\Services\Formula\Exceptions\InvalidExpressionSyntaxException;
use App\Services\Formula\Expression\ExpressionParser;
use App\Services\Formula\Expression\Tokenizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validasi SYNTAX expression saat Store/Update (Owner Decision §8) —
 * hanya tokenize + parse, TIDAK evaluate (nilai target/realisasi belum
 * tersedia saat validasi). Kegagalan division-by-zero/unknown-variable
 * tetap jadi tanggung jawab runtime evaluator (defense-in-depth,
 * Checkpoint 3), bukan rule ini.
 */
class ValidFormulaExpression implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            // Presence/wajib-tidaknya sudah ditangani rule lain
            // (Rule::requiredIf) di FormRequest — rule ini hanya
            // memvalidasi SYNTAX ketika expression memang diisi.
            return;
        }

        try {
            $tokenizer = new Tokenizer();
            $parser = new ExpressionParser();
            $parser->parse($tokenizer->tokenize($value));
        } catch (InvalidExpressionSyntaxException $e) {
            $fail('Expression formula tidak valid: '.$e->getMessage());
        }
    }
}