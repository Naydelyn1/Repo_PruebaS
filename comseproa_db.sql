-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 04-06-2025 a las 08:03:22
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `comseproa_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `almacenes`
--

CREATE TABLE `almacenes` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `ubicacion` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `almacenes`
--

INSERT INTO `almacenes` (`id`, `nombre`, `ubicacion`) VALUES
(3, 'Grupo Seal - Motupe', 'Motupe - Lambayeque'),
(4, 'Grupo Sael - Olmos', 'Olmos - Lambayeque');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id`, `nombre`) VALUES
(2, 'Accesorios de Seguridad'),
(4, 'Armas'),
(3, 'Kebras y Fundas Nuevas'),
(1, 'Ropa'),
(6, 'Walkie-Talkie');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `entrega_uniformes`
--

CREATE TABLE `entrega_uniformes` (
  `id` int(11) NOT NULL,
  `usuario_responsable_id` int(11) NOT NULL,
  `nombre_destinatario` varchar(100) NOT NULL,
  `dni_destinatario` varchar(8) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `almacen_id` int(11) NOT NULL,
  `fecha_entrega` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `entrega_uniformes`
--

INSERT INTO `entrega_uniformes` (`id`, `usuario_responsable_id`, `nombre_destinatario`, `dni_destinatario`, `producto_id`, `cantidad`, `almacen_id`, `fecha_entrega`) VALUES
(1, 8, 'prueba de entrega 1', '71749437', 200, 1, 3, '2025-06-03 17:24:18'),
(2, 8, 'prueba de entrega 1', '71749437', 201, 1, 3, '2025-06-03 17:24:18'),
(3, 8, 'prueba de entrega 1', '71749437', 202, 1, 3, '2025-06-03 17:24:18'),
(4, 8, 'prueba de entrega 1', '71749437', 203, 1, 3, '2025-06-03 17:24:18'),
(5, 8, 'prueba de entrega 1', '71749437', 204, 1, 3, '2025-06-03 17:24:18'),
(6, 8, 'prueba de entrega 1', '71749437', 208, 1, 3, '2025-06-03 17:24:18'),
(7, 8, 'prueba de entrega 2', '12312345', 212, 1, 3, '2025-06-03 18:04:38'),
(8, 8, 'prueba de entrega 2', '12312345', 211, 1, 3, '2025-06-03 18:04:38'),
(9, 8, 'prueba de entrega 2', '12312345', 205, 1, 3, '2025-06-03 18:04:38'),
(10, 8, 'prueba de entrega 2', '12312345', 215, 1, 3, '2025-06-03 18:04:38'),
(11, 8, 'prueba de entrega 2', '12312345', 200, 1, 3, '2025-06-03 18:04:38'),
(12, 8, 'prueba de entrega 2', '12312345', 207, 1, 3, '2025-06-03 18:04:38'),
(13, 8, 'luis preubaa 03', '71749437', 196, 1, 3, '2025-06-03 21:44:38'),
(14, 8, 'sheral altamirano vega', '71717171', 207, 1, 3, '2025-06-04 03:53:06'),
(15, 8, 'sheral altamirano vega', '71717171', 200, 1, 3, '2025-06-04 03:53:06'),
(16, 8, 'sheral altamirano vega', '71717171', 205, 1, 3, '2025-06-04 03:53:06'),
(17, 8, 'sheral altamirano vega', '71717171', 211, 1, 3, '2025-06-04 03:53:06'),
(18, 8, 'sheral altamirano vega', '71717171', 212, 1, 3, '2025-06-04 03:53:06'),
(19, 8, 'sheral altamirano vega', '71717171', 204, 1, 3, '2025-06-04 03:53:06'),
(20, 8, 'sheral altamirano vega', '71717171', 208, 1, 3, '2025-06-04 03:53:06');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `logs_actividad`
--

CREATE TABLE `logs_actividad` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `accion` varchar(100) NOT NULL,
  `detalle` text DEFAULT NULL,
  `fecha_accion` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `logs_actividad`
--

INSERT INTO `logs_actividad` (`id`, `usuario_id`, `accion`, `detalle`, `fecha_accion`, `ip_address`, `user_agent`) VALUES
(2, 1, 'SOLICITAR_TRANSFERENCIA', 'Solicitó transferencia de 1 unidades de \'Camisa Blanca\' desde Grupo Seal - Motupe hacia Grupo Sael - Olmos', '2025-06-01 19:45:05', NULL, NULL),
(3, 1, 'SOLICITAR_TRANSFERENCIA', 'Solicitó transferencia de 3 unidades de \'Botas de seguridad\' desde Grupo Seal - Motupe hacia Grupo Sael - Olmos', '2025-06-01 20:04:51', NULL, NULL),
(4, 1, 'SOLICITAR_TRANSFERENCIA', 'Solicitó transferencia de 3 unidades de \'Camisa Blanca\' desde Grupo Seal - Motupe hacia Grupo Sael - Olmos', '2025-06-01 20:05:00', NULL, NULL),
(5, 1, 'SOLICITAR_TRANSFERENCIA', 'Solicitó transferencia de 9 unidades de \'Camisa Blanca\' desde Grupo Seal - Motupe hacia Grupo Sael - Olmos', '2025-06-01 20:25:48', NULL, NULL),
(6, 8, 'ACTUALIZAR_STOCK', 'Actualizó stock del producto \'Chaleco\' de 4 a 5 unidades', '2025-06-03 08:07:03', NULL, NULL),
(7, 8, 'ACTUALIZAR_STOCK', 'Actualizó stock del producto \'Chaleco\' de 5 a 6 unidades', '2025-06-03 08:07:03', NULL, NULL),
(8, 8, 'ACTUALIZAR_STOCK', 'Actualizó stock del producto \'Chaleco\' de 6 a 7 unidades', '2025-06-03 08:07:03', NULL, NULL),
(9, 8, 'ACTUALIZAR_STOCK', 'Actualizó stock del producto \'Chaleco\' de 7 a 8 unidades', '2025-06-03 08:07:03', NULL, NULL),
(10, 8, 'ACTUALIZAR_STOCK', 'Actualizó stock del producto \'Chaleco\' de 8 a 9 unidades', '2025-06-03 08:07:04', NULL, NULL),
(11, 8, 'ACTUALIZAR_STOCK', 'Actualizó stock del producto \'Chaleco\' de 9 a 10 unidades', '2025-06-03 08:07:04', NULL, NULL),
(12, 8, 'SOLICITAR_TRANSFERENCIA', 'Solicitó transferencia de 9 unidades de \'Chaleco\' desde Grupo Seal - Motupe hacia Grupo Sael - Olmos', '2025-06-03 08:07:18', NULL, NULL),
(13, 8, 'ACTUALIZAR_STOCK', 'Actualizó stock del producto \'Camisa Blanca\' de 2 a 3 unidades', '2025-06-03 17:23:33', NULL, NULL),
(14, 8, 'ACTUALIZAR_STOCK', 'Actualizó stock del producto \'Camisa Blanca\' de 3 a 4 unidades', '2025-06-03 17:23:34', NULL, NULL),
(15, 8, 'SOLICITAR_TRANSFERENCIA', 'Solicitó transferencia de 1 unidades de \'Camisa Blanca\' desde Grupo Seal - Motupe hacia Grupo Sael - Olmos', '2025-06-03 17:23:37', NULL, NULL),
(16, 8, 'ENTREGA_PRODUCTOS', 'Entregó 6 tipo(s) de productos (6 unidades total) a prueba de entrega 1 (DNI: 71749437). Productos: Camisa Blanca, Camisa Blanca, Camisa Blanca...', '2025-06-03 17:24:18', NULL, NULL),
(17, 8, 'ENTREGA_PRODUCTOS', 'Entregó 6 tipo(s) de productos (6 unidades total) a prueba de entrega 2 (DNI: 12312345). Productos: Chaleco, Chaleco, Cascos...', '2025-06-03 18:04:38', NULL, NULL),
(18, 8, 'ENTREGA_PRODUCTOS', 'Entregó 1 tipo(s) de productos (1 unidades total) a luis preubaa 03 (DNI: 71749437). Productos: Icom-sueltas', '2025-06-03 21:44:38', NULL, NULL),
(19, 8, 'ENTREGA_PRODUCTOS', 'Entregó 7 tipo(s) de productos (7 unidades total) a sheral altamirano vega (DNI: 71717171). Productos: Botas de seguridad, Camisa Blanca, Cascos...', '2025-06-04 03:53:06', NULL, NULL),
(20, 8, 'ACTUALIZAR_STOCK', 'Actualizó stock del producto \'Camisa Blanca\' de 0 a 1 unidades', '2025-06-04 03:54:00', NULL, NULL),
(21, 8, 'ACTUALIZAR_STOCK', 'Actualizó stock del producto \'Chaleco\' de 0 a 1 unidades', '2025-06-04 03:54:02', NULL, NULL),
(22, 8, 'ACTUALIZAR_STOCK', 'Actualizó stock del producto \'Chaleco\' de 1 a 0 unidades', '2025-06-04 03:54:03', NULL, NULL),
(23, 8, 'SOLICITAR_TRANSFERENCIA', 'Solicitó transferencia de 6 unidades de \'Cascos\' desde Grupo Seal - Motupe hacia Grupo Sael - Olmos', '2025-06-04 03:54:12', NULL, NULL),
(24, 8, 'ACTUALIZAR_STOCK', 'Actualizó stock del producto \'Camisa Blanca\' de 0 a 1 unidades', '2025-06-04 03:55:30', NULL, NULL),
(25, 8, 'ACTUALIZAR_STOCK', 'Actualizó stock del producto \'Camisa Blanca\' de 1 a 2 unidades', '2025-06-04 03:55:30', NULL, NULL),
(26, 8, 'ACTUALIZAR_STOCK', 'Actualizó stock del producto \'Camisa Blanca\' de 2 a 3 unidades', '2025-06-04 03:55:30', NULL, NULL),
(27, 8, 'ACTUALIZAR_STOCK', 'Actualizó stock del producto \'Camisa Blanca\' de 3 a 4 unidades', '2025-06-04 03:55:30', NULL, NULL),
(28, 8, 'ACTUALIZAR_STOCK', 'Actualizó stock del producto \'Camisa Blanca\' de 4 a 5 unidades', '2025-06-04 03:55:30', NULL, NULL),
(29, 8, 'ACTUALIZAR_STOCK', 'Actualizó stock del producto \'Camisa Blanca\' de 5 a 6 unidades', '2025-06-04 03:55:30', NULL, NULL),
(30, 8, 'SOLICITAR_TRANSFERENCIA', 'Solicitó transferencia de 3 unidades de \'Icom-sueltas\' desde Grupo Seal - Motupe hacia Grupo Sael - Olmos', '2025-06-04 06:02:55', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos`
--

CREATE TABLE `movimientos` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `almacen_origen` int(11) DEFAULT NULL,
  `almacen_destino` int(11) DEFAULT NULL,
  `cantidad` int(11) NOT NULL,
  `tipo` enum('entrada','salida','transferencia','ajuste') NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario_id` int(11) NOT NULL,
  `estado` enum('pendiente','completado','rechazado') DEFAULT 'pendiente',
  `descripcion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `movimientos`
--

INSERT INTO `movimientos` (`id`, `producto_id`, `almacen_origen`, `almacen_destino`, `cantidad`, `tipo`, `fecha`, `usuario_id`, `estado`, `descripcion`) VALUES
(1, 196, 3, NULL, 1, 'entrada', '2025-04-09 17:23:42', 8, 'completado', NULL),
(2, 205, 3, NULL, 1, 'entrada', '2025-04-09 20:51:22', 8, 'completado', NULL),
(3, 205, 3, NULL, 1, 'entrada', '2025-04-09 20:51:23', 8, 'completado', NULL),
(4, 205, 3, NULL, 1, 'entrada', '2025-04-09 20:51:23', 8, 'completado', NULL),
(5, 205, 3, NULL, 1, 'entrada', '2025-04-09 20:51:23', 8, 'completado', NULL),
(6, 205, 3, NULL, 1, 'entrada', '2025-04-09 20:51:24', 8, 'completado', NULL),
(7, 205, 3, NULL, 1, 'entrada', '2025-04-09 20:51:25', 8, 'completado', NULL),
(8, 201, 3, 4, 2, 'transferencia', '2025-05-25 14:45:29', 8, 'completado', NULL),
(9, 215, 3, NULL, 13, 'entrada', '2025-06-01 14:25:27', 8, 'completado', NULL),
(11, 200, 3, 4, 1, 'transferencia', '2025-06-01 19:45:05', 1, 'pendiente', 'Solicitud de transferencia #6: 1 unidades de Camisa Blanca a Grupo Sael - Olmos'),
(13, 200, 3, 4, 9, 'transferencia', '2025-06-03 03:18:44', 1, 'completado', 'Transferencia aprobada desde solicitud #9'),
(14, 213, 3, NULL, 1, 'entrada', '2025-06-03 08:07:03', 8, 'completado', 'Ajuste manual de cantidad: sumar 1 unidad'),
(15, 213, 3, NULL, 1, 'entrada', '2025-06-03 08:07:03', 8, 'completado', 'Ajuste manual de cantidad: sumar 1 unidad'),
(16, 213, 3, NULL, 1, 'entrada', '2025-06-03 08:07:03', 8, 'completado', 'Ajuste manual de cantidad: sumar 1 unidad'),
(17, 213, 3, NULL, 1, 'entrada', '2025-06-03 08:07:03', 8, 'completado', 'Ajuste manual de cantidad: sumar 1 unidad'),
(18, 213, 3, NULL, 1, 'entrada', '2025-06-03 08:07:04', 8, 'completado', 'Ajuste manual de cantidad: sumar 1 unidad'),
(19, 213, 3, NULL, 1, 'entrada', '2025-06-03 08:07:04', 8, 'completado', 'Ajuste manual de cantidad: sumar 1 unidad'),
(20, 213, 3, 4, 9, 'transferencia', '2025-06-03 08:07:29', 8, 'completado', 'Transferencia aprobada desde solicitud #10'),
(21, 200, 3, NULL, 1, 'entrada', '2025-06-03 17:23:33', 8, 'completado', 'Ajuste manual de cantidad: sumar 1 unidad'),
(22, 200, 3, NULL, 1, 'entrada', '2025-06-03 17:23:34', 8, 'completado', 'Ajuste manual de cantidad: sumar 1 unidad'),
(23, 200, 3, NULL, 1, 'salida', '2025-06-03 17:24:18', 8, 'completado', NULL),
(24, 201, 3, NULL, 1, 'salida', '2025-06-03 17:24:18', 8, 'completado', NULL),
(25, 202, 3, NULL, 1, 'salida', '2025-06-03 17:24:18', 8, 'completado', NULL),
(26, 203, 3, NULL, 1, 'salida', '2025-06-03 17:24:18', 8, 'completado', NULL),
(27, 204, 3, NULL, 1, 'salida', '2025-06-03 17:24:18', 8, 'completado', NULL),
(28, 208, 3, NULL, 1, 'salida', '2025-06-03 17:24:18', 8, 'completado', NULL),
(29, 200, 3, 4, 1, 'transferencia', '2025-06-03 17:24:55', 8, 'completado', 'Transferencia aprobada desde solicitud #11'),
(30, 212, 3, NULL, 1, 'salida', '2025-06-03 18:04:38', 8, 'completado', NULL),
(31, 211, 3, NULL, 1, 'salida', '2025-06-03 18:04:38', 8, 'completado', NULL),
(32, 205, 3, NULL, 1, 'salida', '2025-06-03 18:04:38', 8, 'completado', NULL),
(33, 215, 3, NULL, 1, 'salida', '2025-06-03 18:04:38', 8, 'completado', NULL),
(34, 200, 3, NULL, 1, 'salida', '2025-06-03 18:04:38', 8, 'completado', NULL),
(35, 207, 3, NULL, 1, 'salida', '2025-06-03 18:04:38', 8, 'completado', NULL),
(36, 218, 3, NULL, 1, 'entrada', '2025-06-03 21:24:43', 8, 'completado', NULL),
(37, 196, 3, NULL, 1, 'salida', '2025-06-03 21:44:38', 8, 'completado', NULL),
(38, 207, 3, NULL, 1, 'salida', '2025-06-04 03:53:06', 8, 'completado', NULL),
(39, 200, 3, NULL, 1, 'salida', '2025-06-04 03:53:06', 8, 'completado', NULL),
(40, 205, 3, NULL, 1, 'salida', '2025-06-04 03:53:06', 8, 'completado', NULL),
(41, 211, 3, NULL, 1, 'salida', '2025-06-04 03:53:06', 8, 'completado', NULL),
(42, 212, 3, NULL, 1, 'salida', '2025-06-04 03:53:06', 8, 'completado', NULL),
(43, 204, 3, NULL, 1, 'salida', '2025-06-04 03:53:06', 8, 'completado', NULL),
(44, 208, 3, NULL, 1, 'salida', '2025-06-04 03:53:06', 8, 'completado', NULL),
(45, 202, 3, NULL, 1, 'entrada', '2025-06-04 03:54:00', 8, 'completado', 'Ajuste manual de cantidad: sumar 1 unidad'),
(46, 203, 3, NULL, 1, 'entrada', '2025-06-04 03:54:02', 8, 'completado', 'Ajuste manual de cantidad: sumar 1 unidad'),
(47, 203, 3, NULL, 1, 'salida', '2025-06-04 03:54:03', 8, 'completado', 'Ajuste manual de cantidad: restar 1 unidad'),
(48, 205, 3, 4, 6, 'transferencia', '2025-06-04 03:54:29', 8, 'completado', 'Transferencia aprobada desde solicitud #12'),
(49, 200, 3, NULL, 1, 'entrada', '2025-06-04 03:55:30', 8, 'completado', 'Ajuste manual de cantidad: sumar 1 unidad'),
(50, 200, 3, NULL, 1, 'entrada', '2025-06-04 03:55:30', 8, 'completado', 'Ajuste manual de cantidad: sumar 1 unidad'),
(51, 200, 3, NULL, 1, 'entrada', '2025-06-04 03:55:30', 8, 'completado', 'Ajuste manual de cantidad: sumar 1 unidad'),
(52, 200, 3, NULL, 1, 'entrada', '2025-06-04 03:55:30', 8, 'completado', 'Ajuste manual de cantidad: sumar 1 unidad'),
(53, 200, 3, NULL, 1, 'entrada', '2025-06-04 03:55:30', 8, 'completado', 'Ajuste manual de cantidad: sumar 1 unidad'),
(54, 200, 3, NULL, 1, 'entrada', '2025-06-04 03:55:30', 8, 'completado', 'Ajuste manual de cantidad: sumar 1 unidad'),
(55, 196, 3, 4, 3, 'transferencia', '2025-06-04 06:03:07', 8, 'completado', 'Transferencia aprobada desde solicitud #13');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `almacen_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `talla_dimensiones` varchar(50) DEFAULT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 0,
  `unidad_medida` varchar(50) DEFAULT NULL,
  `estado` enum('Nuevo','Usado','Dañado') NOT NULL,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `categoria_id`, `almacen_id`, `nombre`, `descripcion`, `modelo`, `color`, `talla_dimensiones`, `cantidad`, `unidad_medida`, `estado`, `observaciones`) VALUES
(191, 6, 3, 'Icom', NULL, 'IC-F3003', 'Negro', '', 5, 'unidad', 'Nuevo', 'EN CAJA'),
(196, 6, 3, 'Icom-sueltas', NULL, 'IC-F3003', 'Negro', '', 5, 'unidad', 'Nuevo', 'c/u con sus respectivos accesorios completos'),
(197, 6, 3, 'Talkabout', NULL, 'T200PE', 'Verde', '', 2, 'unidad', 'Nuevo', 'En caja'),
(198, 6, 3, 'Talkabout-Sueltas', NULL, 'T200PE', 'Verde', '', 3, 'unidad', 'Usado', ''),
(199, 6, 3, 'SPM', NULL, '1230S', 'Negro', '', 14, 'unidad', 'Nuevo', ''),
(200, 1, 3, 'Camisa Blanca', NULL, 'Comseproa SAC', 'Blanca', 'XL', 9, 'unidad', 'Nuevo', ''),
(201, 1, 3, 'Camisa Blanca', NULL, 'Comseproa SAC', 'Blanca', 'M', 1, 'unidad', 'Nuevo', ''),
(202, 1, 3, 'Camisa Blanca', NULL, 'Grupo Seal', 'Blanca', 'XXL', 1, 'unidad', 'Nuevo', ''),
(203, 1, 3, 'Chaleco', NULL, 'Grupo Seal', 'Plomo', 'L', 0, 'unidad', 'Nuevo', ''),
(204, 1, 3, 'Pantalon', NULL, '', 'Azul', '38', 0, 'unidad', 'Nuevo', ''),
(205, 1, 3, 'Cascos', NULL, 'Bellsafe', 'Blanco', '', 0, 'unidad', 'Nuevo', ''),
(206, 1, 3, 'Zapatos de Charol', NULL, '', 'negros', '42', 2, 'unidad', 'Nuevo', ''),
(207, 1, 3, 'Botas de seguridad', NULL, '', 'Negro', '43', 13, 'unidad', 'Nuevo', ''),
(208, 1, 3, 'Polera Manga', NULL, 'Larga', 'Plomo', 'M', 4, 'unidad', 'Nuevo', ''),
(209, 1, 3, 'Polera Manga', NULL, 'Larga', 'Plomo', 'L', 6, 'unidad', 'Nuevo', ''),
(210, 1, 3, 'Polera Manga', NULL, 'Larga', 'Plomo', 'XL', 6, 'unidad', 'Nuevo', ''),
(211, 1, 3, 'Chaleco', NULL, 'Grupo Seal', 'Azul', 'M', 2, 'unidad', 'Nuevo', ''),
(212, 1, 3, 'Chaleco', NULL, 'Grupo Seal', 'Azul', 'XL', 2, 'unidad', 'Nuevo', ''),
(213, 1, 3, 'Chaleco', NULL, 'Grupo Seal', 'Azul', 'L', 1, 'unidad', 'Nuevo', ''),
(214, 1, 4, 'Camisa Blanca', NULL, 'Comseproa SAC', 'Blanca', 'M', 3, 'unidad', 'Nuevo', ''),
(215, 1, 3, 'prueba', NULL, 'pruebita', 'negro', 'xl', 12, 'Par', 'Nuevo', 'preuba'),
(216, 1, 4, 'Camisa Blanca', NULL, 'Comseproa SAC', 'Blanca', 'XL', 10, 'unidad', 'Nuevo', ''),
(217, 1, 4, 'Chaleco', NULL, 'Grupo Seal', 'Azul', 'L', 9, 'unidad', 'Nuevo', ''),
(218, 4, 3, 'arma 1', NULL, 'm4a1', 'negra', '', 1, 'Unidad', 'Nuevo', 'preuba'),
(219, 1, 4, 'Cascos', NULL, 'Bellsafe', 'Blanco', '', 6, 'unidad', 'Nuevo', ''),
(220, 6, 4, 'Icom-sueltas', NULL, 'IC-F3003', 'Negro', '', 3, 'unidad', 'Nuevo', 'c/u con sus respectivos accesorios completos');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes_transferencia`
--

CREATE TABLE `solicitudes_transferencia` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `almacen_origen` int(11) NOT NULL,
  `almacen_destino` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado` enum('pendiente','aprobada','rechazada') DEFAULT 'pendiente',
  `usuario_aprobador_id` int(11) DEFAULT NULL,
  `fecha_procesamiento` timestamp NULL DEFAULT NULL,
  `procesado_por` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `solicitudes_transferencia`
--

INSERT INTO `solicitudes_transferencia` (`id`, `producto_id`, `almacen_origen`, `almacen_destino`, `cantidad`, `usuario_id`, `fecha_solicitud`, `estado`, `usuario_aprobador_id`, `fecha_procesamiento`, `procesado_por`, `observaciones`) VALUES
(1, 201, 3, 4, 2, 8, '2025-05-25 21:45:08', 'aprobada', 8, NULL, NULL, NULL),
(6, 200, 3, 4, 1, 1, '2025-06-01 19:45:05', 'aprobada', NULL, '2025-06-01 19:45:05', 1, 'Transferencia solicitada desde el sistema web'),
(7, 207, 3, 4, 3, 8, '2025-06-01 20:04:51', 'rechazada', 8, '2025-06-04 05:31:28', NULL, 'Transferencia solicitada desde el sistema web'),
(8, 200, 3, 4, 3, 8, '2025-06-01 20:05:00', 'rechazada', 8, '2025-06-04 05:31:02', NULL, 'Transferencia solicitada desde el sistema web'),
(9, 200, 3, 4, 9, 8, '2025-06-01 20:25:48', 'aprobada', 8, '2025-06-03 03:18:44', NULL, 'Transferencia solicitada desde el sistema web'),
(10, 213, 3, 4, 9, 8, '2025-06-03 08:07:18', 'aprobada', 8, '2025-06-03 08:07:29', NULL, 'Transferencia solicitada desde el sistema web'),
(11, 200, 3, 4, 1, 8, '2025-06-03 17:23:37', 'aprobada', 8, '2025-06-03 17:24:55', NULL, 'Transferencia solicitada desde el sistema web'),
(12, 205, 3, 4, 6, 8, '2025-06-04 03:54:12', 'aprobada', 8, '2025-06-04 03:54:29', NULL, 'Transferencia solicitada desde el sistema web'),
(13, 196, 3, 4, 3, 8, '2025-06-04 06:02:55', 'aprobada', 8, '2025-06-04 06:03:07', NULL, 'Transferencia solicitada desde el sistema web');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(64) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `dni` varchar(8) NOT NULL,
  `celular` varchar(15) NOT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `correo` varchar(100) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `almacen_id` int(11) DEFAULT NULL,
  `rol` enum('admin','almacenero') NOT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `apellidos`, `dni`, `celular`, `direccion`, `correo`, `contrasena`, `almacen_id`, `rol`, `estado`, `fecha_registro`) VALUES
