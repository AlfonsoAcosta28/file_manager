<?php
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestor de Archivos</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    
    
    <!-- <script src="http://192.168.0.13:8081/Jquery.js"></script> -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="http://192.168.0.13:8080/styles.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="nav container">
            <h1>Gestor de Archivos</h1>
            <div class="auth-buttons" id="authButtons">
                <button class="btn btn-primary" onclick="showModal('loginModal')">Iniciar Sesión</button>
                <button class="btn btn-primary" onclick="showModal('registerModal')">Registrarse</button>
            </div>
            <div class="user-info" id="userInfo" style="display: none;">
                <span id="userEmail" class="user-email" onclick="showProfile()" style="cursor: pointer; text-decoration: underline;"></span>
                <button class="btn btn-primary" onclick="showUploadForm()">Contribuir con Archivo</button>
                <button class="btn btn-danger" onclick="logout()">Cerrar Sesión</button>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="container">
        <!-- Hero Section -->
        <section class="hero">
            <h1>Gestor de Archivos</h1>
            <p>Organiza y comparte tus archivos de manera sencilla y eficiente</p>
        </section>

        <!-- Search Section -->
        <section class="search-section">
            <div class="search-container">
                <div class="search-header">
                    <div class="search-wrapper">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="search-input" id="searchInput" placeholder="Buscar archivos...">
                    </div>
                    <button class="btn btn-primary" onclick="showAllFiles()">
                        <i class="fas fa-list"></i> Ver todos los documentos
                    </button>
                </div>
            </div>
        </section>

        <!-- Categories -->
        <section class="categories">
            <h2>Categorías</h2>
            <div class="category-grid">
                <div class="category-btn" data-category="musica">
                    <i class="fas fa-music category-icon"></i>
                    <span>Música</span>
                </div>
                <div class="category-btn" data-category="videos">
                    <i class="fas fa-video category-icon"></i>
                    <span>Videos</span>
                </div>
                <div class="category-btn" data-category="documentos">
                    <i class="fas fa-file-alt category-icon"></i>
                    <span>Documentos</span>
                </div>
                <div class="category-btn" data-category="imagenes">
                    <i class="fas fa-image category-icon"></i>
                    <span>Imágenes</span>
                </div>
            </div>
        </section>

        <!-- Results Section -->
        <section class="results-section">
            <div class="results-header">
                <div class="results-count">Resultados encontrados</div>
                <button class="clear-filters" onclick="clearFilters()">
                    <i class="fas fa-times"></i> Limpiar filtros
                </button>
            </div>
            <ul class="file-list" id="fileList">
                <!-- Los resultados se cargarán dinámicamente aquí -->
            </ul>
        </section>
    </main>

    <!-- Login Modal -->
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <button class="modal-close">&times;</button>
            <h2>Iniciar Sesión</h2>
            <form id="loginForm" onsubmit="return handleLogin(event)">
                <div class="form-group">
                    <label for="loginEmail">Correo Electrónico</label>
                    <input type="email" id="loginEmail" required>
                </div>
                <div class="form-group">
                    <label for="loginPassword">Contraseña</label>
                    <input type="password" id="loginPassword" required>
                </div>
                <button type="submit" class="btn btn-primary">Iniciar Sesión</button>
            </form>
        </div>
    </div>

    <!-- Register Modal -->
    <div id="registerModal" class="modal">
        <div class="modal-content">
            <button class="modal-close">&times;</button>
            <h2>Registro</h2>
            <form id="registerForm" onsubmit="return handleRegister(event)">
                <div class="form-group">
                    <label for="registerEmail">Correo Electrónico</label>
                    <input type="email" id="registerEmail" required>
                </div>
                <div class="form-group">
                    <label for="registerName">Nombre</label>
                    <input type="text" id="registerName" required>
                </div>
                <div class="form-group">
                    <label for="registerApellidoPaterno">Apellido Paterno</label>
                    <input type="text" id="registerApellidoPaterno" required>
                </div>
                <div class="form-group">
                    <label for="registerApellidoMaterno">Apellido Materno</label>
                    <input type="text" id="registerApellidoMaterno" required>
                </div>
                <div class="form-group">
                    <label for="registerPassword">Contraseña</label>
                    <input type="password" id="registerPassword" required>
                </div>
                <div class="form-group">
                    <label for="registerPasswordConfirm">Confirmar Contraseña</label>
                    <input type="password" id="registerPasswordConfirm" required>
                </div>
                <button type="submit" class="btn btn-primary">Registrarse</button>
            </form>
        </div>
    </div>

    <!-- Upload Modal -->
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <button class="modal-close">&times;</button>
            <h2>Subir Archivo</h2>
            <div class="upload-zone" id="uploadZone">
                <i class="fas fa-cloud-upload-alt fa-3x"></i>
                <p>Arrastra y suelta tu archivo aquí</p>
                <p>o</p>
                <input type="file" id="fileInput" style="display: none;">
                <button class="btn btn-primary" onclick="document.getElementById('fileInput').click()">Seleccionar Archivo</button>
            </div>
        </div>
    </div>

    <!-- Profile Modal -->
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <button class="modal-close">&times;</button>
            <h2>Perfil de Usuario</h2>
            <div class="profile-info" id="profileInfo">
                <!-- La información del perfil se cargará dinámicamente aquí -->
            </div>
            <!-- <button class="btn btn-primary" onclick="showEditProfile()">Editar Perfil</button> -->
        </div>
    </div>

    <script src="http://192.168.0.13:8081/main.js"></script>
</body>
</html> 