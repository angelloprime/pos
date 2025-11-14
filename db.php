<?php
/*
 * --- Archivo de Conexión: db.php ---
 */

$servername = "181.64.136.100";        // O 127.0.0.1
$username = "angello_user";             // Tu usuario de MySQL
$password = "NemesisPrime123@";    // Tu contraseña de MySQL
$database = "sistema_agente";   // La base de datos que importamos

// Crear la conexión usando MySQLi
$conn = new mysqli($servername, $username, $password, $database);

// Verificar si la conexión falló
if ($conn->connect_error) {
    die("Error de Conexión: " . $conn->connect_error);
}

// Opcional: Asegurar que la conexión use UTF-8 (para tildes y ñ)
$conn->set_charset("utf8mb4");

/*
 * Si llegas hasta aquí, la conexión fue exitosa.
 * No pongas un "echo" aquí, solo incluye este archivo
 * en las páginas que necesiten la base de datos.
 */
?>
