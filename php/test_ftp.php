<?php
// diagnostic.php - Script para diagnosticar problemas de conectividad

function testFTPConnection($host, $port, $username, $password) {
    echo "=== DIAGNÓSTICO DE CONEXIÓN FTP ===\n";
    echo "Host: $host\n";
    echo "Puerto: $port\n";
    echo "Usuario: $username\n\n";

    // 1. Verificar conectividad básica
    echo "1. Verificando conectividad básica...\n";
    $socket = @fsockopen($host, $port, $errno, $errstr, 10);
    if (!$socket) {
        echo "❌ Error: No se puede conectar a $host:$port ($errno: $errstr)\n";
        return false;
    } else {
        echo "✅ Conectividad básica: OK\n";
        fclose($socket);
    }

    // 2. Intentar conexión FTP
    echo "\n2. Intentando conexión FTP...\n";
    $conn = @ftp_connect($host, $port, 30);
    if (!$conn) {
        echo "❌ Error: No se pudo establecer conexión FTP\n";
        return false;
    } else {
        echo "✅ Conexión FTP establecida\n";
    }

    // 3. Intentar login
    echo "\n3. Intentando login...\n";
    $login = @ftp_login($conn, $username, $password);
    if (!$login) {
        echo "❌ Error: Fallo en autenticación\n";
        ftp_close($conn);
        return false;
    } else {
        echo "✅ Login exitoso\n";
    }

    // 4. Configurar modo pasivo
    echo "\n4. Configurando modo pasivo...\n";
    $pasv = @ftp_pasv($conn, true);
    if (!$pasv) {
        echo "⚠️  Advertencia: No se pudo configurar modo pasivo\n";
    } else {
        echo "✅ Modo pasivo configurado\n";
    }

    // 5. Listar directorio actual
    echo "\n5. Listando directorio actual...\n";
    $pwd = ftp_pwd($conn);
    echo "Directorio actual: $pwd\n";
    
    $list = @ftp_nlist($conn, '.');
    if ($list) {
        echo "Contenido del directorio:\n";
        foreach ($list as $item) {
            echo "  - $item\n";
        }
    } else {
        echo "❌ No se pudo listar el directorio\n";
    }

    // 6. Intentar crear archivo de prueba
    echo "\n6. Intentando crear archivo de prueba...\n";
    $testFile = 'test_' . time() . '.txt';
    $tempFile = tempnam(sys_get_temp_dir(), 'ftp_test');
    file_put_contents($tempFile, 'Test file content');
    
    $upload = @ftp_put($conn, $testFile, $tempFile, FTP_BINARY);
    if ($upload) {
        echo "✅ Archivo de prueba creado exitosamente\n";
        // Intentar eliminar archivo de prueba
        @ftp_delete($conn, $testFile);
        echo "✅ Archivo de prueba eliminado\n";
    } else {
        echo "❌ Error: No se pudo crear archivo de prueba\n";
    }
    
    unlink($tempFile);
    ftp_close($conn);
    
    echo "\n=== FIN DIAGNÓSTICO ===\n";
    return true;
}

// Ejecutar diagnóstico
$host = '192.168.0.13'; // o '192.168.0.100' según tu configuración
$port = 21;
$username = 'ftpuser';
$password = '1234';

testFTPConnection($host, $port, $username, $password);
?>