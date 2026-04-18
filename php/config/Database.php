<?php
/**
 * Database Connection Class
 * Handles all database connections for ReliefLink
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'relieflink';
    private $db_user = 'root';
    private $db_pass = '';
    private $conn;

    public function connect() {
        $this->conn = new mysqli($this->host, $this->db_user, $this->db_pass, $this->db_name);

        if ($this->conn->connect_error) {
            die('Connection Error: ' . $this->conn->connect_error);
        }

        // Set charset to utf8
        $this->conn->set_charset('utf8mb4');

        return $this->conn;
    }

    public function getConnection() {
        if ($this->conn === null) {
            $this->connect();
        }
        return $this->conn;
    }

    public function closeConnection() {
        if ($this->conn) {
            $this->conn->close();
        }
    }

    public function getErrorMessage() {
        return $this->conn->error;
    }
}
?>
