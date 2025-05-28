<?php
session_start();
require_once '../models/File.php';

// Solo establecer el header JSON si no es una descarga de archivo
if (!isset($_POST['acc']) || $_POST['acc'] !== 'download') {
    header('Content-Type: application/json');
}

$file = new File();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $opc = $_POST['opc'] ?? '';
    $acc = $_POST['acc'] ?? '';

    error_log("Operación: " . $opc . ", Acción: " . $acc);
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));

    if ($opc === 'file') {
        switch ($acc) {
            case 'upload':
                if (!isset($_SESSION['user_id'])) {
                    echo json_encode(['success' => false, 'message' => 'Debe iniciar sesión para subir archivos']);
                    exit;
                }
                if (isset($_FILES['file'])) {
                    $result = $file->upload($_FILES['file'], $_SESSION['user_email']);
                    echo json_encode($result);
                } else {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'No se proporcionó ningún archivo para subir',
                        'debug' => [
                            'step' => 'error', 
                            'error' => 'No file provided',
                            'post_data' => $_POST,
                            'files_data' => $_FILES
                        ]
                    ]);
                }
                break;

            case 'download':
                if (isset($_POST['id'])) {
                    $fileData = $file->download($_POST['id'], $_SESSION['user_email'] ?? null);
                    if ($fileData) {
                        if (file_exists($fileData['path'])) {
                            // Limpiar cualquier salida anterior
                            if (ob_get_level()) {
                                ob_end_clean();
                            }
                            
                            // Establecer los headers correctos
                            header('Content-Type: ' . $fileData['type']);
                            header('Content-Disposition: attachment; filename="' . $fileData['name'] . '"');
                            header('Content-Length: ' . $fileData['size']);
                            header('Cache-Control: no-cache, must-revalidate');
                            header('Pragma: no-cache');
                            header('Expires: 0');
                            
                            // Enviar el archivo
                            if (readfile($fileData['path']) === false) {
                                header('Content-Type: application/json');
                                echo json_encode(['success' => false, 'message' => 'Error al leer el archivo']);
                                exit;
                            }
                            
                            // Limpiar el archivo temporal
                            @unlink($fileData['path']);
                            exit;
                        } else {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => false, 'message' => 'El archivo no existe en el servidor']);
                            exit;
                        }
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Error al descargar el archivo']);
                        exit;
                    }
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'ID de archivo no proporcionado']);
                    exit;
                }
                break;

            case 'delete':
                if (!isset($_SESSION['user_id'])) {
                    echo json_encode(['success' => false, 'message' => 'Debe iniciar sesión para eliminar archivos']);
                    exit;
                }
                if (isset($_POST['id'])) {
                    $result = $file->delete($_POST['id'], $_SESSION['user_email']);
                    if ($result === true) {
                        echo json_encode(['success' => true, 'message' => 'Archivo eliminado correctamente']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Error al eliminar el archivo: ' . $result]);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'ID de archivo no proporcionado']);
                }
                break;

            case 'getAll':
                $file = new File();
                $category = $_POST['category'] ?? null;
                
                $debug_info = [
                    'action' => 'getAll',
                    'category' => $category,
                    'session_id' => $_SESSION['user_id'] ?? null
                ];
                
                $files = $file->getAll(null, $category);
                
                $debug_info['files_count'] = count($files);
                $debug_info['files'] = $files;
                
                echo json_encode([
                    'success' => true, 
                    'files' => $files,
                    'debug' => $debug_info
                ]);
                break;

            case 'search':
                if (!isset($_POST['query'])) {
                    echo json_encode(['success' => false, 'message' => 'No se proporcionó término de búsqueda']);
                    break;
                }
                
                $file = new File();
                $query = $_POST['query'];
                $category = $_POST['category'] ?? null;
                
                $debug_info = [
                    'action' => 'search',
                    'query' => $query,
                    'category' => $category,
                    'session_id' => $_SESSION['user_id'] ?? null
                ];
                
                $files = $file->search($query, $category, null);
                
                $debug_info['files_count'] = count($files);
                $debug_info['files'] = $files;
                
                echo json_encode([
                    'success' => true, 
                    'files' => $files,
                    'debug' => $debug_info
                ]);
                break;

            default:
                echo json_encode([
                    'success' => false, 
                    'message' => 'Acción no válida',
                    'debug' => [
                        'action' => $acc,
                        'operation' => $opc,
                        'post_data' => $_POST,
                        'files_data' => $_FILES
                    ]
                ]);
                break;
        }
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Operación no válida',
            'debug' => [
                'operation' => $opc,
                'post_data' => $_POST,
                'files_data' => $_FILES
            ]
        ]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Método no permitido',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'post_data' => $_POST,
            'files_data' => $_FILES
        ]
    ]);
}