let currentCategory = null;
let searchTimeout = null;
let inactivityTimeout = null;
const INACTIVITY_TIME = 3 * 60 * 1000;
const BASE_URL = 'http://192.168.0.13:8082/controllers/';


function showModal(modalId) {
    $(`#${modalId}`).fadeIn(300);
}

function hideModal(modalId) {
    $(`#${modalId}`).fadeOut(300);
}

function resetInactivityTimer() {
    clearTimeout(inactivityTimeout);
    inactivityTimeout = setTimeout(logout, INACTIVITY_TIME);
}
$(document).ready(function() {
    checkSession();
    $('#searchInput').on('input', function() {
        clearTimeout(searchTimeout);
        if ($(this).val().length >= 4) {
            searchTimeout = setTimeout(() => {
                searchFiles($(this).val());
            }, 500);
        }
    });
    $('.category-btn').on('click', function() {
        const category = $(this).data('category');
        toggleCategory($(this), category);
    });
    const uploadZone = $('#uploadZone');
    uploadZone.on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('dragover');
    });
    uploadZone.on('dragleave', function() {
        $(this).removeClass('dragover');
    });
    uploadZone.on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
        const file = e.originalEvent.dataTransfer.files[0];
        handleFileUpload(file);
    });
    $('#fileInput').on('change', function(e) {
        const file = e.target.files[0];
        handleFileUpload(file);
    });
    $(document).on('click mousemove keypress', resetInactivityTimer);
    $('.modal-close').on('click', function() {
        $(this).closest('.modal').fadeOut(300);
    });
    $('.modal').on('click', function(e) {
        if ($(e.target).hasClass('modal')) {
            $(this).fadeOut(300);
        }
    });
    $('.modal-content').on('click', function(e) {
        e.stopPropagation();
    });
});

function handleLogin(event) {
    event.preventDefault();
    const email = $('#loginEmail').val();
    const password = $('#loginPassword').val();

    $.ajax({
        type: "POST",
        url: BASE_URL + "AuthController.php",
        data: {
            opc: "auth",
            acc: "login",
            email: email,
            password: password
        },
        dataType: "json"
    }).done(function(response) {
        if (response.success) {
            hideModal('loginModal');
            updateUIForLoggedInUser(response.user);
            resetInactivityTimer();
        } else {
            alert("Error al iniciar sesión");
        }
    }).fail(function() {
        alert("Error al iniciar sesión");
    });
}

function handleRegister(event) {
    event.preventDefault();
    const email = $('#registerEmail').val();
    const password = $('#registerPassword').val();
    const passwordConfirm = $('#registerPasswordConfirm').val();
    const nombre = $('#registerName').val();
    const apellidoPaterno = $('#registerApellidoPaterno').val();
    const apellidoMaterno = $('#registerApellidoMaterno').val();

    if (password !== passwordConfirm) {
        alert("Las contraseñas no coinciden");
        return;
    }

    $.ajax({
        type: "POST",
        url: BASE_URL + "AuthController.php",
        data: {
            opc: "auth",
            acc: "register",
            email: email,
            password: password,
            nombre: nombre,
            apellido_paterno: apellidoPaterno,
            apellido_materno: apellidoMaterno
        },
        dataType: "json"
    }).done(function(response) {
        if (response.success) {
            hideModal('registerModal');
            alert(response.message || "Registro exitoso. Por favor, inicie sesión.");
        } else {
            let errorMessage = response.message || "Error al registrar usuario";
            if (response.missing_fields) {
                errorMessage += "\nCampos faltantes: " + response.missing_fields.join(", ");
            }
            if (response.error) {
                errorMessage += "\nDetalles: " + response.error;
            }
            alert(errorMessage);
            console.error("Error de registro:", response);
        }
    }).fail(function(jqXHR, textStatus, errorThrown) {
        console.error("Error AJAX:", textStatus, errorThrown);
        console.error("Respuesta del servidor:", jqXHR.responseText);
        alert("Error al registrar usuario. Revisa la consola para más detalles.");
    });
}

