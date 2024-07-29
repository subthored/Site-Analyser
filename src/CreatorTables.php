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

    public function createTablesChecks()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS url_checks (
                    id bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
                    url_id bigint REFERENCES urls (id),
                    status_code int,
                    h1 varchar(255),
                    title varchar (255),
                    description varchar(255),
                    created_at timestamp
        );';

        $this->pdo->exec($sql);

        return $this;
    }
}