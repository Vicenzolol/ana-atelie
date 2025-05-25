<?php
require_once "../includes/conexao.php";
require_once "../includes/header.php";
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Histórico de Pagamentos</h2>
</div>

<!-- Campo de pesquisa -->
<div class="mb-3">
    <input type="text" class="form-control" id="pesquisaPagamento"
        placeholder="Pesquisar por cliente ou produto..." onkeyup="pesquisarPagamento()">
</div>

<table class="table table-striped">
    <thead>
        <tr>
            <th>Data Pagamento</th>
            <th>Cliente</th>
            <th>Produto</th>
            <th>Valor Pago</th>
            <th>Valor Total Venda</th>
            <th>Status</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $sql = "SELECT 
        p.id,
        p.valor,
        p.data_pagamento,
        d.nome as nome_devedor,
        GROUP_CONCAT(pr.nome SEPARATOR ', ') as produtos,
        v.valor_total,
        v.status,
        v.id as venda_id
        FROM pagamentos p
        JOIN vendas v ON p.venda_id = v.id
        JOIN devedores d ON v.devedor_id = d.id
        LEFT JOIN itens_venda iv ON v.id = iv.venda_id
        LEFT JOIN produtos pr ON iv.produto_id = pr.id
        GROUP BY p.id, p.valor, p.data_pagamento, d.nome, v.valor_total, v.status, v.id
        ORDER BY p.data_pagamento DESC";

        $stmt = $conn->query($sql);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $status_classes = [
                'pendente' => 'text-danger',
                'parcialmente_pago' => 'text-warning',
                'pago' => 'text-success'
            ];

            $classe_status = $status_classes[$row['status']] ?? 'text-secondary';

            echo "<tr>";
            echo "<td>" . formatarDataHoraBR($row['data_pagamento']) . "</td>";
            echo "<td>" . htmlspecialchars($row['nome_devedor']) . "</td>";
            echo "<td>" . htmlspecialchars($row['produtos']) . "</td>";
            echo "<td>R$ " . number_format($row['valor'], 2, ',', '.') . "</td>";
            echo "<td>R$ " . number_format($row['valor_total'], 2, ',', '.') . "</td>";
            echo "<td class=\"{$classe_status}\">" . ucfirst($row['status']) . "</td>";
            echo "<td>
            <button class='btn btn-sm bg-info' onclick='verDetalhesVenda(" . $row['venda_id'] . ")'>
                Ver Detalhes
            </button>
          </td>";
            echo "</tr>";
        }
        ?>
    </tbody>
</table>

<!-- Modal Detalhes da Venda -->
<div class="modal fade" id="detalhesVendaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes da Venda</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="detalhesVendaConteudo">
                    <!-- Conteúdo será preenchido via JavaScript -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function pesquisarPagamento() {
        const termo = document.getElementById("pesquisaPagamento").value.toLowerCase();
        const tabela = document.querySelector("table tbody");
        const linhas = tabela.getElementsByTagName("tr");

        for (let linha of linhas) {
            const cliente = linha.getElementsByTagName("td")[1].textContent.toLowerCase();
            const produto = linha.getElementsByTagName("td")[2].textContent.toLowerCase();

            if (cliente.includes(termo) || produto.includes(termo)) {
                linha.style.display = "";
            } else {
                linha.style.display = "none";
            }
        }
    }

    function verDetalhesVenda(id) {
        fetch("../processar/vendas.php", {
                method: "POST",
                body: new URLSearchParams({
                    acao: "buscar",
                    id: id
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    const venda = data.data;

                    let html = `
                <div class="mb-3">
                    <strong>Cliente:</strong> ${venda.nome_devedor}
                </div>
                <div class="mb-3">
                    <strong>Produtos:</strong> ${venda.produtos}
                </div>
                <div class="mb-3">
                    <strong>Valor Total:</strong> R$ ${Number(venda.valor_total).toLocaleString('pt-BR', {minimumFractionDigits: 2})}
                </div>
                <div class="mb-3">
                    <strong>Valor Pago:</strong> R$ ${Number(venda.valor_pago || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}
                </div>
                <div class="mb-3">
                    <strong>Data da Venda:</strong> ${new Date(venda.data_venda).toLocaleDateString()}
                </div>
                <div class="mb-3">
                    <strong>Status:</strong> ${venda.status}
                </div>
                <div class="mb-3">
                    <strong>Status Entrega:</strong> ${venda.status_entrega}
                </div>`;

                    document.getElementById('detalhesVendaConteudo').innerHTML = html;
                    const modal = new bootstrap.Modal(document.getElementById('detalhesVendaModal'));
                    modal.show();
                } else {
                    alert("Erro ao buscar detalhes da venda: " + data.message);
                }
            })
            .catch(error => {
                alert("Erro ao buscar detalhes da venda");
                console.error(error);
            });
    }
</script>

<?php require_once "../includes/footer.php"; ?>