function logout() {
    $.ajax({
        type: "POST",
        url: BASE_URL + "AuthController.php",
        data: {
            opc: "auth",
            acc: "logout"
        }
    }).done(function(response) {
        console.log("Respuesta de logout:", response);
        if (response == "1") {
            alert("Sesión cerrada exitosamente.");
            window.location.reload();
        } else {
            alert("Error al cerrar sesión. Respuesta inesperada del servidor.");
            console.error("Respuesta de logout inesperada:", response);
        }
    }).fail(function(jqXHR, textStatus, errorThrown) {
        console.error("Error AJAX al cerrar sesión:", textStatus, errorThrown);
        console.error("Respuesta del servidor (fail):", jqXHR.responseText);
        alert("Error al cerrar sesión.");
    });
}

function checkSession() {
    $.ajax({
        type: "POST",
        url: BASE_URL + "AuthController.php",
        data: {
            opc: "auth",
            acc: "checkSession"
        },
        dataType: "json"
    }).done(function(response) {
        if (response.loggedIn) {
            updateUIForLoggedInUser(response.user);
            resetInactivityTimer();
        }
    }).fail(function() {
        console.error("Error al verificar sesión");
    });
}

async function searchFiles(query) {
    try {
        const response = await $.ajax({
            type: "POST",
            url: BASE_URL + "FileController.php",
            data: {
                opc: "file",
                acc: "search",
                query: query,
                category: currentCategory
            },
            dataType: "json"
        });
        displayResults(response.files);
    } catch (error) {
        console.error('Error:', error);
    }
}

function toggleCategory(btn, category) {
    const wasActive = btn.hasClass('active');
    
    $('.category-btn').removeClass('active');
    
    if (!wasActive) {
        btn.addClass('active');
        currentCategory = category;
    } else {
        currentCategory = null;
    }
    
    const searchQuery = $('#searchInput').val();
    if (searchQuery.length >= 4) {
        searchFiles(searchQuery);
    } else {
        showAllFiles();
    }
}

