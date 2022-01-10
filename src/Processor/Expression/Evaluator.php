<?php
namespace MysqlEngine\Processor\Expression;

use MysqlEngine\Expression;
use MysqlEngine\Processor\ProcessorException;
use MysqlEngine\Processor\QueryResult;
use MysqlEngine\Processor\Scope;
use MysqlEngine\Query\Expression\FunctionExpression;
use MysqlEngine\Schema\Column;
use MysqlEngine\TokenType;

class Evaluator
{
    /**
     * @param  array<string, mixed> $row
     * @param array<string, Column> $columns
     * @return mixed
     */
    public static function evaluate(
        \MysqlEngine\FakePdoInterface $conn,
        Scope $scope,
        \MysqlEngine\Query\Expression\Expression $expr,
        array $row,
        QueryResult $result
    ) {
        if ($expr->name && \array_key_exists($expr->name, $row)) {
            return $row[$expr->name];
        }

        switch (get_class($expr)) {
            case \MysqlEngine\Query\Expression\BetweenOperatorExpression::class:
                return BetweenOperatorEvaluator::evaluate($conn, $scope, $expr, $row, $result);

            case \MysqlEngine\Query\Expression\BinaryOperatorExpression::class:
                return BinaryOperatorEvaluator::evaluate($conn, $scope, $expr, $row, $result);

            case \MysqlEngine\Query\Expression\CaseOperatorExpression::class:
                return CaseOperatorEvaluator::evaluate($conn, $scope, $expr, $row, $result);

            case \MysqlEngine\Query\Expression\ColumnExpression::class:
                return ColumnEvaluator::evaluate($conn, $scope, $expr, $row, $result);

            case \MysqlEngine\Query\Expression\ConstantExpression::class:
                return $expr->value;

            case \MysqlEngine\Query\Expression\ExistsOperatorExpression::class:
                return ExistsOperatorEvaluator::evaluate($conn, $scope, $expr, $row, $result);

            case FunctionExpression::class:
                return FunctionEvaluator::evaluate($conn, $scope, $expr, $row, $result);

            case \MysqlEngine\Query\Expression\InOperatorExpression::class:
                return InOperatorEvaluator::evaluate($conn, $scope, $expr, $row, $result);

            case \MysqlEngine\Query\Expression\StubExpression::class:
                throw new ProcessorException("Attempted to evaluate placeholder expression!");

            case \MysqlEngine\Query\Expression\PositionExpression::class:
                return PositionEvaluator::evaluate($expr, $row);

            case \MysqlEngine\Query\Expression\RowExpression::class:
                return RowEvaluator::evaluate($conn, $scope, $expr, $row, $result);

            case \MysqlEngine\Query\Expression\SubqueryExpression::class:
                $subquery_result = \MysqlEngine\Processor\SelectProcessor::process(
                    $conn,
                    $scope,
                    $expr->query,
                    $row,
                    $result->columns
                );

                if (count($subquery_result->rows) > 1) {
                    throw new ProcessorException('Subquery returns more than one row');
                }

                if ($subquery_result->rows) {
                    $first_row = reset($subquery_result->rows);
                    return reset($first_row);
                }

                return null;

            case \MysqlEngine\Query\Expression\UnaryExpression::class:
                return UnaryEvaluator::evaluate($conn, $scope, $expr, $row, $result);

            case \MysqlEngine\Query\Expression\CastExpression::class:
                return CastEvaluator::evaluate($conn, $scope, $expr, $row, $result);

            case \MysqlEngine\Query\Expression\VariableExpression::class:
                return VariableEvaluator::evaluate($scope, $expr);

            case \MysqlEngine\Query\Expression\NamedPlaceholderExpression::class:
                return NamedPlaceholderEvaluator::evaluate($scope, $expr);

            case \MysqlEngine\Query\Expression\QuestionMarkPlaceholderExpression::class:
                return QuestionMarkPlaceholderEvaluator::evaluate($scope, $expr);

            default:
                throw new ProcessorException('Unsupported expression ' . get_class($expr));
        }
    }

