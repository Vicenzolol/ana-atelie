<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Teste de Conexão</h1>";

echo "<h2>Informações do PHP</h2>";
echo "Versão do PHP: " . phpversion() . "<br>";
echo "Extensões carregadas: <pre>" . print_r(get_loaded_extensions(), true) . "</pre>";

echo "<h2>Teste de Conexão MySQL</h2>";
try {
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $dbname = 'ana-atelie';
    
    echo "Tentando conectar a MySQL ($host, $user, $dbname)...<br>";
    
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Conexão bem-sucedida!<br>";
    
    echo "Testando consulta simples... ";
    $stmt = $conn->query('SELECT 1 as test');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "OK! Resultado: " . $result['test'] . "<br>";
    
    echo "Verificando tabelas existentes:<br>";
    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<pre>" . print_r($tables, true) . "</pre>";
    
} catch(PDOException $e) {
    echo "Erro de conexão: " . $e->getMessage();
}