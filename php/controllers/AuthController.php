<?php
session_start();
require_once '../models/User.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $opc = $_POST['opc'] ?? '';
    $acc = $_POST['acc'] ?? '';

    if ($opc === 'auth') {
        $user = new User();

        switch ($acc) {
            case 'login':
                if (isset($_POST['email']) && isset($_POST['password'])) {
                    $userData = $user->login($_POST['email'], $_POST['password']);
                    if ($userData) {
                        $_SESSION['user_id'] = $userData['id'];
                        $_SESSION['user_email'] = $userData['email'];
                        echo json_encode(['success' => true, 'user' => $userData]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Credenciales inválidas']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
                }
                break;

            case 'register':
                if (isset($_POST['email']) && isset($_POST['password']) && 
                    isset($_POST['nombre']) && isset($_POST['apellido_paterno']) && 
                    isset($_POST['apellido_materno'])) {
                    
                    $data = [
                        'email' => $_POST['email'],
                        'password' => $_POST['password'],
                        'nombre' => $_POST['nombre'],
                        'apellido_paterno' => $_POST['apellido_paterno'],
                        'apellido_materno' => $_POST['apellido_materno']
                    ];

                    try {
                        $result = $user->create($data);
                        if ($result) {
                            echo json_encode(['success' => true, 'message' => 'Usuario registrado exitosamente']);
                        } else {
                            echo json_encode([
                                'success' => false, 
                                'message' => 'Error al registrar usuario',
                                'error' => 'No se pudo crear el usuario en la base de datos'
                            ]);
                        }
                    } catch (Exception $e) {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Error al registrar usuario',
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                } else {
                    $missingFields = [];
                    if (!isset($_POST['email'])) $missingFields[] = 'email';
                    if (!isset($_POST['password'])) $missingFields[] = 'password';
                    if (!isset($_POST['nombre'])) $missingFields[] = 'nombre';
                    if (!isset($_POST['apellido_paterno'])) $missingFields[] = 'apellido_paterno';
                    if (!isset($_POST['apellido_materno'])) $missingFields[] = 'apellido_materno';

                    echo json_encode([
                        'success' => false,
                        'message' => 'Datos incompletos',
                        'missing_fields' => $missingFields
                    ]);
                }
                break;

            case 'logout':
                session_destroy();
                echo "1";
                break;

            case 'checkSession':
                if (isset($_SESSION['user_id'])) {
                    $userData = $user->findById($_SESSION['user_id'], $_SESSION['user_email']);
                    if ($userData) {
                        echo json_encode(['loggedIn' => true, 'user' => $userData]);
                    } else {
                        echo json_encode(['loggedIn' => false]);
                    }
                } else {
                    echo json_encode(['loggedIn' => false]);
                }
                break;

            case 'updateProfile':
                if (isset($_SESSION['user_id']) && isset($_POST['nombre']) && 
                    isset($_POST['apellido_paterno']) && isset($_POST['apellido_materno'])) {
                    
                    $data = [
                        'nombre' => $_POST['nombre'],
                        'apellido_paterno' => $_POST['apellido_paterno'],
                        'apellido_materno' => $_POST['apellido_materno']
                    ];

                    $result = $user->update($_SESSION['user_id'], $data, $_SESSION['user_email']);
                    echo $result ? "1" : "0";
                } else {
                    echo "0";
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