-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 10-04-2025 a las 13:54:37
-- Versión del servidor: 10.11.10-MariaDB-log
-- Versión de PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `u797525844_comseproa_db`
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
  `tipo` enum('entrada','salida','transferencia') NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario_id` int(11) NOT NULL,
  `estado` enum('pendiente','completado','rechazado') DEFAULT 'pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `movimientos`
--

INSERT INTO `movimientos` (`id`, `producto_id`, `almacen_origen`, `almacen_destino`, `cantidad`, `tipo`, `fecha`, `usuario_id`, `estado`) VALUES
(1, 196, 3, NULL, 1, 'entrada', '2025-04-09 17:23:42', 8, 'completado'),
(2, 205, 3, NULL, 1, 'entrada', '2025-04-09 20:51:22', 8, 'completado'),
(3, 205, 3, NULL, 1, 'entrada', '2025-04-09 20:51:23', 8, 'completado'),
(4, 205, 3, NULL, 1, 'entrada', '2025-04-09 20:51:23', 8, 'completado'),
(5, 205, 3, NULL, 1, 'entrada', '2025-04-09 20:51:23', 8, 'completado'),
(6, 205, 3, NULL, 1, 'entrada', '2025-04-09 20:51:24', 8, 'completado'),
(7, 205, 3, NULL, 1, 'entrada', '2025-04-09 20:51:25', 8, 'completado');

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
(196, 6, 3, 'Icom-sueltas', NULL, 'IC-F3003', 'Negro', '', 9, 'unidad', 'Nuevo', 'c/u con sus respectivos accesorios completos'),
(197, 6, 3, 'Talkabout', NULL, 'T200PE', 'Verde', '', 2, 'unidad', 'Nuevo', 'En caja'),
(198, 6, 3, 'Talkabout-Sueltas', NULL, 'T200PE', 'Verde', '', 3, 'unidad', 'Usado', ''),
(199, 6, 3, 'SPM', NULL, '1230S', 'Negro', '', 14, 'unidad', 'Nuevo', ''),
(200, 1, 3, 'Camisa Blanca', NULL, 'Comseproa SAC', 'Blanca', 'XL', 1, 'unidad', 'Nuevo', ''),
(201, 1, 3, 'Camisa Blanca', NULL, 'Comseproa SAC', 'Blanca', 'M', 4, 'unidad', 'Nuevo', ''),
(202, 1, 3, 'Camisa Blanca', NULL, 'Grupo Seal', 'Blanca', 'XXL', 1, 'unidad', 'Nuevo', ''),
(203, 1, 3, 'Chaleco', NULL, 'Grupo Seal', 'Plomo', 'L', 1, 'unidad', 'Nuevo', ''),
(204, 1, 3, 'Pantalon', NULL, '', 'Azul', '38', 2, 'unidad', 'Nuevo', ''),
(205, 1, 3, 'Cascos', NULL, 'Bellsafe', 'Blanco', '', 8, 'unidad', 'Nuevo', ''),
(206, 1, 3, 'Zapatos de Charol', NULL, '', 'negros', '42', 2, 'unidad', 'Nuevo', ''),
(207, 1, 3, 'Botas de seguridad', NULL, '', 'Negro', '43', 1, 'unidad', 'Nuevo', ''),
(208, 1, 3, 'Polera Manga', NULL, 'Larga', 'Plomo', 'M', 6, 'unidad', 'Nuevo', ''),
(209, 1, 3, 'Polera Manga', NULL, 'Larga', 'Plomo', 'L', 6, 'unidad', 'Nuevo', ''),
(210, 1, 3, 'Polera Manga', NULL, 'Larga', 'Plomo', 'XL', 6, 'unidad', 'Nuevo', ''),
(211, 1, 3, 'Chaleco', NULL, 'Grupo Seal', 'Azul', 'M', 4, 'unidad', 'Nuevo', ''),
(212, 1, 3, 'Chaleco', NULL, 'Grupo Seal', 'Azul', 'XL', 4, 'unidad', 'Nuevo', ''),
(213, 1, 3, 'Chaleco', NULL, 'Grupo Seal', 'Azul', 'L', 4, 'unidad', 'Nuevo', '');

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
  `usuario_aprobador_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(9, 'prueba', 'chicalyo', '12341234', '987654321', 'Calle San Julian - 664', 'almacenerocix@gmail.com', '$2y$10$j1xXEGliZcoQemnMt7BWkO5SCmSoXaK6A3XvqmuxSUgeah4fNO23.', 3, 'almacenero', 'activo', '2025-03-27 21:41:40'),
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `movimientos`
--
ALTER TABLE `movimientos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=214;

--
-- AUTO_INCREMENT de la tabla `solicitudes_transferencia`
--
ALTER TABLE `solicitudes_transferencia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  ADD CONSTRAINT `movimientos_ibfk_3` FOREIGN KEY (`almacen_destino`) REFERENCES `almacenes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `movimientos_ibfk_4` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

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
  ADD CONSTRAINT `solicitudes_transferencia_ibfk_3` FOREIGN KEY (`almacen_destino`) REFERENCES `almacenes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `solicitudes_transferencia_ibfk_4` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
