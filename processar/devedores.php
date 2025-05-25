<?php
require_once "../includes/conexao.php";

header("Content-Type: application/json");

$acao = $_POST["acao"] ?? "";

try {
    switch ($acao) {
        case "criar":
            $nome = $_POST["nome"];

            $sql = "INSERT INTO devedores (nome) VALUES (?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nome]);

            echo json_encode(["status" => "success", "message" => "Devedor cadastrado com sucesso!"]);
            break;

        case "editar":
            $id = $_POST["id"];
            $nome = $_POST["nome"];

            $sql = "UPDATE devedores SET nome = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nome, $id]);

            echo json_encode(["status" => "success", "message" => "Devedor atualizado com sucesso!"]);
            break;

        case "excluir":
            $id = $_POST["id"];

            // Verifica se existe alguma venda vinculada
            $sql = "SELECT COUNT(*) as total FROM vendas WHERE devedor_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id]);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)["total"];

            if ($total > 0) {
                throw new Exception("Não é possível excluir este devedor pois existem vendas vinculadas.");
            }

            $sql = "DELETE FROM devedores WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id]);

            echo json_encode(["status" => "success", "message" => "Devedor excluído com sucesso!"]);
            break;

        case "buscar":
            $id = $_POST["id"];

            $sql = "SELECT * FROM devedores WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id]);
            $devedor = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode(["status" => "success", "data" => $devedor]);
            break;

        default:
            throw new Exception("Ação inválida");
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
