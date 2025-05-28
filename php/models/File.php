<?php

require_once '../config/Database.php';

class File
{
    private $conn;
    private $table = 'files';
    private $database;
    private $ftp_conn;
    private $ftp_upload_dir = 'files/';

    public function __construct()
    {
        $this->database = new Database();
    }

    public function __destruct()
    {
        if ($this->conn) {
            $this->database->close();
        }
        if ($this->ftp_conn) {
            $this->database->closeFTP();
        }
    }

    private function getFTPPath($filename)
    {
        // Generar un nombre único para el archivo
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $unique_name = uniqid() . '_' . time() . '.' . $extension;
        return $unique_name; // Retornar solo el nombre del archivo, sin el directorio
    }

    public function upload($file, $user_email)
    {
        $debug_info = [];
        try {
            $debug_info['step'] = 'inicio';
            $debug_info['user_email'] = $user_email;
            $debug_info['file_info'] = $file;

            // Validar que el archivo existe y tiene información
            if (!isset($file['type']) || empty($file['type'])) {
                // Intentar detectar el tipo MIME
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                
                if ($mime_type) {
                    $file['type'] = $mime_type;
                    $debug_info['detected_mime_type'] = $mime_type;
                } else {
                    throw new Exception("No se pudo detectar el tipo de archivo");
                }
            }

            $debug_info['file_type'] = $file['type'];

            // Validar el tipo de archivo
            $allowed_types = [
                'image/jpeg',
                'image/jpg',
                'image/png',
                'image/gif',
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'video/mp4',
                'video/quicktime',
                'video/x-msvideo',
                'video/x-matroska',
                'video/webm',
                'video/3gpp',
                'video/x-ms-wmv',
                'video/mpeg',
                'video/x-m4v',
                'video/x-flv'
            ];

            if (!in_array($file['type'], $allowed_types)) {
                $debug_info['error'] = "Tipo de archivo no permitido: " . $file['type'];
                throw new Exception($debug_info['error']);
            }

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
            $file_name = $this->getFTPPath($file['name']);
            $ftp_path = $this->ftp_upload_dir . $file_name;
            $debug_info['target_path'] = $ftp_path;
            $debug_info['temp_file'] = $file['tmp_name'];

            if (!file_exists($file['tmp_name'])) {
                throw new Exception("El archivo temporal no existe: " . $file['tmp_name']);
            }

            // Habilitar modo pasivo para FTP
            ftp_pasv($this->ftp_conn, true);

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

            // Obtener la categoría del archivo
            $categoria = isset($_POST['categoria']) ? strtolower($_POST['categoria']) : 'documentos';

            // Validar la categoría
            $categorias_validas = ['musica', 'videos', 'documentos', 'imagenes'];
            if (!in_array($categoria, $categorias_validas)) {
                $categoria = 'documentos';
            }

            $debug_info['categoria'] = $categoria;

            // Guardar información en la base de datos
            $debug_info['step'] = 'insertando_en_db';
            if ($this->conn instanceof PDO) {
                $debug_info['db_type'] = 'postgresql';
                // PostgreSQL
                $query = "INSERT INTO " . $this->table . " 
                        (nombre, tipo, tamano, ruta, user_id, categoria) 
                        VALUES (:nombre, :tipo, :tamano, :ruta, :user_id, :categoria)";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':nombre', $file['name']);
                $stmt->bindParam(':tipo', $file['type']);
                $stmt->bindParam(':tamano', $file['size']);
                $stmt->bindParam(':ruta', $ftp_path);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':categoria', $categoria);

                if (!$stmt->execute()) {
                    throw new Exception("Error al insertar en la base de datos PostgreSQL: " . implode(", ", $stmt->errorInfo()));
                }
                $debug_info['db_insert'] = 'success';
                return ['success' => true, 'debug' => $debug_info];
            } else {
                $debug_info['db_type'] = 'mysql';
                // MySQL
                $query = "INSERT INTO " . $this->table . " 
                        (nombre, tipo, tamano, ruta, user_id, categoria) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                
                $stmt = $this->conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Error en la preparación de la consulta MySQL: " . $this->conn->error);
                }

                $stmt->bind_param(
                    "ssissi",
                    $file['name'],
                    $file['type'],
                    $file['size'],
                    $ftp_path,
                    $_SESSION['user_id'],
                    $categoria
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

    public function download($file_id, $user_email = null)
    {
        try {
            // Intentar primero con MySQL
            $mysql_conn = new mysqli(
                "192.168.0.100",
                "root",
                "root",
                "file_manager_mysql",
                3307
            );

            $file = null;
            if (!$mysql_conn->connect_error) {
                $query = "SELECT * FROM " . $this->table . " WHERE id = ?";
                $stmt = $mysql_conn->prepare($query);
                if ($stmt) {
                    $stmt->bind_param("i", $file_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $file = $result->fetch_assoc();
                    $stmt->close();
                }
                $mysql_conn->close();
            }

            // Si no se encontró en MySQL, intentar con PostgreSQL
            if (!$file) {
                try {
                    $pgsql_conn = new PDO(
                        "pgsql:host=192.168.0.100;port=3308;dbname=file_manager_postgres",
                        "postgres",
                        "postgres"
                    );

                    $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
                    $stmt = $pgsql_conn->prepare($query);
                    $stmt->bindParam(':id', $file_id);
                    $stmt->execute();
                    $file = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    error_log("Error PostgreSQL: " . $e->getMessage());
                }
            }

            if (!$file) {
                throw new Exception("Archivo no encontrado");
            }

            // Conectar al FTP
            $this->ftp_conn = $this->database->openFTP();
            if (!$this->ftp_conn) {
                throw new Exception("No se pudo conectar al servidor FTP");
            }

            // Extraer solo el nombre del archivo de la ruta completa
            $ftp_path = $file['ruta'];
            $file_name = basename($ftp_path);

            error_log("Ruta completa: " . $ftp_path);
            error_log("Nombre del archivo: " . $file_name);

            // Verificar que el archivo existe en el FTP
            if (!@ftp_nlist($this->ftp_conn, $this->ftp_upload_dir . $file_name)) {
                throw new Exception("El archivo no existe en el servidor FTP");
            }

            // Crear archivo temporal con la extensión correcta
            $extension = pathinfo($file['nombre'], PATHINFO_EXTENSION);
            $temp_file = tempnam(sys_get_temp_dir(), 'download_') . '.' . $extension;
            
            // Habilitar modo pasivo para FTP
            ftp_pasv($this->ftp_conn, true);
            
            // Descargar archivo del FTP
            if (!@ftp_get($this->ftp_conn, $temp_file, $this->ftp_upload_dir . $file_name, FTP_BINARY)) {
                $error = error_get_last();
                throw new Exception("Error al descargar el archivo del FTP: " . ($error ? $error['message'] : 'Error desconocido'));
            }

            // Verificar que el archivo existe y tiene contenido
            if (!file_exists($temp_file) || filesize($temp_file) === 0) {
                throw new Exception("El archivo descargado está vacío o no existe");
            }

            return [
                'path' => $temp_file,
                'name' => $file['nombre'],
                'type' => $file['tipo'],
                'size' => filesize($temp_file)
            ];
        } catch (Exception $e) {
            error_log("Error en File::download: " . $e->getMessage());
            return false;
        }
    }

    public function delete($file_id, $user_email)
    {
        try {
            $this->conn = $this->database->open($user_email);
            $this->ftp_conn = $this->database->openFTP();

            if (!$this->ftp_conn) {
                throw new Exception("No se pudo conectar al servidor FTP");
            }

            // Obtener información del archivo
            if ($this->conn instanceof PDO) {
                // PostgreSQL
                $query = "SELECT * FROM " . $this->table . " WHERE id = :id AND user_id = :user_id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':id', $file_id);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
                $file = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                // MySQL
                $query = "SELECT * FROM " . $this->table . " WHERE id = ? AND user_id = ?";
                $stmt = $this->conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Error en la preparación de la consulta: " . $this->conn->error);
                }

                $stmt->bind_param("ii", $file_id, $_SESSION['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $file = $result->fetch_assoc();
                $stmt->close();
            }

            if (!$file) {
                throw new Exception("Archivo no encontrado o no tienes permisos para eliminarlo");
            }

            // Eliminar archivo del FTP
            if (!@ftp_delete($this->ftp_conn, $file['ruta'])) {
                throw new Exception("Error al eliminar el archivo del FTP");
            }

            // Eliminar registro de la base de datos
            if ($this->conn instanceof PDO) {
                // PostgreSQL
                $query = "DELETE FROM " . $this->table . " WHERE id = :id AND user_id = :user_id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':id', $file_id);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                return $stmt->execute();
            } else {
                // MySQL
                $query = "DELETE FROM " . $this->table . " WHERE id = ? AND user_id = ?";
                $stmt = $this->conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Error en la preparación de la consulta: " . $this->conn->error);
                }

                $stmt->bind_param("ii", $file_id, $_SESSION['user_id']);
                $result = $stmt->execute();
                $stmt->close();
                return $result;
            }
        } catch (Exception $e) {
            error_log("Error en File::delete: " . $e->getMessage());
            return false;
        }
    }

    public function getAll($user_email = null, $category = null)
    {
        try {
            $files = [];

            // Buscar en MySQL
            $mysql_conn = new mysqli(
                "192.168.0.100",
                "root",
                "root",
                "file_manager_mysql",
                3307
            );

            if (!$mysql_conn->connect_error) {
                $mysql_query = "SELECT f.*, u.email as user_email, 
                              CASE WHEN f.user_id = ? THEN true ELSE false END as is_owner 
                              FROM " . $this->table . " f 
                              JOIN users u ON f.user_id = u.id";

                $params = [$_SESSION['user_id'] ?? 0];
                $types = "i";

                if ($category) {
                    $mysql_query .= " WHERE f.categoria = ?";
                    $params[] = $category;
                    $types .= "s";
                }

                $mysql_query .= " ORDER BY f.id DESC";

                $stmt = $mysql_conn->prepare($mysql_query);
                if ($stmt) {
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $files[] = $row;
                    }
                    $stmt->close();
                }
                $mysql_conn->close();
            }

            // Buscar en PostgreSQL
            try {
                $pgsql_conn = new PDO(
                    "pgsql:host=192.168.0.100;port=3308;dbname=file_manager_postgres",
                    "postgres",
                    "postgres"
                );

                $pgsql_query = "SELECT f.*, u.email as user_email, 
                         CASE WHEN f.user_id = :user_id THEN true ELSE false END as is_owner 
                         FROM " . $this->table . " f 
                               JOIN users u ON f.user_id = u.id";

                $params = [':user_id' => $_SESSION['user_id'] ?? 0];

                if ($category) {
                    $pgsql_query .= " WHERE f.categoria = :category";
                    $params[':category'] = $category;
                }

                $pgsql_query .= " ORDER BY f.id DESC";

                $stmt = $pgsql_conn->prepare($pgsql_query);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();

                $pgsql_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($pgsql_results as $row) {
                    $files[] = $row;
                }
            } catch (PDOException $e) {
                // Ignorar errores de PostgreSQL si MySQL funciona
            }

            // Ordenar todos los resultados por ID de forma descendente
            usort($files, function ($a, $b) {
                return $b['id'] - $a['id'];
            });

                return $files;
        } catch (Exception $e) {
            return [];
        }
    }

    public function search($query, $category, $user_email = null)
    {
        try {
            $searchQuery = "%" . $query . "%";
            $files = [];

            // Buscar en MySQL
            $mysql_conn = new mysqli(
                "192.168.0.100",
                "root",
                "root",
                "file_manager_mysql",
                3307
            );
            
            if (!$mysql_conn->connect_error) {
                $mysql_query = "SELECT f.*, u.email as user_email, 
                              CASE WHEN f.user_id = ? THEN true ELSE false END as is_owner 
                         FROM " . $this->table . " f 
                         JOIN users u ON f.user_id = u.id 
                              WHERE f.nombre LIKE ?";
                
                $params = [$_SESSION['user_id'] ?? 0, $searchQuery];
                $types = "is";

                if ($category) {
                    $mysql_query .= " AND f.categoria = ?";
                    $params[] = $category;
                    $types .= "s";
                }

                $mysql_query .= " ORDER BY f.id DESC";

                $stmt = $mysql_conn->prepare($mysql_query);
                if ($stmt) {
                    $stmt->bind_param($types, ...$params);
                $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $files[] = $row;
                    }
                    $stmt->close();
                }
                $mysql_conn->close();
            }

            // Buscar en PostgreSQL
            try {
                $pgsql_conn = new PDO(
                    "pgsql:host=192.168.0.100;port=3308;dbname=file_manager_postgres",
                    "postgres",
                    "postgres"
                );

                $pgsql_query = "SELECT f.*, u.email as user_email, 
                               CASE WHEN f.user_id = :user_id THEN true ELSE false END as is_owner 
                         FROM " . $this->table . " f 
                         JOIN users u ON f.user_id = u.id 
                               WHERE f.nombre LIKE :search";
                
                $params = [
                    ':search' => $searchQuery,
                    ':user_id' => $_SESSION['user_id'] ?? 0
                ];

                if ($category) {
                    $pgsql_query .= " AND f.categoria = :category";
                    $params[':category'] = $category;
                }

                $pgsql_query .= " ORDER BY f.id DESC";

                $stmt = $pgsql_conn->prepare($pgsql_query);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();

                $pgsql_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($pgsql_results as $row) {
                    $files[] = $row;
                }
            } catch (PDOException $e) {
                // Ignorar errores de PostgreSQL si MySQL funciona
            }

            // Ordenar todos los resultados por ID de forma descendente
            usort($files, function ($a, $b) {
                return $b['id'] - $a['id'];
            });

                return $files;
        } catch (Exception $e) {
            return [];
        }
    }
}