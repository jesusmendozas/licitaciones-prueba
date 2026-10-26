-- Estructura de la base de licitaciones, con las columnas que pide el enunciado.
-- Fecha y hora quedan separadas, y el documento apunta a la oferta con licitacion_id.
-- No hay tabla pivote: una oferta tiene una actividad y un documento pertenece a una oferta.
-- Después de este script, cargar las actividades con: php bd/load_activities.php

CREATE DATABASE IF NOT EXISTS licitaciones
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE licitaciones;

SET NAMES utf8mb4;

DROP TABLE IF EXISTS ofertas_documentos;
DROP TABLE IF EXISTS ofertas;
DROP TABLE IF EXISTS actividades;

-- Catálogo UNSPSC. codigo_producto identifica el bien o servicio que elige la oferta.
CREATE TABLE actividades (
  id INT NOT NULL AUTO_INCREMENT,
  codigo_segmento INT NOT NULL,
  segmento VARCHAR(200) NOT NULL,
  codigo_familia INT NOT NULL,
  familia VARCHAR(200) NOT NULL,
  codigo_clase INT NOT NULL,
  clase VARCHAR(200) NOT NULL,
  codigo_producto INT NOT NULL,
  producto VARCHAR(200) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_actividades_codigo_producto (codigo_producto),
  KEY idx_actividades_producto (producto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Una licitación. El consecutivo es único y el estado lo calcula el servidor.
CREATE TABLE ofertas (
  id INT NOT NULL AUTO_INCREMENT,
  consecutivo VARCHAR(20) NOT NULL,
  objeto VARCHAR(150) NOT NULL,
  descripcion VARCHAR(400) NOT NULL,
  moneda VARCHAR(3) NOT NULL,
  presupuesto DECIMAL(15,2) NOT NULL,
  actividad_id INT NOT NULL,
  fecha_inicio DATE NOT NULL,
  hora_inicio TIME NOT NULL,
  fecha_cierre DATE NOT NULL,
  hora_cierre TIME NOT NULL,
  estado VARCHAR(20) NOT NULL,
  creado_en DATETIME NOT NULL,
  actualizado_en DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_ofertas_consecutivo (consecutivo),
  KEY idx_ofertas_actividad (actividad_id),
  KEY idx_ofertas_inicio (fecha_inicio, hora_inicio),
  KEY idx_ofertas_cierre (fecha_cierre, hora_cierre),
  KEY idx_ofertas_listado (creado_en, id),
  CONSTRAINT fk_ofertas_actividad
    FOREIGN KEY (actividad_id) REFERENCES actividades (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Documentos de la oferta. licitacion_id apunta a ofertas.id.
CREATE TABLE ofertas_documentos (
  id INT NOT NULL AUTO_INCREMENT,
  licitacion_id INT NOT NULL,
  titulo VARCHAR(100) NOT NULL,
  descripcion VARCHAR(200) NOT NULL,
  archivo VARCHAR(400) NOT NULL,
  creado_en DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_documentos_licitacion (licitacion_id),
  CONSTRAINT fk_documentos_oferta
    FOREIGN KEY (licitacion_id) REFERENCES ofertas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
