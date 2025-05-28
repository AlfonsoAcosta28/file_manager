<?php

require_once '../config/Database.php';

class File {
    private $conn;
    private $table = 'files';
    private $database;
    private $ftp_conn;
    private $ftp_upload_dir = 'files/';

    public function __construct() {
        $this->database = new Database();
    }

    public function __destruct() {
        if ($this->conn) {
            $this->database->close();
        }
        if ($this->ftp_conn) {
            $this->database->closeFTP();
        }
    }

    private function getFTPPath($filename) {
        // Generar un nombre único para el archivo
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $unique_name = uniqid() . '_' . time() . '.' . $extension;
        return $this->ftp_upload_dir . $unique_name;
    }

    public function upload($file, $user_email) {
        $debug_info = [];
        try {
            $debug_info['step'] = 'inicio';
            $debug_info['user_email'] = $user_email;
            $debug_info['file_info'] = $file;

            $this->conn = $this->database->open($user_email);
            $debug_info['db_connection'] = $this->conn ? 'success' : 'failed';

            $this->ftp_conn = $this->database->openFTP();
            $debug_info['ftp_connection'] = $this->ftp_conn ? 'success' : 'failed';

            if (!$this->ftp_conn) {
                throw new Exception("No se pudo conectar al servidor FTP");
            }

            if (!$this->conn) {
                throw new Exception("No se pudo conectar a la base de datos");
            }

            // Verificar permisos y directorio base
            $debug_info['step'] = 'verificando_directorio';
            
            // Obtener el directorio actual
            $current_dir = ftp_pwd($this->ftp_conn);
            $debug_info['current_directory'] = $current_dir;
            
            // Listar el directorio actual para verificar permisos
            $current_list = @ftp_nlist($this->ftp_conn, '.');
            $debug_info['can_list_current'] = $current_list !== false;
            $debug_info['current_directory_contents'] = $current_list;

            // Subir archivo al FTP
            $debug_info['step'] = 'subiendo_archivo';
            $ftp_path = $this->getFTPPath($file['name']);
            $debug_info['target_path'] = $ftp_path;
            $debug_info['temp_file'] = $file['tmp_name'];
            
            if (!file_exists($file['tmp_name'])) {
                throw new Exception("El archivo temporal no existe: " . $file['tmp_name']);
            }

            if (!ftp_put($this->ftp_conn, $ftp_path, $file['tmp_name'], FTP_BINARY)) {
                $error = error_get_last();
                throw new Exception("Error al subir el archivo al FTP: " . ($error ? $error['message'] : 'Error desconocido'));
            }
            $debug_info['ftp_upload'] = 'success';

            // Verificar que el archivo existe en el FTP
            if (!@ftp_nlist($this->ftp_conn, $ftp_path)) {
                throw new Exception("El archivo no se encuentra en el FTP después de la subida");
            }
            $debug_info['file_verification'] = 'success';

            // Guardar información en la base de datos
            $debug_info['step'] = 'insertando_en_db';
            if ($this->conn instanceof PDO) {
                $debug_info['db_type'] = 'postgresql';
                // PostgreSQL
                $query = "INSERT INTO " . $this->table . " 
                        (nombre, tipo, tamaño, ruta, user_id) 
                        VALUES (:nombre, :tipo, :tamaño, :ruta, :user_id)";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':nombre', $file['name']);
                $stmt->bindParam(':tipo', $file['type']);
                $stmt->bindParam(':tamaño', $file['size']);
                $stmt->bindParam(':ruta', $ftp_path);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error al insertar en la base de datos PostgreSQL: " . implode(", ", $stmt->errorInfo()));
                }
                $debug_info['db_insert'] = 'success';
                return ['success' => true, 'debug' => $debug_info];
            } else {
                $debug_info['db_type'] = 'mysql';
                // MySQL
                $query = "INSERT INTO " . $this->table . " 
                        (nombre, tipo, tamaño, ruta, user_id) 
                        VALUES (?, ?, ?, ?, ?)";
                
                $stmt = $this->conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Error en la preparación de la consulta MySQL: " . $this->conn->error);
                }

                $stmt->bind_param("ssisi", 
                    $file['name'],
                    $file['type'],
                    $file['size'],
                    $ftp_path,
                    $_SESSION['user_id']
                );

                if (!$stmt->execute()) {
                    throw new Exception("Error al insertar en la base de datos MySQL: " . $stmt->error);
                }
                
                $debug_info['db_insert'] = 'success';
                $stmt->close();
                return ['success' => true, 'debug' => $debug_info];
            }
        } catch (Exception $e) {
            $debug_info['error'] = $e->getMessage();
            // Si hubo error y el archivo se subió al FTP, intentar eliminarlo
            if (isset($ftp_path) && $this->ftp_conn) {
                @ftp_delete($this->ftp_conn, $ftp_path);
                $debug_info['cleanup'] = 'file_deleted';
            }
            return ['success' => false, 'message' => $e->getMessage(), 'debug' => $debug_info];
        }
    }

    public function download($file_id, $user_email) {
        try {
            $this->conn = $this->database->open($user_email);
            $this->ftp_conn = $this->database->openFTP();

            if (!$this->ftp_conn) {
                throw new Exception("No se pudo conectar al servidor FTP");
            }

            // Obtener información del archivo
            if ($this->conn instanceof PDO) {
                // PostgreSQL
                $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':id', $file_id);
                $stmt->execute();
                $file = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                // MySQL
                $query = "SELECT * FROM " . $this->table . " WHERE id = ?";
                $stmt = $this->conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Error en la preparación de la consulta: " . $this->conn->error);
                }

                $stmt->bind_param("i", $file_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $file = $result->fetch_assoc();
                $stmt->close();
            }

            if (!$file) {
                throw new Exception("Archivo no encontrado");
            }

            // Crear archivo temporal
            $temp_file = tempnam(sys_get_temp_dir(), 'download_');
            
            // Descargar archivo del FTP
            if (!ftp_get($this->ftp_conn, $temp_file, $file['ruta'], FTP_BINARY)) {
                throw new Exception("Error al descargar el archivo del FTP");
            }

            return [
                'path' => $temp_file,
                'name' => $file['nombre'],
                'type' => $file['tipo']
            ];
        } catch (Exception $e) {
            error_log("Error en File::download: " . $e->getMessage());
            return false;
        }
    }

    public function delete($file_id, $user_email) {
        try {
            $this->conn = $this->database->open($user_email);
            $this->ftp_conn = $this->database->openFTP();

            if (!$this->ftp_conn) {
                throw new Exception("No se pudo conectar al servidor FTP");
            }

            // Obtener información del archivo
            if ($this->conn instanceof PDO) {
                // PostgreSQL
                $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':id', $file_id);
                $stmt->execute();
                $file = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                // MySQL
                $query = "SELECT * FROM " . $this->table . " WHERE id = ?";
                $stmt = $this->conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Error en la preparación de la consulta: " . $this->conn->error);
                }

                $stmt->bind_param("i", $file_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $file = $result->fetch_assoc();
                $stmt->close();
            }

            if (!$file) {
                throw new Exception("Archivo no encontrado");
            }

            // Eliminar archivo del FTP
            if (!ftp_delete($this->ftp_conn, $file['ruta'])) {
                throw new Exception("Error al eliminar el archivo del FTP");
            }

            // Eliminar registro de la base de datos
            if ($this->conn instanceof PDO) {
                // PostgreSQL
                $query = "DELETE FROM " . $this->table . " WHERE id = :id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':id', $file_id);
                return $stmt->execute();
            } else {
                // MySQL
                $query = "DELETE FROM " . $this->table . " WHERE id = ?";
                $stmt = $this->conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Error en la preparación de la consulta: " . $this->conn->error);
                }

                $stmt->bind_param("i", $file_id);
                $result = $stmt->execute();
                $stmt->close();
                return $result;
            }
        } catch (Exception $e) {
            error_log("Error en File::delete: " . $e->getMessage());
            return false;
        }
    }

    public function getAll($user_email) {
        try {
            $this->conn = $this->database->open($user_email);
            
            if ($this->conn instanceof PDO) {
                // PostgreSQL
                $query = "SELECT f.*, u.email as user_email, 
                         CASE WHEN f.user_id = :user_id THEN true ELSE false END as is_owner 
                         FROM " . $this->table . " f 
                         JOIN users u ON f.user_id = u.id 
                         ORDER BY f.id DESC";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // MySQL
                $query = "SELECT f.*, u.email as user_email, 
                         CASE WHEN f.user_id = ? THEN true ELSE false END as is_owner 
                         FROM " . $this->table . " f 
                         JOIN users u ON f.user_id = u.id 
                         ORDER BY f.id DESC";
                $stmt = $this->conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Error en la preparación de la consulta: " . $this->conn->error);
                }

                $stmt->bind_param("i", $_SESSION['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $files = [];
                while ($row = $result->fetch_assoc()) {
                    $files[] = $row;
                }
                $stmt->close();
                return $files;
            }
        } catch (Exception $e) {
            error_log("Error en File::getAll: " . $e->getMessage());
            return [];
        }
    }

    public function search($query, $category, $user_email) {
        try {
            $this->conn = $this->database->open($user_email);
            
            $searchQuery = "%" . $query . "%";
            $categoryCondition = $category ? "AND f.tipo LIKE :category" : "";
            
            if ($this->conn instanceof PDO) {
                // PostgreSQL
                $query = "SELECT f.*, u.email as user_email, 
                         CASE WHEN f.user_id = :user_id THEN true ELSE false END as is_owner 
                         FROM " . $this->table . " f 
                         JOIN users u ON f.user_id = u.id 
                         WHERE f.nombre LIKE :search " . $categoryCondition . "
                         ORDER BY f.id DESC";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':search', $searchQuery);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                if ($category) {
                    $stmt->bindParam(':category', $category . '%');
                }
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // MySQL
                $query = "SELECT f.*, u.email as user_email, 
                         CASE WHEN f.user_id = ? THEN true ELSE false END as is_owner 
                         FROM " . $this->table . " f 
                         JOIN users u ON f.user_id = u.id 
                         WHERE f.nombre LIKE ? " . $categoryCondition . "
                         ORDER BY f.id DESC";
                
                $stmt = $this->conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Error en la preparación de la consulta: " . $this->conn->error);
                }

                if ($category) {
                    $categoryPattern = $category . '%';
                    $stmt->bind_param("iss", $_SESSION['user_id'], $searchQuery, $categoryPattern);
                } else {
                    $stmt->bind_param("is", $_SESSION['user_id'], $searchQuery);
                }

                $stmt->execute();
                $result = $stmt->get_result();
                $files = [];
                while ($row = $result->fetch_assoc()) {
                    $files[] = $row;
                }
                $stmt->close();
                return $files;
            }
        } catch (Exception $e) {
            error_log("Error en File::search: " . $e->getMessage());
            return [];
        }
    }
}