    /**
     * @param  array<string, Column> $columns
     * @return Column
     */
    public static function getColumnSchema(
        \MysqlEngine\Query\Expression\Expression $expr,
        Scope $scope,
        array $columns,
        bool $use_cache = true
    ) : Column {
        if (!$scope->variables && $expr->column && $use_cache) {
            return $expr->column;
        }

        if ($expr->name && \array_key_exists($expr->name, $columns)) {
            return $expr->column = $columns[$expr->name];
        }

        switch (get_class($expr)) {
            case \MysqlEngine\Query\Expression\BetweenOperatorExpression::class:
                return $expr->column = new Column\TinyInt(true, 1);

            case \MysqlEngine\Query\Expression\BinaryOperatorExpression::class:
                return $expr->column = BinaryOperatorEvaluator::getColumnSchema($expr, $scope, $columns);

            case \MysqlEngine\Query\Expression\CaseOperatorExpression::class:
                $types = [];

                foreach ($expr->whenExpressions as $when) {
                    $types[] = Evaluator::getColumnSchema($when['then'], $scope, $columns);
                }

                $types[] = Evaluator::getColumnSchema($expr->else, $scope, $columns);

                return $expr->column = self::combineColumnTypes($types);

            case \MysqlEngine\Query\Expression\ColumnExpression::class:
                return $expr->column = ColumnEvaluator::getColumnSchema($expr, $columns);

            case \MysqlEngine\Query\Expression\ConstantExpression::class:
                switch ($expr->getType()) {
                    case TokenType::NUMERIC_CONSTANT:
                        if (\strpos((string) $expr->value, '.') !== false) {
                            return $expr->column = new Column\FloatColumn(10, 2);
                        }

                        return $expr->column = new Column\IntColumn(false, 10);

                    case TokenType::STRING_CONSTANT:
                        return $expr->column = new Column\Varchar(255);

                    case TokenType::NULL_CONSTANT:
                        return $expr->column = new Column\NullColumn();
                }
                break;

            case \MysqlEngine\Query\Expression\ExistsOperatorExpression::class:
                return $expr->column = new Column\TinyInt(true, 1);

            case FunctionExpression::class:
                return $expr->column = FunctionEvaluator::getColumnSchema($expr, $scope, $columns);

            case \MysqlEngine\Query\Expression\InOperatorExpression::class:
                return $expr->column = new Column\TinyInt(true, 1);

            case \MysqlEngine\Query\Expression\StubExpression::class:
                throw new ProcessorException("Attempted to evaluate placeholder expression!");

            case \MysqlEngine\Query\Expression\PositionExpression::class:
                break;

            case \MysqlEngine\Query\Expression\RowExpression::class:
                break;

            case \MysqlEngine\Query\Expression\SubqueryExpression::class:
                if (\count($expr->query->selectExpressions) === 1) {
                    if ($expr->query->selectExpressions[0]->column) {
                        return $expr->query->selectExpressions[0]->column;
                    }

                    if ($expr->query->selectExpressions[0] instanceof FunctionExpression
                        && \strtoupper($expr->query->selectExpressions[0]->functionName) === 'COUNT'
                    ) {
                        return $expr->column = new Column\IntColumn(true, 10);
                    }
                }

                return new Column\NullColumn();

            case \MysqlEngine\Query\Expression\UnaryExpression::class:
                break;

            case \MysqlEngine\Query\Expression\CastExpression::class:
                if ($expr->castType->type === 'UNSIGNED') {
                    return $expr->column = new Column\IntColumn(true, 10);
                }

                if ($expr->castType->type === 'SIGNED') {
                    return $expr->column = new Column\IntColumn(false, 10);
                }

                break;

            case \MysqlEngine\Query\Expression\VariableExpression::class:
                if (array_key_exists($expr->variableName, $scope->variables)) {
                    $value = $scope->variables[$expr->variableName];

                    if (\is_int($value)) {
                        return new Column\IntColumn(false, 10);
                    }

                    if (\is_float($value)) {
                        return new Column\FloatColumn(10, 2);
                    }

                    if ($value === null) {
                        return new Column\NullColumn();
                    }

                    return new Column\Varchar(255);
                }

                // When MySQL can't figure out a variable column's type
                // it defaults to string
                return new Column\Varchar(10);

            case \MysqlEngine\Query\Expression\NamedPlaceholderExpression::class:
                if (\array_key_exists($expr->parameterName, $scope->parameters)) {
                    return self::getColumnTypeFromValue($expr, $scope->parameters[$expr->parameterName]);
                }

                // When MySQL can't figure out a variable column's type
                // it defaults to string
                return new Column\Varchar(10);

            case \MysqlEngine\Query\Expression\QuestionMarkPlaceholderExpression::class:
                if (\array_key_exists($expr->offset, $scope->parameters)) {
                    return self::getColumnTypeFromValue($expr, $scope->parameters[$expr->offset]);
                }

                // When MySQL can't figure out a variable column's type
                // it defaults to string
                return new Column\Varchar(10);
        }

        return $expr->column = new Column\Varchar(10);
    }

