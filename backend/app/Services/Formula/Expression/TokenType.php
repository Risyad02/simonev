<?php

namespace App\Services\Formula\Expression;

enum TokenType
{
    case NUMBER;
    case VARIABLE;
    case PLUS;
    case MINUS;
    case STAR;
    case SLASH;
    case LPAREN;
    case RPAREN;
    case EOF;
}