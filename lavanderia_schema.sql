-- ============================================================
-- BASE DE DATOS: GESTIÓN INTEGRAL DE LAVANDERÍA
-- TFG - Sistema de gestión de lavandería
-- ============================================================

CREATE DATABASE IF NOT EXISTS lavanderia
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE lavanderia;

-- ============================================================
-- TABLA: roles
-- Define los tipos de usuario del sistema
-- ============================================================
CREATE TABLE roles (
    id         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    nombre     VARCHAR(50)      NOT NULL UNIQUE,
    descripcion VARCHAR(200)    NULL,
    PRIMARY KEY (id)
);

INSERT INTO roles (nombre, descripcion) VALUES
    ('ADMIN',    'Acceso completo al sistema'),
    ('EMPLEADO', 'Gestión diaria de pedidos y clientes');


-- ============================================================
-- TABLA: usuarios
-- Personal autorizado para acceder a la aplicación
-- ============================================================
CREATE TABLE usuarios (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    nombre          VARCHAR(100)     NOT NULL,
    apellidos       VARCHAR(150)     NOT NULL,
    email           VARCHAR(255)     NOT NULL UNIQUE,
    password_hash   VARCHAR(255)     NOT NULL,          -- BCrypt hash
    rol_id          INT UNSIGNED     NOT NULL,
    activo          BOOLEAN          NOT NULL DEFAULT TRUE,
    ultimo_login    DATETIME         NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id)
        REFERENCES roles(id) ON UPDATE CASCADE ON DELETE RESTRICT
);

-- Usuario administrador por defecto (password: Admin1234!)
INSERT INTO usuarios (nombre, apellidos, email, password_hash, rol_id) VALUES
    ('Admin', 'Sistema', 'admin@lavanderia.com',
     '$2a$12$examplehashplaceholder1234567890abcdefgh', 1);


-- ============================================================
-- TABLA: clientes
-- Personas que usan los servicios de la lavandería
-- ============================================================
CREATE TABLE clientes (
    id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    nombre      VARCHAR(100)     NOT NULL,
    apellidos   VARCHAR(150)     NOT NULL,
    telefono    VARCHAR(20)      NULL,
    email       VARCHAR(255)     NULL UNIQUE,
    direccion   VARCHAR(300)     NULL,
    notas       TEXT             NULL,
    activo      BOOLEAN          NOT NULL DEFAULT TRUE,
    created_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                           ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_clientes_email   (email),
    INDEX idx_clientes_nombre  (nombre, apellidos)
);


-- ============================================================
-- TABLA: estados_pedido
-- Catálogo de posibles estados de un pedido
-- ============================================================
CREATE TABLE estados_pedido (
    id      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre  VARCHAR(50)     NOT NULL UNIQUE,
    orden   TINYINT UNSIGNED NOT NULL,   -- para ordenar el flujo
    PRIMARY KEY (id)
);

INSERT INTO estados_pedido (nombre, orden) VALUES
    ('PENDIENTE',   1),
    ('EN_PROCESO',  2),
    ('FINALIZADO',  3),
    ('ENTREGADO',   4);


