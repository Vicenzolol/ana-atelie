<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ana-atelie');

try {
    $conn = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo 'Erro de conexão: ' . $e->getMessage();
}

// Função para formatar data e hora no padrão brasileiro
function formatarDataHoraBR($data) {
    if ($data == 'Sem pagamento') return $data;
    return date('d/m/Y H:i', strtotime($data));
}

// Função para formatar apenas a data no padrão brasileiro
function formatarDataBR($data) {
    if ($data == 'Sem pagamento') return $data;
    return date('d/m/Y', strtotime($data));
}