(8, 'Jhamir Alexander', 'Silva Baldera', '71749437', '982566142', 'San Julian 664 - Motupe', 'jhamirsilva@gmail.com', '$2y$10$yG9ldNFttY94fCt/FZXx/OTuaBGPmD/rkvniTmpYFa9ZPotDIRfZ.', 3, 'admin', 'activo', '2025-03-24 13:38:41'),
(10, 'prueba', 'olmos', '76787652', '987654321', 'omos', 'almaceneroolmos@gmail.com', '$2y$10$8h7JB5WqwWiUupnV27y1f.9EmL2nYxHsa3L8dNkYuwDJ6YprEOopm', 4, 'almacenero', 'activo', '2025-04-07 02:36:31');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `almacenes`
--
ALTER TABLE `almacenes`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `entrega_uniformes`
--
ALTER TABLE `entrega_uniformes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_responsable_id` (`usuario_responsable_id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `almacen_id` (`almacen_id`);

--
-- Indices de la tabla `logs_actividad`
--
ALTER TABLE `logs_actividad`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `accion` (`accion`),
  ADD KEY `fecha_accion` (`fecha_accion`);

--
-- Indices de la tabla `movimientos`
--
ALTER TABLE `movimientos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `almacen_origen` (`almacen_origen`),
  ADD KEY `almacen_destino` (`almacen_destino`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_producto_almacen` (`nombre`,`color`,`talla_dimensiones`,`almacen_id`),
  ADD KEY `categoria_id` (`categoria_id`),
  ADD KEY `almacen_id` (`almacen_id`);

--
-- Indices de la tabla `solicitudes_transferencia`
--
ALTER TABLE `solicitudes_transferencia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `almacen_origen` (`almacen_origen`),
  ADD KEY `almacen_destino` (`almacen_destino`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `fk_usuario_aprobador` (`usuario_aprobador_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dni` (`dni`),
  ADD UNIQUE KEY `correo` (`correo`),
  ADD KEY `almacen_id` (`almacen_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `almacenes`
--
ALTER TABLE `almacenes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `entrega_uniformes`
--
ALTER TABLE `entrega_uniformes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `logs_actividad`
--
ALTER TABLE `logs_actividad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT de la tabla `movimientos`
--
ALTER TABLE `movimientos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=221;

--
-- AUTO_INCREMENT de la tabla `solicitudes_transferencia`
--
ALTER TABLE `solicitudes_transferencia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `entrega_uniformes`
--
ALTER TABLE `entrega_uniformes`
  ADD CONSTRAINT `entrega_uniformes_ibfk_1` FOREIGN KEY (`usuario_responsable_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `entrega_uniformes_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `entrega_uniformes_ibfk_3` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `movimientos`
--
ALTER TABLE `movimientos`
  ADD CONSTRAINT `movimientos_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `movimientos_ibfk_2` FOREIGN KEY (`almacen_origen`) REFERENCES `almacenes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `movimientos_ibfk_3` FOREIGN KEY (`almacen_destino`) REFERENCES `almacenes` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `productos_ibfk_2` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `solicitudes_transferencia`
--
ALTER TABLE `solicitudes_transferencia`
  ADD CONSTRAINT `fk_usuario_aprobador` FOREIGN KEY (`usuario_aprobador_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `solicitudes_transferencia_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `solicitudes_transferencia_ibfk_2` FOREIGN KEY (`almacen_origen`) REFERENCES `almacenes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `solicitudes_transferencia_ibfk_3` FOREIGN KEY (`almacen_destino`) REFERENCES `almacenes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
