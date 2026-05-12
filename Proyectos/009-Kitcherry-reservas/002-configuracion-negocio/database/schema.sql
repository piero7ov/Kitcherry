-- ==========================================================
-- KITCHERRY RESERVAS
-- Archivo: 001-panel-sencillo/database/schema.sql
-- Base de datos inicial para SQLite
-- ==========================================================

PRAGMA foreign_keys = ON;

-- ==========================================================
-- TABLA: usuarios
-- Control de acceso al panel
-- ==========================================================

CREATE TABLE IF NOT EXISTS usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    rol TEXT NOT NULL DEFAULT 'admin',
    activo INTEGER NOT NULL DEFAULT 1,
    creado_en TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TEXT
);

-- ==========================================================
-- TABLA: negocios
-- Datos generales del restaurante, bar o cafetería
-- ==========================================================

CREATE TABLE IF NOT EXISTS negocios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    tipo_negocio TEXT,
    tipo_cocina TEXT,
    telefono TEXT,
    email TEXT,
    direccion TEXT,
    ciudad TEXT,
    capacidad_maxima INTEGER DEFAULT 0,
    tono_comunicacion TEXT DEFAULT 'cercano',
    activo INTEGER NOT NULL DEFAULT 1,
    creado_en TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TEXT
);

-- ==========================================================
-- TABLA: configuracion_reservas
-- Configuración interna para organizar reservas
-- ==========================================================

CREATE TABLE IF NOT EXISTS configuracion_reservas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    negocio_id INTEGER NOT NULL,

    hora_comida_inicio TEXT,
    hora_comida_fin TEXT,
    hora_cena_inicio TEXT,
    hora_cena_fin TEXT,

    duracion_media_reserva INTEGER DEFAULT 90,
    margen_limpieza_mesa INTEGER DEFAULT 15,
    tamano_grupo_grande INTEGER DEFAULT 6,

    trabaja_por_turnos INTEGER NOT NULL DEFAULT 1,
    permite_terraza INTEGER NOT NULL DEFAULT 0,

    creado_en TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TEXT,

    FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE
);

-- ==========================================================
-- TABLA: mesas
-- Mesas del negocio
-- ==========================================================

CREATE TABLE IF NOT EXISTS mesas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    negocio_id INTEGER NOT NULL,

    nombre TEXT NOT NULL,
    capacidad INTEGER NOT NULL,
    zona TEXT DEFAULT 'Interior',
    activa INTEGER NOT NULL DEFAULT 1,
    orden INTEGER DEFAULT 0,

    creado_en TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TEXT,

    FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE
);

-- ==========================================================
-- TABLA: clientes
-- Clientes vinculados a reservas
-- ==========================================================

CREATE TABLE IF NOT EXISTS clientes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    nombre TEXT NOT NULL,
    telefono TEXT,
    email TEXT,

    preferencias TEXT,
    alergias TEXT,
    notas_internas TEXT,

    estado_cliente TEXT NOT NULL DEFAULT 'nuevo',
    archivado INTEGER NOT NULL DEFAULT 0,

    creado_en TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TEXT,
    ultima_reserva_en TEXT,

    CHECK (
        estado_cliente IN (
            'nuevo',
            'recurrente',
            'habitual',
            'destacado',
            'inactivo',
            'riesgo'
        )
    )
);

-- ==========================================================
-- TABLA: reservas
-- Núcleo principal del sistema
-- ==========================================================

CREATE TABLE IF NOT EXISTS reservas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    negocio_id INTEGER NOT NULL,
    cliente_id INTEGER,
    mesa_id INTEGER,

    fecha TEXT NOT NULL,
    hora TEXT NOT NULL,
    turno TEXT,

    personas INTEGER NOT NULL,
    estado TEXT NOT NULL DEFAULT 'pendiente',

    origen TEXT DEFAULT 'manual',

    observaciones TEXT,
    alergias TEXT,
    preferencias TEXT,

    confirmacion_enviada INTEGER NOT NULL DEFAULT 0,
    recordatorio_enviado INTEGER NOT NULL DEFAULT 0,

    creado_en TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TEXT,

    FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
    FOREIGN KEY (mesa_id) REFERENCES mesas(id) ON DELETE SET NULL,

    CHECK (
        estado IN (
            'pendiente',
            'confirmada',
            'modificada',
            'cancelada',
            'no_presentada',
            'completada'
        )
    )
);

