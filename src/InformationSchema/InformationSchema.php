<?php

namespace MysqlEngine\InformationSchema;

use MysqlEngine\Php7\FakePdo as FakePdo7;
use MysqlEngine\Php8\FakePdo as FakePdo8;
use MysqlEngine\Processor\ProcessorException;
use MysqlEngine\Processor\SQLFakeUniqueKeyViolation;

class InformationSchema
{
    /**
     * @param FakePdo7|FakePdo8 $pdo
     * @return void
     * @throws ProcessorException
     * @throws SQLFakeUniqueKeyViolation
     */
    public function createSchema($pdo): void
    {
        $queries = file_get_contents(__DIR__ . '/tables.sql');
        $pdo->prepare($queries)->execute();
    }
}