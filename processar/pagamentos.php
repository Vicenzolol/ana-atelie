<?php
require_once "../includes/conexao.php";

header("Content-Type: application/json");

$acao = $_POST["acao"] ?? "";

try {
    switch ($acao) {
        case 'registrar_pagamento':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $valor = filter_input(INPUT_POST, 'valor', FILTER_VALIDATE_FLOAT);
        
            try {
                $conn->beginTransaction();
        
                // Insere o pagamento
                $sql = "INSERT INTO pagamentos (venda_id, valor) VALUES (?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$id, $valor]);
        
                // Calcula total pago
                $sql = "SELECT 
                        v.quantidade * v.valor_unitario as total,
                        COALESCE(SUM(p.valor), 0) as total_pago
                        FROM vendas v 
                        LEFT JOIN pagamentos p ON v.id = p.venda_id
                        WHERE v.id = ?
                        GROUP BY v.id";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
                // Atualiza status da venda
                $status = 'pendente';
                if ($result['total_pago'] >= $result['total']) {
                    $status = 'pago';
                } elseif ($result['total_pago'] > 0) {
                    $status = 'parcialmente_pago';
                }
        
                $sql = "UPDATE vendas SET status = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$status, $id]);
        
                $conn->commit();
                echo json_encode(["status" => "success", "message" => "Pagamento registrado com sucesso!"]);
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
            break;
        
        case "buscar":
            $id = $_POST["id"];
        
            $sql = "SELECT 
                    v.*, 
                    d.nome as nome_devedor, 
                    p.nome as nome_produto,
                    COALESCE(SUM(pg.valor), 0) as valor_pago,
                    (v.quantidade * v.valor_unitario) as valor_total
                    FROM vendas v 
                    JOIN devedores d ON v.devedor_id = d.id 
                    JOIN produtos p ON v.produto_id = p.id 
                    LEFT JOIN pagamentos pg ON v.id = pg.venda_id
                    WHERE v.id = ?
                    GROUP BY v.id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id]);
            $venda = $stmt->fetch(PDO::FETCH_ASSOC);
        
            echo json_encode(["status" => "success", "data" => $venda]);
            break;
        default:
            throw new Exception("Ação inválida");
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
