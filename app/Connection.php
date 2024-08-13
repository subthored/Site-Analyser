<?php

namespace PostgreSQL;

class Connection
{
    private static ?Connection $conn = null;

    public function connect()
    {
        if (getenv('DATABASE_URL')) {
            $databaseUrl = parse_url(getenv('DATABASE_URL'));
            var_dump($databaseUrl);
        }

        if (isset($databaseUrl['host'])) {
            $params['host'] = $databaseUrl['host'];
            $params['port'] = $databaseUrl['port'] ?? null;
            $params['database'] = $databaseUrl['scheme'] ?? null;
            $params['user'] = $databaseUrl['user'] ?? null;
            $params['password'] = $databaseUrl['pass'] ?? null;
        } else {
            $params = parse_ini_file('database.ini');
        }
        if ($params === false) {
            throw new \Exception("Error reading database configuration file");
        }
        var_dump($params);

        $conStr = sprintf(
            "pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s",
            $params['host'],
            $params['port'],
            $params['database'],
            $params['user'],
            $params['password']
        );
        var_dump($conStr);

        $pdo = new \PDO($conStr);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }
    public static function get()
    {
        if (null === static::$conn) {
            static::$conn = new self();
        }
        return static::$conn;
    }

    protected function __construct()
    {
    }
}