    /**
     * @param mixed $value
     */
    private static function getColumnTypeFromValue(
        \MysqlEngine\Query\Expression\Expression $expr,
        $value
    ) : Column {
        if (\is_int($value)) {
            return $expr->column = new Column\IntColumn(false, 10);
        }

        if (\is_float($value)) {
            return $expr->column = new Column\FloatColumn(10, 2);
        }

        if (\is_bool($value)) {
            return $expr->column = new Column\TinyInt(true, 1);
        }

        if ($value === null) {
            return $expr->column = new Column\NullColumn();
        }

        return new Column\Varchar(255);
    }

    /**
     * @param  list<Column>  $types
     */
    public static function combineColumnTypes(array $types) : Column
    {
        if (count($types) === 2) {
            $type_0_null = $types[0] instanceof Column\NullColumn;
            $type_1_null = $types[1] instanceof Column\NullColumn;

            if ($type_0_null && $type_1_null) {
                return $types[0];
            }

            if ($type_0_null) {
                $type = clone $types[1];
                $type->setNullable(true);
                return $type;
            }

            if ($type_1_null) {
                $type = clone $types[0];
                $type->setNullable(true);
                return $type;
            }
        }

        $is_nullable = false;

        $has_floating_point = false;
        $has_integer = false;
        $has_string = false;

        $non_null_types = [];

        foreach ($types as $type) {
            if ($type->isNullable()) {
                $is_nullable = true;
            }

            if (!$type instanceof Column\NullColumn) {
                $non_null_types[] = $type;
            }
        }

        if (!$non_null_types) {
            return new Column\NullColumn;
        }

        if (count($non_null_types) === 1) {
            $type = clone $non_null_types[0];
            $type->setNullable(true);
            return $type;
        }

        foreach ($non_null_types as $type) {
            if ($type instanceof Column\StringColumn
                || $type instanceof Column\ChronologicalColumn
            ) {
                $has_string = true;
            }

            if ($type instanceof Column\DecimalPointColumn) {
                $has_floating_point = true;
            }

            if ($type instanceof Column\IntegerColumn) {
                $has_integer = true;
            }
        }

        if ($has_string) {
            $column = new Column\Varchar(255);
        } elseif ($has_floating_point) {
            $column = new Column\FloatColumn(10, 2);
        } elseif ($has_integer) {
            $column = new Column\IntColumn(false, 10);
        } else {
            $column = new Column\Varchar(255);
        }

        if ($is_nullable) {
            $column->setNullable(true);
        }

        return $column;
    }
}
