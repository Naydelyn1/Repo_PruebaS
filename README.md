# Sistema de Gestión de Inventario COMSEPROA
## GRUPO SEAL - Sistema Integral de Control de Inventarios

![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat&logo=mysql&logoColor=white)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=flat&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=flat&logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=flat&logo=javascript&logoColor=black)

### 📋 Descripción

COMSEPROA es un sistema integral de gestión de inventarios desarrollado para GRUPO SEAL, diseñado para controlar y administrar eficientemente el inventario de uniformes, equipos de seguridad y materiales operativos distribuidos en múltiples almacenes.

### ✨ Características Principales

#### 🏢 **Gestión Multi-Almacén**
- Control de inventario en múltiples ubicaciones
- Transferencias automáticas entre almacenes
- Seguimiento en tiempo real de stock por ubicación
- Reportes consolidados y por almacén específico

#### 👥 **Sistema de Usuarios y Roles**
- **Administradores**: Control total del sistema
- **Almaceneros**: Gestión limitada a su almacén asignado
- Autenticación segura con sesiones
- Control de permisos granular

#### 📦 **Gestión de Productos**
- Categorización avanzada de productos
- Control detallado de stock (modelo, color, talla)
- Estados de productos (Nuevo, Usado, Dañado)
- Alertas de stock crítico
- Ajustes manuales de inventario

#### 🔄 **Sistema de Transferencias**
- Solicitudes de transferencia entre almacenes
- Flujo de aprobación/rechazo
- Notificaciones automáticas
- Historial completo de movimientos

#### 👔 **Gestión de Entregas**
- Registro detallado de entregas a personal
- Control por destinatario con DNI
- Historial por categoría de productos
- Reportes de entregas por período

#### 📊 **Reportes y Analytics**
- Inventario general y por almacén
- Análisis de movimientos
- Actividad de usuarios
- Productos con stock crítico
- Exportación a PDF

#### 🔔 **Sistema de Notificaciones**
- Solicitudes pendientes de aprobación
- Alertas de stock bajo
- Notificaciones en tiempo real
- Centro de notificaciones unificado

### 🛠️ Tecnologías Utilizadas

#### Backend
- **PHP 8.2+** - Lógica del servidor
- **MySQL/MariaDB** - Base de datos
- **PDO/MySQLi** - Conexión segura a base de datos

#### Frontend
- **HTML5** - Estructura
- **CSS3** - Estilos responsivos
- **JavaScript (Vanilla)** - Interactividad
- **Font Awesome 6.4.2** - Iconografía
- **Google Fonts (Poppins)** - Tipografía

#### Seguridad
- Autenticación por sesiones PHP
- Prepared statements (SQL Injection prevention)
- Validación de entrada
- Control de acceso por roles
- Regeneración de session ID

### 📁 Estructura del Proyecto

```
COMSEPROA_INVENTORY/
├── 📁 almacenes/           # Gestión de almacenes
│   ├── listar.php         # Lista de almacenes
│   ├── registrar.php      # Registro de almacenes
│   └── ver-almacen.php    # Detalle de almacén
├── 📁 assets/             # Recursos estáticos
│   ├── 📁 css/           # Hojas de estilo
│   ├── 📁 js/            # Scripts JavaScript
│   └── 📁 img/           # Imágenes
├── 📁 config/            # Configuración
│   └── database.php      # Conexión a BD
├── 📁 entregas/          # Gestión de entregas
│   └── historial.php     # Historial de entregas
├── 📁 logs/              # Logs del sistema
├── 📁 notificaciones/    # Sistema de notificaciones
│   ├── historial.php     # Historial de solicitudes
│   └── pendientes.php    # Solicitudes pendientes
├── 📁 perfil/            # Gestión de perfil
│   └── cambiar-password.php
├── 📁 productos/         # Gestión de productos
│   ├── listar.php        # Lista de productos
│   ├── registrar.php     # Registro de productos
│   ├── ver-producto.php  # Detalle de producto
│   └── eliminar_producto.php
├── 📁 reportes/          # Sistema de reportes
│   ├── inventario.php    # Reporte de inventario
│   ├── movimientos.php   # Reporte de movimientos
│   └── usuarios.php      # Actividad de usuarios
├── 📁 usuarios/          # Gestión de usuarios
│   ├── listar.php        # Lista de usuarios
│   └── registrar.php     # Registro de usuarios
├── 📁 views/             # Vistas de autenticación
├── comseproa_db.sql      # Script de base de datos
├── dashboard.php         # Panel principal
├── index.php            # Punto de entrada
└── logout.php           # Cerrar sesión
```

### 🗄️ Esquema de Base de Datos

#### Tablas Principales

**usuarios**
- Control de acceso y roles
- Asignación a almacenes específicos

**almacenes**
- Gestión de múltiples ubicaciones
- Información de ubicación

**productos**
- Inventario detallado
- Categorización y especificaciones

**movimientos**
- Historial completo de transacciones
- Tipos: entrada, salida, transferencia

**solicitudes_transferencia**
- Flujo de aprobación
- Trazabilidad de solicitudes

**entrega_uniformes**
- Registro de entregas a personal
- Control por destinatario

**logs_actividad**
- Auditoría completa del sistema
- Trazabilidad de acciones

### 🚀 Instalación

