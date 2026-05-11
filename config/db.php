<?php

class db
{
    private string $host = '127.0.0.1';
    private string $dbName = 'photohub_db'; // Change this to your real database/schema name.
    private string $username = 'root';
    private string $password = '';
    private string $charset = 'utf8mb4';

    private ?PDO $connection = null;

    public function connect(): PDO
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        $dsn = "mysql:host={$this->host};dbname={$this->dbName};charset={$this->charset}";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->connection = new PDO(
                $dsn,
                $this->username,
                $this->password,
                $options
            );

            return $this->connection;
        } catch (PDOException $exception) {
            die('Database connection failed: ' . $exception->getMessage());
        }
    }
}
