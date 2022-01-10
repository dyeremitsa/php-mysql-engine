<?php
namespace MysqlEngine\Query\Expression;

use MysqlEngine\Parser\Token;
use MysqlEngine\TokenType;
use MysqlEngine\Processor\ProcessorException;
use MysqlEngine\Query\MysqlColumnType;

final class CastExpression extends Expression
{
    /**
     * @var Token
     */
    public $token;

    /**
     * @var Expression
     */
    public $expr;

    /**
     * @var MysqlColumnType
     */
    public $castType;

    /**
     * @param Token $tokens
     */
    public function __construct(Token $token, Expression $expr, MysqlColumnType $cast_type)
    {
        $this->token = $token;
        $this->type = $token->type;
        $this->precedence = 0;
        $this->operator = (string) $this->type;
        $this->expr = $expr;
        $this->castType = $cast_type;
        $this->start = $token->start;
    }

    /**
     * @return bool
     */
    public function isWellFormed()
    {
        return true;
    }
}
