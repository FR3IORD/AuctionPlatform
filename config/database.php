<?php
// Only declare Database class if it doesn't already exist
if (!class_exists('Database')) {
    class Database {
        private $connection;
        private $in_transaction = false;

        public function __construct() {
            try {
                $this->connection = new PDO(
                    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                    DB_USER,
                    DB_PASS,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch (PDOException $e) {
                // Log error instead of dying
                error_log('Database Connection Error: ' . $e->getMessage());
                
                // Check if database name is the issue
                if (strpos($e->getMessage(), '1049') !== false) {
                    die('Database not found: ' . DB_NAME . '<br>
                    Please create the database first:<br>
                    <code>CREATE DATABASE ' . DB_NAME . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;</code>');
                }
                
                die('Database connection failed: ' . $e->getMessage());
            }
        }

        public function getConnection() {
            return $this->connection;
        }

        public function beginTransaction() {
            if (!$this->in_transaction) {
                $this->connection->beginTransaction();
                $this->in_transaction = true;
            }
        }

        public function commit() {
            if ($this->in_transaction) {
                $this->connection->commit();
                $this->in_transaction = false;
            }
        }

        public function rollback() {
            if ($this->in_transaction) {
                $this->connection->rollBack();
                $this->in_transaction = false;
            }
        }

        public function inTransaction() {
            return $this->in_transaction;
        }
    }
}
?>