-- ==========================================================
-- TABLA: historial_reservas
-- Registro de acciones importantes
-- ==========================================================

CREATE TABLE IF NOT EXISTS historial_reservas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    reserva_id INTEGER NOT NULL,
    usuario_id INTEGER,

    accion TEXT NOT NULL,
    descripcion TEXT,

    creado_en TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (reserva_id) REFERENCES reservas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- ==========================================================
-- TABLA: mensajes_reserva
-- Confirmaciones y recordatorios enviados o preparados
-- ==========================================================

CREATE TABLE IF NOT EXISTS mensajes_reserva (
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    reserva_id INTEGER NOT NULL,
    cliente_id INTEGER,

    tipo TEXT NOT NULL,
    canal TEXT NOT NULL DEFAULT 'email',

    asunto TEXT,
    cuerpo TEXT NOT NULL,

    tono TEXT,
    estado_envio TEXT NOT NULL DEFAULT 'pendiente',

    creado_en TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    enviado_en TEXT,

    FOREIGN KEY (reserva_id) REFERENCES reservas(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,

    CHECK (
        tipo IN (
            'confirmacion',
            'recordatorio',
            'modificacion',
            'cancelacion'
        )
    ),

    CHECK (
        estado_envio IN (
            'pendiente',
            'enviado',
            'error'
        )
    )
);

-- ==========================================================
-- TABLA: alertas_reserva
-- Alertas internas calculadas o guardadas
-- ==========================================================

CREATE TABLE IF NOT EXISTS alertas_reserva (
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    reserva_id INTEGER,
    cliente_id INTEGER,

    tipo TEXT NOT NULL,
    nivel TEXT NOT NULL DEFAULT 'info',
    mensaje TEXT NOT NULL,

    resuelta INTEGER NOT NULL DEFAULT 0,

    creado_en TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resuelta_en TEXT,

    FOREIGN KEY (reserva_id) REFERENCES reservas(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,

    CHECK (
        nivel IN (
            'info',
            'aviso',
            'riesgo',
            'critico'
        )
    )
);

-- ==========================================================
-- ÍNDICES
-- Mejoran búsquedas habituales del panel
-- ==========================================================

CREATE INDEX IF NOT EXISTS idx_reservas_fecha
ON reservas(fecha);

CREATE INDEX IF NOT EXISTS idx_reservas_estado
ON reservas(estado);

CREATE INDEX IF NOT EXISTS idx_reservas_cliente
ON reservas(cliente_id);

CREATE INDEX IF NOT EXISTS idx_reservas_mesa
ON reservas(mesa_id);

CREATE INDEX IF NOT EXISTS idx_clientes_email
ON clientes(email);

CREATE INDEX IF NOT EXISTS idx_clientes_telefono
ON clientes(telefono);

CREATE INDEX IF NOT EXISTS idx_alertas_resuelta
ON alertas_reserva(resuelta);

-- ==========================================================
-- MIGRACIÓN 002 - CONFIGURACIÓN DE HORARIO
-- Ejecutar solo una vez si la base de datos ya estaba creada
-- Añade soporte para horario por turnos o servicio continuo
-- ==========================================================

ALTER TABLE configuracion_reservas 
ADD COLUMN tipo_horario TEXT DEFAULT 'turnos';

ALTER TABLE configuracion_reservas 
ADD COLUMN hora_apertura TEXT;

ALTER TABLE configuracion_reservas 
ADD COLUMN hora_cierre TEXT;

-- ==========================================================
-- TABLA: horarios_negocio
-- Horario semanal del negocio
-- Una fila por cada día de la semana
-- ==========================================================

CREATE TABLE IF NOT EXISTS horarios_negocio (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    negocio_id INTEGER NOT NULL,

    dia_semana TEXT NOT NULL,
    cerrado INTEGER NOT NULL DEFAULT 0,

    hora_apertura TEXT,
    hora_cierre TEXT,

    creado_en TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TEXT,

    FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,

    CHECK (
        dia_semana IN (
            'lunes',
            'martes',
            'miercoles',
            'jueves',
            'viernes',
            'sabado',
            'domingo'
        )
    )
);