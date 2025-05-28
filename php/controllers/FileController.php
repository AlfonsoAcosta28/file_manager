<?php
session_start();
require_once '../models/File.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$file = new File();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $opc = $_POST['opc'] ?? '';
    $acc = $_POST['acc'] ?? '';

    if ($opc === 'file') {
        switch ($acc) {
            case 'upload':
                if (isset($_FILES['file'])) {
                    $result = $file->upload($_FILES['file'], $_SESSION['user_email']);
                    echo json_encode($result);
                } else {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'No se proporcionó ningún archivo para subir',
                        'debug' => ['step' => 'error', 'error' => 'No file provided']
                    ]);
                }
                break;

            case 'download':
                if (isset($_POST['id'])) {
                    $fileData = $file->download($_POST['id'], $_SESSION['user_email']);
                    if ($fileData) {
                        header('Content-Type: ' . $fileData['type']);
                        header('Content-Disposition: attachment; filename="' . $fileData['name'] . '"');
                        readfile($fileData['path']);
                        unlink($fileData['path']); // Eliminar archivo temporal
                        exit;
                    } else {
                        echo "0";
                    }
                } else {
                    echo "0";
                }
                break;

            case 'delete':
                if (isset($_POST['id'])) {
                    $result = $file->delete($_POST['id'], $_SESSION['user_email']);
                    echo $result ? "1" : "0";
                } else {
                    echo "0";
                }
                break;

            case 'getAll':
                $files = $file->getAll($_SESSION['user_email']);
                echo json_encode(['success' => true, 'files' => $files]);
                break;

            case 'search':
                if (isset($_POST['query'])) {
                    $query = $_POST['query'];
                    $category = $_POST['category'] ?? null;
                    $files = $file->search($query, $category, $_SESSION['user_email']);
                    echo json_encode(['success' => true, 'files' => $files]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Query no proporcionada']);
                }
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Acción no válida']);
                break;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Operación no válida']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}