<?php
// Desabilitar exibição de erros para garantir saída JSON limpa
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');
require_once "../includes/conexao.php";

try {
    $acao = filter_input(INPUT_POST, 'acao');
    
    switch ($acao) {
        case 'criar':
            $nome = filter_input(INPUT_POST, 'nome');
            $nome = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
            
            $descricao = filter_input(INPUT_POST, 'descricao');
            $descricao = htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8');
            
            $preco_padrao = filter_input(INPUT_POST, 'preco_padrao', FILTER_VALIDATE_FLOAT);
            
            $sql = "INSERT INTO produtos (nome, descricao, preco_padrao) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nome, $descricao, $preco_padrao]);
            
            echo json_encode([
                "status" => "success",
                "message" => "Produto cadastrado com sucesso!"
            ]);
            break;
            
        case 'editar':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $nome = filter_input(INPUT_POST, 'nome');
            $nome = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
            
            $descricao = filter_input(INPUT_POST, 'descricao');
            $descricao = htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8');
            
            $preco_padrao = filter_input(INPUT_POST, 'preco_padrao', FILTER_VALIDATE_FLOAT);
            
            $sql = "UPDATE produtos SET nome = ?, descricao = ?, preco_padrao = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nome, $descricao, $preco_padrao, $id]);
            
            echo json_encode([
                "status" => "success",
                "message" => "Produto atualizado com sucesso!"
            ]);
            break;
            
        case 'buscar':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            
            $sql = "SELECT * FROM produtos WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id]);
            $produto = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                "status" => "success",
                "data" => $produto
            ]);
            break;
            
        case 'excluir':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            
            // Verificar se o produto está sendo usado em alguma venda
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM itens_venda WHERE produto_id = ?");
            $stmt->execute([$id]);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)["total"];
            
            if ($total > 0) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Não é possível excluir este produto pois ele está sendo usado em uma ou mais vendas!"
                ]);
            } else {
                $sql = "DELETE FROM produtos WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$id]);
                
                echo json_encode([
                    "status" => "success",
                    "message" => "Produto excluído com sucesso!"
                ]);
            }
            break;
            
        default:
            echo json_encode([
                "status" => "error",
                "message" => "Ação inválida"
            ]);
    }
} catch (Exception $e) {
    // Registrar o erro em um arquivo de log
    error_log("Erro em processar/produtos.php: " . $e->getMessage());
    
    echo json_encode([
        "status" => "error",
        "message" => "Erro ao processar a requisição: " . $e->getMessage()
    ]);
}
?>