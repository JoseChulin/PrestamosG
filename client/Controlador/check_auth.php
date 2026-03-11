<?php
session_start();
header('Content-Type: application/json');

// Verificar si la sesión está activa y el usuario autenticado
if (!isset($_SESSION['idUsuario']) || empty($_SESSION['idUsuario'])) {
    http_response_code(401);
    echo json_encode([
        'authenticated' => false, 
        'error' => 'No hay sesión activa',
        'session_debug' => $_SESSION 
    ]);
    exit;
}

// Si todo está bien
echo json_encode([
    'authenticated' => true,
    'user_id' => $_SESSION['idUsuario'],
    'user_name' => $_SESSION['user_name'] ?? '',
    'user_email' => $_SESSION['user_email'] ?? ''
]);
?>
