<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | GRUPO SEAL</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome para los iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <!-- CSS exclusivo para login -->
    <link rel="stylesheet" href="../assets/css/login-styles.css">
    
    <!-- Meta tags adicionales para mejor SEO y rendimiento -->
    <meta name="description" content="Iniciar sesión en el sistema de gestión de inventario GRUPO SEAL">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon.ico">
    <link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png">
</head>
<body>
    <div class="login-container">
        <!-- Sección Izquierda: Logo y Nombre -->
        <div class="left-section">
            <img src="../assets/img/logo.png" alt="Logo de GRUPO SEAL - Sistema de Gestión de Inventario" class="logo">
        </div>

        <!-- Línea Divisoria -->
        <div class="divider"></div>

        <!-- Sección Derecha: Formulario de Login -->
        <div class="right-section">
            <h2>Login</h2>
            
            <!-- Área para mensajes del sistema -->
            <div id="messages-area"></div>
            
            <form action="../auth/login.php" method="POST" id="loginForm">
                <div class="input-container">
                    <label for="correo">Correo Electrónico:</label>
                    <input 
                        type="email" 
                        id="correo" 
                        name="correo" 
                        required 
                        autocomplete="email"
                        placeholder="ejemplo@gruposeal.com"
                        aria-describedby="correo-help"
                    >
                </div>

                <div class="input-container">
                    <label for="password">Contraseña:</label>
                    <div class="password-container">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required 
                            autocomplete="current-password"
                            placeholder="Ingresa tu contraseña"
                            minlength="6"
                            aria-describedby="password-help"
                        >
                        <button 
                            type="button" 
                            class="password-toggle" 
                            id="togglePassword"
                            aria-label="Mostrar contraseña"
                            title="Mostrar/Ocultar contraseña"
                        >
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i>
                    Iniciar Sesión
                </button>
            </form>

            <!-- Opciones adicionales -->
            <div class="additional-options">
                <p>¿Problemas para acceder? <button type="button" id="helpBtn" class="link-button">Obtener ayuda</button></p>
            </div>
        </div>
    </div>

    <!-- Modal de ayuda -->
    <div id="helpModal" class="modal">
        <div class="modal-content">
            <span class="close" id="closeModal">&times;</span>
            <h3><i class="fas fa-question-circle"></i> Opciones de ayuda</h3>
            <div class="help-options">
                <div class="help-option">
                    <h4><i class="fas fa-clock"></i> Formulario bloqueado temporalmente</h4>
                    <p>Si has intentado varias veces sin éxito, el formulario se bloquea por seguridad.</p>
                    <button onclick="limpiarIntentosFallidos()" class="btn-help">Limpiar intentos</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript principal del login -->
    <script src="../assets/js/login-functions.js"></script>
    
    <!-- Herramientas de administrador (solo para desarrollo) -->
    <?php if (isset($_GET['admin']) && $_GET['admin'] === 'true'): ?>
    <script src="../assets/js/admin-utils.js"></script>
    <?php endif; ?>
</body>
</html>