// Funciones de manejo de archivos
async function handleFileUpload(file) {
    if (!file) return;

    const fileType = file.type;
    let categoria = 'documentos';

    if (fileType.startsWith('image/')) {
        categoria = 'imagenes';
    } else if (fileType.startsWith('video/')) {
        categoria = 'videos';
    } else if (fileType.startsWith('audio/')) {
        categoria = 'musica';
    } else if (fileType === 'application/pdf' || 
               fileType === 'application/msword' || 
               fileType === 'application/vnd.ms-excel' || 
               fileType === 'text/plain') {
        categoria = 'documentos';
    } else {
        alert('Error: El tipo de archivo no es compatible con ninguna categoría.');
        return;
    }

    if (!confirm(`¿Seguro que deseas guardar este archivo en la categoría "${categoria}"?`)) {
        return;
    }

    const formData = new FormData();
    formData.append('file', file);
    formData.append('opc', 'file');
    formData.append('acc', 'upload');
    formData.append('categoria', categoria);

    try {
        const response = await $.ajax({
            url: BASE_URL + "FileController.php",
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false
        });

        if (response === "1") {
            alert('Archivo subido exitosamente');
            hideModal('uploadModal');
            $('#fileInput').val('');
            const searchQuery = $('#searchInput').val();
            if (searchQuery.length >= 4) {
                searchFiles(searchQuery);
            }
        } else {
            alert('Error al subir el archivo');
            $('#fileInput').val('');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al subir el archivo');
        $('#fileInput').val('');
    }
}

function resetUploadModal() {
    $('#fileInput').val('');
    $('#uploadZone').removeClass('dragover');
}

function showUploadForm() {
    resetUploadModal();
    showModal('uploadModal');
}

async function deleteFile(fileId) {
    if (!confirm('¿Está seguro de eliminar este archivo?')) return;

    try {
        const response = await $.ajax({
            type: "POST",
            url: BASE_URL + "FileController.php",
            data: {
                opc: "file",
                acc: "delete",
                id: fileId
            }
        });

        if (response === "1") {
            const searchQuery = $('#searchInput').val();
            if (searchQuery.length >= 4) {
                searchFiles(searchQuery);
            }
        } else {
            alert('Error al eliminar el archivo');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al eliminar el archivo');
    }
}

async function downloadFile(fileId) {
    try {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = BASE_URL + 'FileController.php';
        form.target = '_blank';

        const opcInput = document.createElement('input');
        opcInput.type = 'hidden';
        opcInput.name = 'opc';
        opcInput.value = 'file';

        const accInput = document.createElement('input');
        accInput.type = 'hidden';
        accInput.name = 'acc';
        accInput.value = 'download';

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = fileId;

        form.appendChild(opcInput);
        form.appendChild(accInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    } catch (error) {
        console.error('Error:', error);
        alert('Error al descargar el archivo');
    }
}

function updateUIForLoggedInUser(user) {
    $('#authButtons').hide();
    $('#userInfo').show();
    $('#userEmail').text(user.email);
}

function displayResults(files) {
    const fileList = $('#fileList');
    fileList.empty();

    if (files.length === 0) {
        fileList.html(`
            <div class="no-results">
                <i class="fas fa-search no-results-icon"></i>
                <p>No se encontraron archivos</p>
            </div>
        `);
        return;
    }

    $('.results-count').text(`${files.length} resultado${files.length !== 1 ? 's' : ''} encontrado${files.length !== 1 ? 's' : ''}`);

    files.forEach(file => {
        const li = $('<li>').addClass(`file-item ${getFileCategoryClass(file.tipo)} ${file.isOwner ? 'own-file' : ''}`);
        
        li.html(`
            <div class="file-info">
                <div class="file-icon">
                    <i class="fas ${getFileIcon(file.tipo)}"></i>
                </div>
                <div class="file-details">
                    <div class="file-name">${file.nombre}</div>
                    <div class="file-meta">
                        <span class="file-type">📄 ${getFileTypeName(file.tipo)}</span>
                        <span class="file-size">💾 ${formatFileSize(file.tamaño)}</span>
                        <span class="file-owner">👤 Subido por: ${file.user_email}</span>
                    </div>
                </div>
            </div>
            <div class="file-actions">
                <button class="btn btn-primary" onclick="downloadFile(${file.id})" title="Descargar archivo">
                    <i class="fas fa-download"></i>
                </button>
                ${file.isOwner ? `
                    <button class="btn btn-danger" onclick="deleteFile(${file.id})" title="Eliminar archivo">
                        <i class="fas fa-trash"></i>
                    </button>
                ` : ''}
            </div>
        `);
        
        fileList.append(li);
    });
}

function getFileTypeName(tipo) {
    const tiposMIME = {
        'image/jpeg': 'Imagen',
        'image/jpg': 'Imagen',
        'image/png': 'Imagen',
        'image/gif': 'Imagen',
        'video/mp4': 'Video',
        'video/quicktime': 'Video',
        'audio/mpeg': 'Audio',
        'audio/mp3': 'Audio',
        'application/pdf': 'PDF',
        'application/msword': 'Documento Word',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'Documento Word',
        'application/vnd.ms-excel': 'Hoja de cálculo',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'Hoja de cálculo',
        'text/plain': 'Archivo de texto'
    };

    if (tiposMIME[tipo]) {
        return tiposMIME[tipo];
    }

    const extension = tipo.split('.').pop().toLowerCase();
    const tiposExtension = {
        'jpg': 'Imagen',
        'jpeg': 'Imagen',
        'png': 'Imagen',
        'gif': 'Imagen',
        'mp4': 'Video',
        'mov': 'Video',
        'mp3': 'Audio',
        'wav': 'Audio',
        'pdf': 'PDF',
        'doc': 'Documento Word',
        'docx': 'Documento Word',
        'xls': 'Hoja de cálculo',
        'xlsx': 'Hoja de cálculo',
        'txt': 'Archivo de texto'
    };

    return tiposExtension[extension] || 'Documento';
}

function getFileIcon(tipo) {
    const icons = {
        'image': 'fa-image',
        'video': 'fa-video',
        'audio': 'fa-music',
        'application/pdf': 'fa-file-pdf',
        'application/msword': 'fa-file-word',
        'application/vnd.ms-excel': 'fa-file-excel',
        'text/plain': 'fa-file-alt'
    };
    return icons[tipo] || 'fa-file';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function getFileCategoryClass(tipo) {
    if (tipo.startsWith('image/')) return 'image';
    if (tipo.startsWith('video/')) return 'video';
    if (tipo.startsWith('audio/')) return 'music';
    return 'document';
}

function clearFilters() {
    $('#searchInput').val('');
    $('.category-btn').removeClass('active');
    currentCategory = null;
    showAllFiles();
}

function showProfile() {
    showModal('profileModal');
    loadProfileInfo();
}

function loadProfileInfo() {
    $.ajax({
        type: "POST",
        url: BASE_URL + "AuthController.php",
        data: {
            opc: "auth",
            acc: "checkSession"
        },
        dataType: "json"
    }).done(function(response) {
        if (response.loggedIn) {
            const profileInfo = $('#profileInfo');
            profileInfo.html(`
                <div class="profile-view">
                    <div class="profile-field">
                        <label><strong>Correo:</strong></label>
                        <p>${response.user.email}</p>
                    </div>
                    <div class="profile-field">
                        <label><strong>Nombre:</strong></label>
                        <p>${response.user.nombre}</p>
                    </div>
                    <div class="profile-field">
                        <label><strong>Apellido Paterno:</strong></label>
                        <p>${response.user.apellido_paterno}</p>
                    </div>
                    <div class="profile-field">
                        <label><strong>Apellido Materno:</strong></label>
                        <p>${response.user.apellido_materno}</p>
                    </div>
                    <div class="profile-actions">
                         <button type="button" class="btn btn-primary" id="editProfileButton" onclick="showEditProfile()">Editar Datos</button>
                    </div>
                </div>
            `);
            $('#editProfileButton').show();
        }
    }).fail(function() {
        console.error("Error al cargar información del perfil");
    });
}

function showEditProfile() {
    $('#editProfileButton').hide();

    $.ajax({
        type: "POST",
        url: BASE_URL + "AuthController.php",
        data: {
            opc: "auth",
            acc: "checkSession"
        },
        dataType: "json"
    }).done(function(response) {
        if (response.loggedIn) {
            const profileInfo = $('#profileInfo');
            profileInfo.html(`
                <form id="editProfileForm" onsubmit="return updateProfile(event)">
                    <div class="profile-field">
                        <label><strong>Correo:</strong></label>
                        <p>${response.user.email}</p>
                    </div>
                    <div class="profile-field">
                        <label for="profileName"><strong>Nombre:</strong></label>
                        <input type="text" id="profileName" class="form-control" value="${response.user.nombre}" required>
                    </div>
                    <div class="profile-field">
                        <label for="profileApellidoPaterno"><strong>Apellido Paterno:</strong></label>
                        <input type="text" id="profileApellidoPaterno" class="form-control" value="${response.user.apellido_paterno}" required>
                    </div>
                    <div class="profile-field">
                        <label for="profileApellidoMaterno"><strong>Apellido Materno:</strong></label>
                        <input type="text" id="profileApellidoMaterno" class="form-control" value="${response.user.apellido_materno}" required>
                    </div>
                    <div class="profile-actions">
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        <button type="button" class="btn btn-secondary" onclick="loadProfileInfo()">Cancelar</button>
                    </div>
                </form>
            `);
        }
    }).fail(function() {
        console.error("Error al cargar información del perfil");
    });
}

function updateProfile(event) {
    event.preventDefault();
    const nombre = $('#profileName').val();
    const apellidoPaterno = $('#profileApellidoPaterno').val();
    const apellidoMaterno = $('#profileApellidoMaterno').val();

    $.ajax({
        type: "POST",
        url: BASE_URL + "AuthController.php",
        data: {
            opc: "auth",
            acc: "updateProfile",
            nombre: nombre,
            apellido_paterno: apellidoPaterno,
            apellido_materno: apellidoMaterno
        }
    }).done(function(response) {
        console.log("Respuesta de updateProfile:", response);
        if (response == "1") {
            // alert("Perfil actualizado exitosamente");
            loadProfileInfo();
        } else {
            alert("Error al actualizar perfil");
        }
    }).fail(function(jqXHR, textStatus, errorThrown) {
        console.error("Error AJAX al actualizar perfil:", textStatus, errorThrown);
        console.error("Respuesta del servidor (fail):", jqXHR.responseText);
        alert("Error al actualizar perfil");
    });
    return false;
}

async function showAllFiles() {
    try {
        const response = await $.ajax({
            type: "POST",
            url: BASE_URL + "FileController.php",
            data: {
                opc: "file",
                acc: "getAll"
            },
            dataType: "json"
        });
        
        if (response.files) {
            displayResults(response.files);
            $('#searchInput').val('');
            $('.category-btn').removeClass('active');
            currentCategory = null;
        } else {
            alert('Error al obtener los archivos');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al obtener los archivos');
    }
} 