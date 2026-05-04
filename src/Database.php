<?php
/**
 * Database Connection Class
 * 
 * Handles database connections and basic operations
 * 
 * @author Hariom Vimal
 * @version 1.2.0
 * @since 2024-04-15
 */

class Database {
    private $pdo;
    private $host;
    private $dbname;
    private $user;
    private $pass;
    private $charset = 'utf8mb4';
    
    public function __construct() {
        $this->host = $_ENV['DB_HOST'] ?? 'localhost';
        $this->dbname = $_ENV['DB_NAME'] ?? 'dongare_ecommerce';
        $this->user = $_ENV['DB_USER'] ?? 'postgres';
        $this->pass = $_ENV['DB_PASS'] ?? '';
        
        $this->connect();
    }
    
    private function connect() {
        try {
            // PostgreSQL connection string
            $dsn = "pgsql:host={$this->host};dbname={$this->dbname};user={$this->user};password={$this->pass};port=5432";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->pdo = new PDO($dsn);
            
        } catch (PDOException $e) {
            // Log error instead of showing to users
            error_log("Database connection failed: " . $e->getMessage());
            
            // Show generic error to users
            throw new Exception("Database connection failed. Please try again later.");
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage());
            throw new Exception("Database query failed.");
        }
    }
    
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
    
    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }
    
    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        try {
            $this->query($sql, array_values($data));
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Insert failed: " . $e->getMessage());
            throw new Exception("Failed to insert data.");
        }
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $setClause = [];
        $params = [];
        
        foreach ($data as $column => $value) {
            $setClause[] = "{$column} = ?";
            $params[] = $value;
        }
        
        $setClause = implode(', ', $setClause);
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        
        $params = array_merge($params, $whereParams);
        
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Update failed: " . $e->getMessage());
            throw new Exception("Failed to update data.");
        }
    }
    
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Delete failed: " . $e->getMessage());
            throw new Exception("Failed to delete data.");
        }
    }
    
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollback() {
        return $this->pdo->rollBack();
    }
    
    public function getLastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    // For debugging - remove in production
    public function debugQuery($sql, $params = []) {
        echo "SQL: " . $sql . "\n";
        echo "Params: " . json_encode($params) . "\n";
    }
}
?>
