<?php

require_once '../config/Database.php';

class User {
    private $conn;
    private $table = 'users';
    private $database;

    public function __construct() {
        $this->database = new Database();
    }

    public function __destruct() {
        if ($this->conn) {
            $this->database->close();
        }
    }

    public function create($data) {
        try {
            $this->conn = $this->database->open($data['email']);
            
            if ($this->conn instanceof PDO) {
                // PostgreSQL
                $query = "INSERT INTO " . $this->table . " 
                        (email, password, nombre, apellido_paterno, apellido_materno) 
                        VALUES (:email, :password, :nombre, :apellido_paterno, :apellido_materno)";
                
                $stmt = $this->conn->prepare($query);
                $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
                
                $stmt->bindParam(':email', $data['email']);
                $stmt->bindParam(':password', $hashedPassword);
                $stmt->bindParam(':nombre', $data['nombre']);
                $stmt->bindParam(':apellido_paterno', $data['apellido_paterno']);
                $stmt->bindParam(':apellido_materno', $data['apellido_materno']);
                
                return $stmt->execute();
            } else {
                // MySQL
                $query = "INSERT INTO " . $this->table . " 
                        (email, password, nombre, apellido_paterno, apellido_materno) 
                        VALUES (?, ?, ?, ?, ?)";
                
                $stmt = $this->conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Error en la preparación de la consulta: " . $this->conn->error);
                }

                $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
                
                $stmt->bind_param("sssss", 
                    $data['email'],
                    $hashedPassword,
                    $data['nombre'],
                    $data['apellido_paterno'],
                    $data['apellido_materno']
                );

                $result = $stmt->execute();
                $stmt->close();
                return $result;
            }
        } catch (Exception $e) {
            error_log("Error en User::create: " . $e->getMessage());
            return false;
        }
    }

    public function login($email, $password) {
        try {
            $this->conn = $this->database->open($email);
            
            if ($this->conn instanceof PDO) {
                // PostgreSQL
                $query = "SELECT * FROM " . $this->table . " WHERE email = :email";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                // MySQL
                $query = "SELECT * FROM " . $this->table . " WHERE email = ?";
                $stmt = $this->conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Error en la preparación de la consulta: " . $this->conn->error);
                }

                

                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $stmt->close();
            }

            if ($user && password_verify($password, $user['password'])) {
                unset($user['password']); // No devolver la contraseña
                return $user;
            }

            return false;
        } catch (Exception $e) {
            error_log("Error en User::login: " . $e->getMessage());
            return false;
        }
    }

    public function findById($id, $email) {
        try {
            $this->conn = $this->database->open($email);
            
            if ($this->conn instanceof PDO) {
                // PostgreSQL
                $query = "SELECT id, email, nombre, apellido_paterno, apellido_materno 
                         FROM " . $this->table . " WHERE id = :id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                return $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                // MySQL
                $query = "SELECT id, email, nombre, apellido_paterno, apellido_materno 
                         FROM " . $this->table . " WHERE id = ?";
                $stmt = $this->conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Error en la preparación de la consulta: " . $this->conn->error);
                }

                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $stmt->close();

                return $user;
            }
        } catch (Exception $e) {
            error_log("Error en User::findById: " . $e->getMessage());
            return null;
        }
    }

    public function update($id, $data, $email) {
        try {
            $this->conn = $this->database->open($email);
            
            if ($this->conn instanceof PDO) {
                // PostgreSQL
                $query = "UPDATE " . $this->table . " 
                         SET nombre = :nombre, apellido_paterno = :apellido_paterno, 
                             apellido_materno = :apellido_materno 
                         WHERE id = :id";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':nombre', $data['nombre']);
                $stmt->bindParam(':apellido_paterno', $data['apellido_paterno']);
                $stmt->bindParam(':apellido_materno', $data['apellido_materno']);
                $stmt->bindParam(':id', $id);
                
                return $stmt->execute();
            } else {
                // MySQL
                $query = "UPDATE " . $this->table . " 
                         SET nombre = ?, apellido_paterno = ?, apellido_materno = ? 
                         WHERE id = ?";
                
                $stmt = $this->conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Error en la preparación de la consulta: " . $this->conn->error);
                }

                $stmt->bind_param("sssi", 
                    $data['nombre'],
                    $data['apellido_paterno'],
                    $data['apellido_materno'],
                    $id
                );

                $result = $stmt->execute();
                $stmt->close();
                return $result;
            }
        } catch (Exception $e) {
            error_log("Error en User::update: " . $e->getMessage());
            return false;
        }
    }
}