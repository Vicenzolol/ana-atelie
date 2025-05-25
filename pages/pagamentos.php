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
            <button class='btn btn-sm btn-edit' onclick='editarPagamento(" . $row['id'] . ", " . $row['venda_id'] . ", \"" . $row['data_pagamento'] . "\", " . $row['valor'] . ")'>
                Editar
            </button>
            <button class='btn btn-sm bg-danger' onclick='excluirPagamento(" . $row['id'] . ", " . $row['venda_id'] . ")'>
                Excluir
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

<!-- Modal Editar Pagamento -->
<div class="modal fade" id="editarPagamentoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Pagamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formEditarPagamento">
                    <input type="hidden" id="pagamento_id" name="id">
                    <input type="hidden" id="pagamento_venda_id" name="venda_id">

                    <div class="mb-3">
                        <label for="valor_pagamento" class="form-label">Valor</label>
                        <input type="number" step="0.01" class="form-control" id="valor_pagamento" name="valor" required>
                    </div>
                    <div class="mb-3">
                        <label for="data_pagamento" class="form-label">Data do Pagamento</label>
                        <input type="date" class="form-control" id="data_pagamento" name="data_pagamento" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="salvarEdicaoPagamento()">Salvar</button>
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

    function editarPagamento(id, vendaId, dataPagamento, valor) {
        // Preencher o formulário com os dados atuais
        document.getElementById('pagamento_id').value = id;
        document.getElementById('pagamento_venda_id').value = vendaId;

        // Formatar a data para o formato aceito pelo input date (YYYY-MM-DD)
        let dataFormatada;

        // Primeiro, verifica se a data já está no formato MySQL (YYYY-MM-DD)
        if (/^\d{4}-\d{2}-\d{2}/.test(dataPagamento)) {
            dataFormatada = dataPagamento.split(' ')[0]; // Pega apenas a parte da data
        }
        // Se for uma data formatada pelo PHP (DD/MM/YYYY)
        else if (/^\d{2}\/\d{2}\/\d{4}/.test(dataPagamento)) {
            const partes = dataPagamento.split('/');
            dataFormatada = `${partes[2].split(' ')[0]}-${partes[1]}-${partes[0]}`;
        }
        // Se for qualquer outro formato, tenta converter
        else {
            try {
                // Tenta converter a string para um objeto Date
                const dataObj = new Date(dataPagamento);
                if (!isNaN(dataObj.getTime())) {
                    const ano = dataObj.getFullYear();
                    const mes = String(dataObj.getMonth() + 1).padStart(2, '0');
                    const dia = String(dataObj.getDate()).padStart(2, '0');
                    dataFormatada = `${ano}-${mes}-${dia}`;
                } else {
                    // Se a conversão falhou, use a data atual
                    const hoje = new Date();
                    const ano = hoje.getFullYear();
                    const mes = String(hoje.getMonth() + 1).padStart(2, '0');
                    const dia = String(hoje.getDate()).padStart(2, '0');
                    dataFormatada = `${ano}-${mes}-${dia}`;
                    console.error("Data inválida, usando hoje:", dataFormatada);
                }
            } catch (error) {
                console.error("Erro ao processar data:", error);
                // Fallback para a data atual em caso de erro
                const hoje = new Date();
                dataFormatada = hoje.toISOString().split('T')[0];
            }
        }

        document.getElementById('data_pagamento').value = dataFormatada;
        document.getElementById('valor_pagamento').value = valor;

        // Abrir o modal
        const modal = new bootstrap.Modal(document.getElementById('editarPagamentoModal'));
        modal.show();
    }

    function salvarEdicaoPagamento() {
        const id = document.getElementById('pagamento_id').value;
        const vendaId = document.getElementById('pagamento_venda_id').value;
        const valor = parseFloat(document.getElementById('valor_pagamento').value);
        const data = document.getElementById('data_pagamento').value;

        if (!valor || valor <= 0) {
            alert('Por favor, informe um valor válido');
            return;
        }

        // Primeiro vamos verificar o valor total da venda e o total já pago
        fetch("../processar/pagamentos.php", {
                method: "POST",
                body: new URLSearchParams({
                    acao: "verificar_limite_pagamento",
                    id: id,
                    venda_id: vendaId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    const valorTotalVenda = data.valor_total;
                    const totalPagoOutros = data.total_pago_outros;
                    const valorRestante = valorTotalVenda - totalPagoOutros;

                    if (valor > valorRestante) {
                        alert(`O valor do pagamento (R$ ${valor.toLocaleString('pt-BR', {minimumFractionDigits: 2})}) não pode ser maior que o valor restante disponível (R$ ${valorRestante.toLocaleString('pt-BR', {minimumFractionDigits: 2})})`);
                        return;
                    }

                    // Se o valor é válido, prosseguir com a edição
                    realizarEdicaoPagamento(id, vendaId, valor, data);
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                alert("Erro ao verificar limites de pagamento: " + error.message);
                console.error(error);
            });
    }

    function realizarEdicaoPagamento(id, vendaId, valor, data) {
        fetch("../processar/pagamentos.php", {
                method: "POST",
                body: new URLSearchParams({
                    acao: "editar_pagamento",
                    id: id,
                    venda_id: vendaId,
                    valor: valor,
                    data_pagamento: data
                })
            })
            .then(response => {
                // Verificar o tipo de conteúdo da resposta
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.includes("application/json")) {
                    return response.json();
                }
                // Se não for JSON, captura o texto e lança um erro
                return response.text().then(text => {
                    console.error("Resposta não-JSON recebida:", text);
                    throw new Error("Resposta inválida do servidor: não é JSON válido");
                });
            })
            .then(data => {
                if (data.status === "success") {
                    alert(data.message);
                    window.location.reload();
                } else {
                    throw new Error(data.message || "Erro desconhecido");
                }
            })
            .catch(error => {
                alert("Erro ao editar pagamento: " + error.message);
                console.error("Detalhes do erro:", error);
            });
    }

    function excluirPagamento(id, vendaId) {
        if (confirm('Tem certeza que deseja excluir este pagamento? Isso também atualizará o status da venda.')) {
            fetch("../processar/pagamentos.php", {
                    method: "POST",
                    body: new URLSearchParams({
                        acao: "excluir_pagamento",
                        id: id,
                        venda_id: vendaId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === "success") {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        throw new Error(data.message);
                    }
                })
                .catch(error => {
                    alert("Erro ao excluir pagamento: " + error.message);
                    console.error(error);
                });
        }
    }
</script>

<?php require_once "../includes/footer.php"; ?>