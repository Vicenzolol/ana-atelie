<?php
require_once "../includes/conexao.php";

header("Content-Type: application/json");

$acao = $_POST["acao"] ?? "";

try {
    switch ($acao) {
        case 'registrar_pagamento':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $valor = filter_input(INPUT_POST, 'valor', FILTER_VALIDATE_FLOAT);
            $data_pagamento = filter_input(INPUT_POST, 'data_pagamento', FILTER_SANITIZE_STRING) ?? date('Y-m-d');

            try {
                $conn->beginTransaction();

                // 1. Insere o pagamento
                $sql = "INSERT INTO pagamentos (venda_id, valor, data_pagamento) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$id, $valor, $data_pagamento]);

                // 2. Atualiza a venda com o valor total pago
                $sql = "SELECT COALESCE(SUM(valor), 0) as total_pago 
                        FROM pagamentos 
                        WHERE venda_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$id]);
                $total_pago = $stmt->fetch(PDO::FETCH_ASSOC)['total_pago'];

                // 3. Busca o valor total da venda
                $sql = "SELECT valor_total FROM vendas WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$id]);
                $valor_total = $stmt->fetch(PDO::FETCH_ASSOC)['valor_total'];

                // 4. Define o status baseado no total pago
                $status = 'pendente';
                if ($total_pago >= $valor_total) {
                    $status = 'pago';
                } elseif ($total_pago > 0) {
                    $status = 'parcialmente_pago';
                }

                // 5. Atualiza a venda
                $sql = "UPDATE vendas 
                        SET valor_pago = ?,
                            status = ?,
                            ultimo_pagamento = ?,
                            proximo_pagamento = DATE_ADD(?, INTERVAL 1 MONTH)
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$total_pago, $status, $data_pagamento, $data_pagamento, $id]);

                $conn->commit();
                echo json_encode([
                    "status" => "success",
                    "message" => "Pagamento registrado com sucesso!"
                ]);
            } catch (Exception $e) {
                $conn->rollBack();
                echo json_encode([
                    "status" => "error",
                    "message" => "Erro ao registrar pagamento: " . $e->getMessage()
                ]);
            }
            break;

        case 'verificar_limite_pagamento':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $venda_id = filter_input(INPUT_POST, 'venda_id', FILTER_VALIDATE_INT);

            try {
                // 1. Obter o valor total da venda
                $sql = "SELECT valor_total FROM vendas WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$venda_id]);
                $valor_total = $stmt->fetch(PDO::FETCH_ASSOC)['valor_total'];

                // 2. Obter a soma de todos os outros pagamentos da venda (excluindo o atual)
                $sql = "SELECT COALESCE(SUM(valor), 0) as total_pago_outros 
                FROM pagamentos 
                WHERE venda_id = ? AND id != ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$venda_id, $id]);
                $total_pago_outros = $stmt->fetch(PDO::FETCH_ASSOC)['total_pago_outros'];

                // 3. Retornar os valores para validação no cliente
                echo json_encode([
                    "status" => "success",
                    "valor_total" => (float)$valor_total,
                    "total_pago_outros" => (float)$total_pago_outros
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Erro ao verificar limites de pagamento: " . $e->getMessage()
                ]);
            }
            break;

        case 'editar_pagamento':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $venda_id = filter_input(INPUT_POST, 'venda_id', FILTER_VALIDATE_INT);
            $valor = filter_input(INPUT_POST, 'valor', FILTER_VALIDATE_FLOAT);
            $data_pagamento = filter_input(INPUT_POST, 'data_pagamento', FILTER_SANITIZE_STRING);

            // Validação adicional da data
            if (empty($data_pagamento) || $data_pagamento === '0000-00-00') {
                $data_pagamento = date('Y-m-d'); // Usa a data atual como fallback
            }

            // Verifica se a data está em um formato válido para MySQL
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_pagamento)) {
                // Tenta converter de outros formatos possíveis
                $timestamp = strtotime($data_pagamento);
                if ($timestamp === false) {
                    // Se falhar, usa a data atual
                    $data_pagamento = date('Y-m-d');
                } else {
                    $data_pagamento = date('Y-m-d', $timestamp);
                }
            }

            try {
                $conn->beginTransaction();

                // Registra a data para debugging
                error_log("Atualizando pagamento ID: $id com data: $data_pagamento");

                // 1. Atualiza o pagamento
                $sql = "UPDATE pagamentos SET valor = ?, data_pagamento = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$valor, $data_pagamento, $id]);

                // Verifica se a atualização foi bem-sucedida
                if ($stmt->rowCount() === 0) {
                    throw new Exception("Nenhum registro foi atualizado. Verifique se o ID é válido.");
                }

                // 2. Recalcula o total pago na venda
                $sql = "SELECT COALESCE(SUM(valor), 0) as total_pago FROM pagamentos WHERE venda_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$venda_id]);
                $total_pago = $stmt->fetch(PDO::FETCH_ASSOC)['total_pago'];

                // 3. Busca o valor total da venda
                $sql = "SELECT valor_total FROM vendas WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$venda_id]);
                $valor_total = $stmt->fetch(PDO::FETCH_ASSOC)['valor_total'];

                // 4. Define o status baseado no total pago
                $status = 'pendente';
                if ($total_pago >= $valor_total) {
                    $status = 'pago';
                } elseif ($total_pago > 0) {
                    $status = 'parcialmente_pago';
                }

                // 5. Atualiza a venda
                $sql = "UPDATE vendas SET valor_pago = ?, status = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$total_pago, $status, $venda_id]);

                $conn->commit();
                echo json_encode([
                    "status" => "success",
                    "message" => "Pagamento atualizado com sucesso!"
                ]);
            } catch (Exception $e) {
                $conn->rollBack();
                echo json_encode([
                    "status" => "error",
                    "message" => "Erro ao atualizar pagamento: " . $e->getMessage()
                ]);
            }
            break;

        case 'excluir_pagamento':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $venda_id = filter_input(INPUT_POST, 'venda_id', FILTER_VALIDATE_INT);

            try {
                $conn->beginTransaction();

                // 1. Exclui o pagamento
                $sql = "DELETE FROM pagamentos WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$id]);

                // 2. Recalcula o total pago na venda
                $sql = "SELECT COALESCE(SUM(valor), 0) as total_pago FROM pagamentos WHERE venda_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$venda_id]);
                $total_pago = $stmt->fetch(PDO::FETCH_ASSOC)['total_pago'];

                // 3. Busca o valor total da venda
                $sql = "SELECT valor_total FROM vendas WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$venda_id]);
                $valor_total = $stmt->fetch(PDO::FETCH_ASSOC)['valor_total'];

                // 4. Define o status baseado no total pago
                $status = 'pendente';
                if ($total_pago >= $valor_total) {
                    $status = 'pago';
                } elseif ($total_pago > 0) {
                    $status = 'parcialmente_pago';
                }

                // 5. Atualiza a venda
                $sql = "UPDATE vendas SET valor_pago = ?, status = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$total_pago, $status, $venda_id]);

                $conn->commit();
                echo json_encode([
                    "status" => "success",
                    "message" => "Pagamento excluído com sucesso!"
                ]);
            } catch (Exception $e) {
                $conn->rollBack();
                echo json_encode([
                    "status" => "error",
                    "message" => "Erro ao excluir pagamento: " . $e->getMessage()
                ]);
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
