<?php

class Database {
    private $mysql_host = "192.168.0.13";
    private $mysql_db_name = "file_manager_mysql";
    private $mysql_username = "root";
    private $mysql_password = "root";
    private $mysql_port = 3307;

    private $postgres_host = "192.168.0.13";
    private $postgres_db_name = "file_manager_postgres";
    private $postgres_username = "postgres";
    private $postgres_password = "postgres";
    private $postgres_port = 3308;

    private $ftp_host = "192.168.0.13";
    private $ftp_username = "ftpuser";
    private $ftp_password = "1234";
    private $ftp_port = 21;

    private $conn = null;
    private $ftp_conn = null;

    // Primera mitad del alfabeto (a-m) va a MySQL
    private $mysql_letters = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm'];
    // Segunda mitad del alfabeto (n-z) va a PostgreSQL
    private $postgres_letters = ['n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'];

    public function open($email = null) {
        try {
            if ($email) {
                $firstLetter = strtolower(substr($email, 0, 1));
                if (in_array($firstLetter, $this->mysql_letters)) {
                    // MySQL para primera mitad del alfabeto (a-m)
                    $this->conn = new mysqli(
                        $this->mysql_host,
                        $this->mysql_username,
                        $this->mysql_password,
                        $this->mysql_db_name,
                        $this->mysql_port
                    );
                } else if (in_array($firstLetter, $this->postgres_letters)) {
                    // PostgreSQL para segunda mitad del alfabeto (n-z)
                    $this->conn = new PDO(
                        "pgsql:host={$this->postgres_host};port={$this->postgres_port};dbname={$this->postgres_db_name}",
                        $this->postgres_username,
                        $this->postgres_password
                    );
                } else {
                    throw new Exception("El correo electrónico debe comenzar con una letra del alfabeto");
                }
            } else {
                // Conexión por defecto a MySQL
                $this->conn = new mysqli(
                    $this->mysql_host,
                    $this->mysql_username,
                    $this->mysql_password,
                    $this->mysql_db_name,
                    $this->mysql_port
                );
            }
            
            if ($this->conn instanceof mysqli && $this->conn->connect_error) {
                throw new Exception("Error de conexión MySQL: " . $this->conn->connect_error);
            }
            
            return $this->conn;
        } catch (Exception $e) {
            error_log("Error en Database::open: " . $e->getMessage());
            return null;
        }
    }

    public function openFTP() {
        try {
            $this->ftp_conn = ftp_connect($this->ftp_host, $this->ftp_port);
            if (!$this->ftp_conn) {
                throw new Exception("No se pudo conectar al servidor FTP");
            }

            if (!ftp_login($this->ftp_conn, $this->ftp_username, $this->ftp_password)) {
                throw new Exception("Error al iniciar sesión en FTP");
            }

            return $this->ftp_conn;
        } catch (Exception $e) {
            error_log("Error en Database::openFTP: " . $e->getMessage());
            return null;
        }
    }

    public function close() {
        if ($this->conn) {
            if ($this->conn instanceof mysqli) {
                $this->conn->close();
            }
            $this->conn = null;
        }
    }

    public function closeFTP() {
        if ($this->ftp_conn) {
            ftp_close($this->ftp_conn);
            $this->ftp_conn = null;
        }
    }

    public function __destruct() {
        $this->close();
        $this->closeFTP();
    }
}