#### Prerrequisitos
- PHP 8.2 o superior
- MySQL 8.0 o MariaDB 10.4+
- Servidor web (Apache/Nginx)
- Extensiones PHP: `mysqli`, `pdo`, `session`

#### Pasos de Instalación

1. **Clonar/Descargar el proyecto**
```bash
# Si tienes Git
git clone (https://github.com/BrSilvinha/comseproa_inventory.git)

# O descargar y extraer el ZIP
```

2. **Configurar la base de datos**
```sql
-- Crear base de datos
CREATE DATABASE comseproa_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- Importar estructura y datos
mysql -u [usuario] -p comseproa_db < comseproa_db.sql
```

3. **Configurar conexión a base de datos**
```php
// config/database.php
$servername = "localhost";
$username = "tu_usuario";
$password = "tu_password";
$dbname = "comseproa_db";
```

4. **Configurar permisos de archivos**
```bash
# Linux/macOS
chmod 755 -R /ruta/al/proyecto
chmod 644 -R /ruta/al/proyecto/logs/
```

5. **Configurar servidor web**
- Apuntar document root a la carpeta del proyecto
- Habilitar mod_rewrite (Apache)
- Configurar PHP con las extensiones necesarias

### 👤 Usuarios por Defecto

El sistema incluye usuarios predeterminados para pruebas:

**Administrador**
- **Usuario**: jhamirsilva@gmail.com
- **Contraseña**: [Ver en base de datos - hash bcrypt]
- **Rol**: Administrador
- **Permisos**: Acceso completo al sistema

**Almacenero**
- **Usuario**: almaceneroolmos@gmail.com
- **Contraseña**: [Ver en base de datos - hash bcrypt]
- **Rol**: Almacenero
- **Almacén**: Grupo Sael - Olmos

> **Nota**: Las contraseñas están hasheadas con bcrypt. Se recomienda cambiarlas en el primer acceso.

### 🔧 Configuración

#### Variables de Entorno
```php
// Configuraciones recomendadas en config/database.php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_secure', 1); // Solo HTTPS
```

#### Configuración de Sesiones
- Timeout automático por inactividad
- Regeneración de session ID por seguridad
- Validación de roles en cada página

### 📱 Características Responsivas

- **Diseño Mobile-First**
- **Menú hamburguesa** para dispositivos móviles
- **Tablas responsivas** con scroll horizontal
- **Formularios optimizados** para touch
- **Interfaz adaptativa** para diferentes tamaños de pantalla

### 🔐 Seguridad Implementada

#### Autenticación
- Hashing seguro de contraseñas (bcrypt)
- Validación de sesiones
- Regeneración de session ID
- Timeout por inactividad

#### Autorización
- Control de acceso por roles
- Verificación de permisos por página
- Restricciones de almacén para almaceneros

#### Prevención de Ataques
- SQL Injection (prepared statements)
- XSS (htmlspecialchars, validación de entrada)
- CSRF (validaciones de origen)
- Session Hijacking (regeneración de ID)

### 📊 Funcionalidades Destacadas

#### Dashboard Inteligente
- **Estadísticas en tiempo real**
- **Accesos rápidos** por rol de usuario
- **Notificaciones integradas**
- **Navegación contextual**

#### Sistema de Inventario Avanzado
- **Control granular** de productos
- **Alertas automáticas** de stock crítico
- **Ajustes manuales** con auditoría
- **Categorización flexible**

#### Flujo de Transferencias
- **Solicitudes estructuradas**
- **Proceso de aprobación** configurable
- **Notificaciones automáticas**
- **Historial completo** de decisiones

#### Reportes Ejecutivos
- **Inventario consolidado** por almacén
- **Análisis de movimientos** con filtros
- **Actividad de usuarios** detallada
- **Exportación a PDF** profesional

### 🚨 Solución de Problemas

#### Problemas Comunes

**Error de conexión a base de datos**
```
- Verificar credenciales en config/database.php
- Confirmar que MySQL esté ejecutándose
- Validar permisos de usuario de BD
```

**Sesiones no funcionan**
```
- Verificar configuración de PHP sessions
- Confirmar permisos de escritura en /tmp
- Revisar configuración de cookies
```

**Problemas de permisos**
```
- Verificar permisos de archivos (755/644)
- Confirmar ownership del directorio web
- Revivar configuración de SELinux (si aplica)
```

### 🤝 Contribución

Para contribuir al proyecto:

1. Fork del repositorio
2. Crear rama feature (`git checkout -b feature/AmazingFeature`)
3. Commit cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Crear Pull Request

### 📝 Licencia

Este proyecto está desarrollado para uso interno de GRUPO SEAL. Todos los derechos reservados.

### 👨‍💻 Desarrollado por

**GRUPO SEAL - Equipo de Desarrollo**
- Sistema diseñado para gestión eficiente de inventarios
- Enfoque en seguridad y usabilidad
- Arquitectura escalable y mantenible

---

### 🔄 Versión Actual: 1.0.0

**Última actualización**: Junio 2025

**Próximas características**:
- [ ] API REST para integración móvil
- [ ] Dashboard con gráficos avanzados
- [ ] Sistema de códigos QR/códigos de barras
- [ ] Integración con proveedores
- [ ] App móvil nativa
- [ ] Backup automático de base de datos

---

*Desarrollado con ❤️ para optimizar la gestión de inventarios de GRUPO SEAL*
