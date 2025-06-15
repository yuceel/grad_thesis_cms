<?php
class Database {
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;
    private $conn;
    private static $instance = null;
    private $inTransaction = false;  
    
    private function __construct() {
        $this->conn = mysqli_init();
        
        mysqli_options($this->conn, MYSQLI_INIT_COMMAND, "SET NAMES utf8mb4");
        mysqli_options($this->conn, MYSQLI_INIT_COMMAND, "SET CHARACTER SET utf8mb4");
        mysqli_options($this->conn, MYSQLI_INIT_COMMAND, "SET character_set_connection=utf8mb4");
        
        if (!mysqli_real_connect($this->conn, $this->host, $this->user, $this->pass, $this->dbname)) {
            die("Veritabanı bağlantı hatası: " . mysqli_connect_error());
        }
        
        mysqli_set_charset($this->conn, "utf8mb4");
        
        mysqli_autocommit($this->conn, false);
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }

    public function beginTransaction() {
        if (!$this->inTransaction) {
            mysqli_begin_transaction($this->conn);
            $this->inTransaction = true;
            error_log("Transaction started");
        }
    }
    
    public function commit() {
        if ($this->inTransaction) {
            mysqli_commit($this->conn);
            $this->inTransaction = false;
            error_log("Transaction committed");
        }
    }
    
    public function rollBack() {
        if ($this->inTransaction) {
            mysqli_rollback($this->conn);
            $this->inTransaction = false;
            error_log("Transaction rolled back");
        }
    }
    
    public function query($sql, $params = []) {
        try {
            if (!empty($params)) {
                $stmt = mysqli_prepare($this->conn, $sql);
                if (!$stmt) {
                    throw new Exception("Query preparation failed: " . mysqli_error($this->conn));
                }

                $types = str_repeat('s', count($params));
                mysqli_stmt_bind_param($stmt, $types, ...$params);
                mysqli_stmt_execute($stmt);
                return $stmt;
            } else {
                $result = mysqli_query($this->conn, $sql);
                if ($result === false) {
                    throw new Exception("Query failed: " . mysqli_error($this->conn));
                }
                return $result;
            }
        } catch (Exception $e) {
            error_log("Query error: " . $e->getMessage());
            if ($this->inTransaction) {
                $this->rollBack();
            }
            throw $e;
        }
    }
    
    public function select($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            
            if ($stmt instanceof mysqli_stmt) {
                $result = mysqli_stmt_get_result($stmt);
                $data = [];
                while ($row = mysqli_fetch_assoc($result)) {
                    $data[] = $row;
                }
                mysqli_stmt_close($stmt);
                return $data;
            } else {
                $data = [];
                while ($row = mysqli_fetch_assoc($stmt)) {
                    $data[] = $row;
                }
                return $data;
            }
        } catch (Exception $e) {
            error_log("Select error: " . $e->getMessage());
            if ($this->inTransaction) {
                $this->rollBack();
            }
            throw $e;
        }
    }
    
    public function insert($table, $data) {
        try {
            $columns = implode(', ', array_keys($data));
            $values = implode(', ', array_fill(0, count($data), '?'));
            $sql = "INSERT INTO $table ($columns) VALUES ($values)";
            
            $stmt = $this->query($sql, array_values($data));
            $insertId = mysqli_stmt_insert_id($stmt);
            
            mysqli_stmt_close($stmt);
            
            return $insertId;
        } catch (Exception $e) {
            error_log("Insert error: " . $e->getMessage());
            if ($this->inTransaction) {
                $this->rollBack();
            }
            throw $e;
        }
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        try {
            $set = implode(' = ?, ', array_keys($data)) . ' = ?';
            $sql = "UPDATE $table SET $set WHERE $where";
            
            $params = array_merge(array_values($data), $whereParams);
            $stmt = $this->query($sql, $params);
            
            if ($stmt instanceof mysqli_stmt) {
                $affected = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);
            } else {
                $affected = mysqli_affected_rows($this->conn);
            }
            
            return $affected;
        } catch (Exception $e) {
            error_log("Update error: " . $e->getMessage());
            if ($this->inTransaction) {
                $this->rollBack();
            }
            throw $e;
        }
    }
    
    public function delete($table, $where, $params = []) {
        try {
            $sql = "DELETE FROM $table WHERE $where";
            $stmt = $this->query($sql, $params);
            
            if ($stmt instanceof mysqli_stmt) {
                $affected = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);
            } else {
                $affected = mysqli_affected_rows($this->conn);
            }
            
            return $affected;
        } catch (Exception $e) {
            error_log("Delete error: " . $e->getMessage());
            if ($this->inTransaction) {
                $this->rollBack();
            }
            throw $e;
        }
    }
    
    public function escape($value) {
        return mysqli_real_escape_string($this->conn, $value);
    }
    
    public function __destruct() {
        if ($this->inTransaction) {
            $this->rollBack();
        }
        if ($this->conn) {
            mysqli_close($this->conn);
        }
    }
}

$db = Database::getInstance();
?> 