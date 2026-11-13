<?php
require_once __DIR__ . '/config/config.php';
if (!empty($_SESSION['cliente_id'])) {
    redirect(BASE_URL . 'perfil/pedidos.php');
}
redirect(BASE_URL . 'auth/login.php');
