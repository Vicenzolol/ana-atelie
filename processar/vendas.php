<?php
require_once "../includes/conexao.php";

header("Content-Type: application/json");

$acao = $_POST["acao"] ?? "";

try {
    switch ($acao) {
        case 'registrar_pagamento':
            try {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                $valor = filter_input(INPUT_POST, 'valor', FILTER_VALIDATE_FLOAT);
                $data_pagamento = filter_input(INPUT_POST, 'data_pagamento', FILTER_SANITIZE_STRING);

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
                            ultimo_pagamento = ?
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$total_pago, $status, $data_pagamento, $id]);

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
        case 'alterar_status_entrega':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

            $sql = "UPDATE vendas SET status_entrega = 
                        CASE WHEN status_entrega = 'encomendado' THEN 'entregue' 
                             ELSE 'encomendado' END 
                        WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id]);

            echo json_encode(["status" => "success", "message" => "Status de entrega alterado com sucesso!"]);
            break;
        case "buscar_pagamentos":
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

            try {
                // Busca informações da venda
                $sql = "SELECT 
                            v.*, 
                            d.nome as nome_devedor,
                            GROUP_CONCAT(p.nome SEPARATOR ', ') as produtos
                            FROM vendas v
                            JOIN devedores d ON v.devedor_id = d.id
                            LEFT JOIN itens_venda iv ON v.id = iv.venda_id
                            LEFT JOIN produtos p ON iv.produto_id = p.id
                            WHERE v.id = ?
                            GROUP BY v.id";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$id]);
                $venda = $stmt->fetch(PDO::FETCH_ASSOC);

                // Busca todos os pagamentos
                $sql = "SELECT * FROM pagamentos WHERE venda_id = ? ORDER BY data_pagamento DESC";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$id]);
                $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    "status" => "success",
                    "venda" => $venda,
                    "pagamentos" => $pagamentos
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Erro ao buscar histórico: " . $e->getMessage()
                ]);
            }
            break;
        case "criar":
            try {
                $conn->beginTransaction();

                $devedor_id = $_POST["devedor_id"];
                $data_venda = $_POST["data_venda"];
                $status_entrega = $_POST["status_entrega"];

                // Define próximo pagamento como um mês após a data da venda
                $proximo_pagamento = date('Y-m-d', strtotime($data_venda . ' + 1 month'));

                // Calcular valor total
                $valor_total = 0;
                foreach ($_POST["produtos"] as $index => $produto_id) {
                    $valor_total += $_POST["quantidades"][$index] * $_POST["valores"][$index];
                }

                // Inserir venda com próximo_pagamento
                $sql = "INSERT INTO vendas (
                                devedor_id, 
                                data_venda, 
                                status_entrega, 
                                valor_total,
                                proximo_pagamento,
                                status
                            ) VALUES (?, ?, ?, ?, ?, 'pendente')";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $devedor_id,
                    $data_venda,
                    $status_entrega,
                    $valor_total,
                    $proximo_pagamento
                ]);

                $venda_id = $conn->lastInsertId();

                // Inserir itens da venda
                $sql = "INSERT INTO itens_venda (venda_id, produto_id, quantidade, valor_unitario) 
                            VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);

                foreach ($_POST["produtos"] as $index => $produto_id) {
                    $stmt->execute([
                        $venda_id,
                        $produto_id,
                        $_POST["quantidades"][$index],
                        $_POST["valores"][$index]
                    ]);
                }

                $conn->commit();
                echo json_encode([
                    "status" => "success",
                    "message" => "Venda cadastrada com sucesso! Próximo pagamento em " . formatarDataBR($proximo_pagamento)
                ]);
            } catch (Exception $e) {
                $conn->rollBack();
                echo json_encode([
                    "status" => "error",
                    "message" => "Erro ao cadastrar venda: " . $e->getMessage()
                ]);
            }
            break;
        case "editar":
            try {
                $conn->beginTransaction();

                $id = $_POST["id"];
                $devedor_id = $_POST["devedor_id"];
                $data_venda = $_POST["data_venda"];
                $status_entrega = $_POST["status_entrega"];

                // Atualiza venda
                $sql = "UPDATE vendas 
                            SET devedor_id = ?, 
                                data_venda = ?, 
                                status_entrega = ? 
                            WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$devedor_id, $data_venda, $status_entrega, $id]);

                // Remove itens antigos
                $sql = "DELETE FROM itens_venda WHERE venda_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$id]);

                // Insere novos itens
                $sql = "INSERT INTO itens_venda (venda_id, produto_id, quantidade, valor_unitario) 
                            VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);

                $valor_total = 0;
                foreach ($_POST["produtos"] as $index => $produto_id) {
                    $quantidade = $_POST["quantidades"][$index];
                    $valor_unitario = $_POST["valores"][$index];
                    $valor_total += $quantidade * $valor_unitario;

                    $stmt->execute([
                        $id,
                        $produto_id,
                        $quantidade,
                        $valor_unitario
                    ]);
                }

                // Atualiza valor total da venda
                $sql = "UPDATE vendas SET valor_total = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$valor_total, $id]);

                $conn->commit();
                echo json_encode(["status" => "success", "message" => "Venda atualizada com sucesso!"]);
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
            break;
        case "editar_simples":
            try {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                $devedor_id = filter_input(INPUT_POST, 'devedor_id', FILTER_VALIDATE_INT);
                $data_venda = filter_input(INPUT_POST, 'data_venda', FILTER_SANITIZE_STRING);
                $status_entrega = filter_input(INPUT_POST, 'status_entrega', FILTER_SANITIZE_STRING);

                $sql = "UPDATE vendas 
                            SET devedor_id = ?, 
                                data_venda = ?, 
                                status_entrega = ? 
                            WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$devedor_id, $data_venda, $status_entrega, $id]);

                echo json_encode([
                    "status" => "success",
                    "message" => "Venda atualizada com sucesso!"
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Erro ao atualizar venda: " . $e->getMessage()
                ]);
            }
            break;
        case "excluir":
            $id = $_POST["id"];

            $sql = "DELETE FROM vendas WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id]);

            echo json_encode(["status" => "success", "message" => "Venda excluída com sucesso!"]);
            break;

        case "buscar":
            $id = $_POST["id"];

            $sql = "SELECT 
                    v.*, 
                    d.nome as nome_devedor,
                    GROUP_CONCAT(p.nome) as produtos,
                    GROUP_CONCAT(iv.quantidade) as quantidades,
                    GROUP_CONCAT(iv.valor_unitario) as valores_unitarios
                    FROM vendas v 
                    JOIN devedores d ON v.devedor_id = d.id 
                    LEFT JOIN itens_venda iv ON v.id = iv.venda_id
                    LEFT JOIN produtos p ON iv.produto_id = p.id 
                    WHERE v.id = ?
                    GROUP BY v.id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id]);
            $venda = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode(["status" => "success", "data" => $venda]);
            break;

        case "marcar_pago":
            $id = $_POST["id"];

            $sql = "UPDATE vendas SET status = \"pago\" WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id]);

            echo json_encode(["status" => "success", "message" => "Venda marcada como paga com sucesso!"]);
            break;

        default:
            throw new Exception("Ação inválida");
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
