<?php
// Desativar exibição de erros para garantir JSON limpo
ini_set('display_errors', 0);
error_reporting(0);
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ana-atelie');

try {
    $conn = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar se a conexão está ativa com uma consulta simples
    $stmt = $conn->query('SELECT 1');
    if (!$stmt) {
        throw new Exception("Falha na conexão com o banco de dados");
    }
} catch(PDOException $e) {
    // Registrar o erro em log
    error_log('Erro de conexão: ' . $e->getMessage());
    
    // Em páginas de API JSON, retornar erro em formato JSON
    if (strpos($_SERVER['REQUEST_URI'], 'processar/') !== false) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Erro de conexão com o banco de dados']);
        exit;
    }
    
    // Em páginas normais, mostrar mensagem amigável
    echo 'Erro de conexão com o banco de dados. Por favor, verifique se o servidor MySQL está rodando.';
    exit;
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