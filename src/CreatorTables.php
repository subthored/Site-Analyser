<?php

namespace Hexlet\Code;

class CreatorTables
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function createTables()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS urls (
                    id bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
                    name varchar(255) NOT NULL UNIQUE,
                    created_at timestamp
        );';

        $this->pdo->exec($sql);

        return $this;
    }
}