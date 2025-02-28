-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS inputs_db;
-- Usar la base de datos creada
USE inputs_db;
-- Eliminar tablas si existen
DROP TABLE IF EXISTS data1;
DROP TABLE IF EXISTS data2;
DROP TABLE IF EXISTS employee_events;
DROP TABLE IF EXISTS event_types;
-- Crear tabla data1
CREATE TABLE IF NOT EXISTS data1 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identificador VARCHAR(15) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    contrato VARCHAR(50) NOT NULL,
    fecha_entrada DATETIME,
    fecha_salida DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    manual_entry TINYINT(1) DEFAULT 0 -- Nueva columna para identificar entradas manuales
);
-- Crear tabla data2
CREATE TABLE IF NOT EXISTS data2 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identificador VARCHAR(15) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    contrato VARCHAR(50) NOT NULL,
    fecha_entrada DATETIME,
    fecha_salida DATETIME,
    ubicacion VARCHAR(50) NOT NULL,
    punto_control VARCHAR(50),
    rut_empresa VARCHAR(20) NOT NULL,
    tipo_personal VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    manual_entry TINYINT(1) DEFAULT 0 -- Nueva columna para identificar entradas manuales
);
-- Crear tabla employee_events
CREATE TABLE IF NOT EXISTS employee_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identificador VARCHAR(15) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    fecha DATETIME NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Crear tabla event_types
CREATE TABLE IF NOT EXISTS event_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    color_html VARCHAR(7) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insertar los tipos de eventos y sus colores correspondientes
INSERT INTO event_types (nombre, color_html) VALUES
('Ni idea que pasa', '#FF0000'),
('Cambio de turno', '#FFA500'),
('Licencia medica', '#FFFF00'),
('Trabajador con asistencia en Talana', '#008000'),
('Trabajador con asistencia en Planificación', '#0000FF'),
('Permiso con o sin goce o falla', '#4B0082'),
('Teletrabajo', '#EE82EE'),
('Vacaciones, nacimiento, sindical', '#A52A2A'),
('Finiquito', '#000000'),
('Cambio Faena', '#808080');