-- ============================================================
-- TABLA: pedidos
-- Servicio contratado por un cliente
-- ============================================================
CREATE TABLE pedidos (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    cliente_id      INT UNSIGNED     NOT NULL,
    empleado_id     INT UNSIGNED     NOT NULL,     -- quien registra el pedido
    estado_id       INT UNSIGNED     NOT NULL DEFAULT 1,
    fecha_entrada   DATE             NOT NULL DEFAULT (CURRENT_DATE),
    fecha_estimada  DATE             NULL,
    fecha_entrega   DATE             NULL,
    observaciones   TEXT             NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_pedidos_cliente  FOREIGN KEY (cliente_id)
        REFERENCES clientes(id)       ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_pedidos_empleado FOREIGN KEY (empleado_id)
        REFERENCES usuarios(id)       ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_pedidos_estado   FOREIGN KEY (estado_id)
        REFERENCES estados_pedido(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_pedidos_cliente (cliente_id),
    INDEX idx_pedidos_estado  (estado_id),
    INDEX idx_pedidos_fecha   (fecha_entrada)
);


-- ============================================================
-- TABLA: tipos_ropa
-- Catálogo de tipos de ropa aceptados
-- ============================================================
CREATE TABLE tipos_ropa (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre          VARCHAR(80)     NOT NULL UNIQUE,  -- Blanca, Color, Delicada...
    precio_kg       DECIMAL(6,2)    NOT NULL,         -- tarifa base por kg
    descripcion     VARCHAR(255)    NULL,
    PRIMARY KEY (id)
);

INSERT INTO tipos_ropa (nombre, precio_kg, descripcion) VALUES
    ('Blanca',    1.50, 'Ropa blanca estándar'),
    ('Color',     1.50, 'Ropa de color estándar'),
    ('Delicada',  2.50, 'Tejidos delicados, seda, lana fina'),
    ('Ropa hogar',1.80, 'Sábanas, toallas, mantelerías'),
    ('Especial',  3.00, 'Prendas con tratamiento específico');


-- ============================================================
-- TABLA: sacos
-- Cada saco de ropa asociado a un pedido
-- ============================================================
CREATE TABLE sacos (
    id           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    pedido_id    INT UNSIGNED     NOT NULL,
    tipo_ropa_id INT UNSIGNED     NOT NULL,
    peso_kg      DECIMAL(6,2)     NOT NULL CHECK (peso_kg > 0),
    precio_kg    DECIMAL(6,2)     NOT NULL,  -- precio vigente al crear el saco
    subtotal     DECIMAL(8,2)     GENERATED ALWAYS AS (peso_kg * precio_kg) STORED,
    observaciones VARCHAR(300)    NULL,
    created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_sacos_pedido    FOREIGN KEY (pedido_id)
        REFERENCES pedidos(id)    ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_sacos_tipo_ropa FOREIGN KEY (tipo_ropa_id)
        REFERENCES tipos_ropa(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_sacos_pedido (pedido_id)
);


-- ============================================================
-- TABLA: facturas
-- Factura generada automáticamente por cada pedido
-- ============================================================
CREATE TABLE facturas (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    pedido_id       INT UNSIGNED     NOT NULL UNIQUE,
    numero_factura  VARCHAR(20)      NOT NULL UNIQUE,  -- ej: FAC-2024-00001
    fecha_emision   DATE             NOT NULL DEFAULT (CURRENT_DATE),
    base_imponible  DECIMAL(10,2)    NOT NULL,
    iva_porcentaje  DECIMAL(4,2)     NOT NULL DEFAULT 21.00,
    iva_importe     DECIMAL(10,2)    NOT NULL,
    total           DECIMAL(10,2)    NOT NULL,
    pagada          BOOLEAN          NOT NULL DEFAULT FALSE,
    fecha_pago      DATE             NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_facturas_pedido FOREIGN KEY (pedido_id)
        REFERENCES pedidos(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_facturas_numero (numero_factura),
    INDEX idx_facturas_fecha  (fecha_emision)
);


-- ============================================================
-- TABLA: categorias_producto
-- Agrupación de productos del almacén
-- ============================================================
CREATE TABLE categorias_producto (
    id      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre  VARCHAR(80)     NOT NULL UNIQUE,
    PRIMARY KEY (id)
);

INSERT INTO categorias_producto (nombre) VALUES
    ('Detergentes'),
    ('Suavizantes'),
    ('Quitamanchas'),
    ('Bolsas y embalajes'),
    ('Otros');


-- ============================================================
-- TABLA: productos
-- Stock de productos de la lavandería
-- ============================================================
CREATE TABLE productos (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    categoria_id    INT UNSIGNED     NOT NULL,
    nombre          VARCHAR(150)     NOT NULL,
    referencia      VARCHAR(50)      NULL UNIQUE,
    descripcion     TEXT             NULL,
    stock_actual    INT UNSIGNED     NOT NULL DEFAULT 0,
    stock_minimo    INT UNSIGNED     NOT NULL DEFAULT 5,  -- alerta de reposición
    precio_unidad   DECIMAL(8,2)     NOT NULL,
    activo          BOOLEAN          NOT NULL DEFAULT TRUE,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_productos_categoria FOREIGN KEY (categoria_id)
        REFERENCES categorias_producto(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_productos_categoria (categoria_id)
);


-- ============================================================
-- TABLA: movimientos_stock
-- Historial de entradas y salidas de productos
-- ============================================================
CREATE TABLE movimientos_stock (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    producto_id     INT UNSIGNED     NOT NULL,
    usuario_id      INT UNSIGNED     NOT NULL,
    tipo            ENUM('ENTRADA','SALIDA') NOT NULL,
    cantidad        INT              NOT NULL,
    stock_resultante INT UNSIGNED   NOT NULL,
    motivo          VARCHAR(255)     NULL,
    fecha           DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_mov_producto FOREIGN KEY (producto_id)
        REFERENCES productos(id)  ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_mov_usuario  FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)   ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_mov_producto (producto_id),
    INDEX idx_mov_fecha    (fecha)
);


-- ============================================================
-- TABLA: sesiones
-- Control de sesiones activas (para seguridad)
-- ============================================================
CREATE TABLE sesiones (
    id          VARCHAR(128)     NOT NULL,  -- UUID del token de sesión
    usuario_id  INT UNSIGNED     NOT NULL,
    ip_address  VARCHAR(45)      NULL,
    user_agent  VARCHAR(500)     NULL,
    expires_at  DATETIME         NOT NULL,
    created_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_sesiones_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_sesiones_usuario  (usuario_id),
    INDEX idx_sesiones_expiry   (expires_at)
);


-- ============================================================
-- VISTAS ÚTILES
-- ============================================================

-- Vista: resumen de pedidos con datos del cliente y estado
CREATE OR REPLACE VIEW v_pedidos_resumen AS
SELECT
    p.id                                    AS pedido_id,
    p.fecha_entrada,
    p.fecha_estimada,
    p.fecha_entrega,
    CONCAT(c.nombre, ' ', c.apellidos)      AS cliente,
    c.telefono                              AS telefono_cliente,
    ep.nombre                               AS estado,
    CONCAT(u.nombre, ' ', u.apellidos)      AS empleado,
    COUNT(s.id)                             AS num_sacos,
    COALESCE(SUM(s.peso_kg), 0)             AS peso_total_kg,
    COALESCE(SUM(s.subtotal), 0)            AS importe_bruto
FROM pedidos p
    JOIN clientes       c  ON p.cliente_id  = c.id
    JOIN estados_pedido ep ON p.estado_id   = ep.id
    JOIN usuarios       u  ON p.empleado_id = u.id
    LEFT JOIN sacos     s  ON p.id          = s.pedido_id
GROUP BY p.id, c.id, ep.id, u.id;


-- Vista: productos con stock bajo el mínimo
CREATE OR REPLACE VIEW v_productos_stock_bajo AS
SELECT
    p.id,
    cp.nombre   AS categoria,
    p.nombre    AS producto,
    p.referencia,
    p.stock_actual,
    p.stock_minimo,
    (p.stock_minimo - p.stock_actual) AS unidades_a_reponer
FROM productos p
    JOIN categorias_producto cp ON p.categoria_id = cp.id
WHERE p.activo = TRUE
  AND p.stock_actual < p.stock_minimo
ORDER BY unidades_a_reponer DESC;


-- ============================================================
-- PROCEDIMIENTO: generar número de factura correlativo
-- ============================================================
DELIMITER $$

CREATE PROCEDURE sp_generar_factura(IN p_pedido_id INT UNSIGNED)
BEGIN
    DECLARE v_base   DECIMAL(10,2);
    DECLARE v_iva    DECIMAL(10,2);
    DECLARE v_total  DECIMAL(10,2);
    DECLARE v_numero VARCHAR(20);
    DECLARE v_year   INT;
    DECLARE v_seq    INT;

    -- Calcular base imponible sumando subtotales de sacos
    SELECT COALESCE(SUM(subtotal), 0) INTO v_base
    FROM sacos WHERE pedido_id = p_pedido_id;

    SET v_iva   = ROUND(v_base * 0.21, 2);
    SET v_total = v_base + v_iva;
    SET v_year  = YEAR(CURRENT_DATE);

    -- Siguiente número de secuencia del año
    SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(numero_factura, '-', -1) AS UNSIGNED)), 0) + 1
    INTO v_seq
    FROM facturas
    WHERE numero_factura LIKE CONCAT('FAC-', v_year, '-%');

    SET v_numero = CONCAT('FAC-', v_year, '-', LPAD(v_seq, 5, '0'));

    INSERT INTO facturas (pedido_id, numero_factura, base_imponible, iva_importe, total)
    VALUES (p_pedido_id, v_numero, v_base, v_iva, v_total);
END$$

DELIMITER ;
