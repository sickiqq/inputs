@echo off
echo Creando base de datos con tablas data1 y data2...
"C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe" -u root -proot < crear_db_completa.sql
echo Base de datos creada correctamente!
pause