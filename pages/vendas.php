<?php
require_once "../includes/conexao.php";
require_once "../includes/header.php";

// Verifica cobranças pendentes
$sql_pendentes = "SELECT COUNT(*) as total FROM vendas v WHERE v.status = \"pendente\"";
$stmt = $conn->query($sql_pendentes);
$total_pendentes = $stmt->fetch(PDO::FETCH_ASSOC)["total"];

if ($total_pendentes > 0) {
    echo "<div class=\"alert alert-warning\">Existem $total_pendentes cobrança(s) pendente(s)!</div>";
}

// Busca todas as vendas com informações relacionadas
$sql = "SELECT 
        v.*, 
        d.nome as nome_devedor,
        GROUP_CONCAT(p.nome SEPARATOR ', ') as produtos,
        GROUP_CONCAT(iv.quantidade SEPARATOR ', ') as quantidades,
        GROUP_CONCAT(iv.valor_unitario SEPARATOR ', ') as valores,
        v.valor_total,
        COALESCE(v.valor_pago, 0) as valor_pago,
        COALESCE(v.ultimo_pagamento, 'Sem pagamento') as ultimo_pagamento
        FROM vendas v 
        JOIN devedores d ON v.devedor_id = d.id 
        LEFT JOIN itens_venda iv ON v.id = iv.venda_id
        LEFT JOIN produtos p ON iv.produto_id = p.id 
        GROUP BY v.id
        ORDER BY v.status ASC, v.ultimo_pagamento DESC";
$stmt = $conn->query($sql);
?>


<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Vendas</h2>
    <button type="button" class="btn btn-new-debtor" data-bs-toggle="modal" data-bs-target="#novaVendaModal">
        Nova Venda
    </button>
</div>

<!-- Campo de pesquisa -->
<div class="row mb-3">
    <div class="col-md-4">
        <input type="text" class="form-control" id="pesquisaVenda"
            placeholder="Pesquisar por cliente..." onkeyup="pesquisarVenda()">
    </div>
    <div class="col-md-4">
        <select class="form-select" id="filtroStatusPagamento" onchange="filtrarVendas()">
            <option value="">Status Pagamento (Todos)</option>
            <option value="pendente">Pendente</option>
            <option value="parcialmente_pago">Parcialmente Pago</option>
            <option value="pago">Pago</option>
        </select>
    </div>
    <div class="col-md-4">
        <select class="form-select" id="filtroStatusEntrega" onchange="filtrarVendas()">
            <option value="">Status Entrega (Todos)</option>
            <option value="encomendado">Encomendado</option>
            <option value="entregue">Entregue</option>
        </select>
    </div>
    <div class="col-12 mt-2">
        <button class="btn btn-secondary btn-sm" onclick="limparFiltros()">
            Limpar Filtros
        </button>
    </div>
</div>

<table class="table table-striped">
    <thead>
        <tr>
            <th>Cliente</th>
            <th>Produto</th>
            <th>Valor Total</th>
            <th>Valor Pago</th>
            <th>Restante</th>
            <th>Último Pagamento</th>
            <th>Status Pagamento</th>
            <th>Status Entrega</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
            // Calcula o valor restante usando o valor_total da venda
            $restante = $row["valor_total"] - $row["valor_pago"];
            $status_classes = [
                'pendente' => 'text-danger',
                'parcialmente_pago' => 'text-danger',
                'pago' => 'text-success'
            ];
            $classe_status = $status_classes[$row["status"]] ?? 'text-secondary';

            // Para mostrar múltiplos produtos
            $produtos = explode(', ', $row["produtos"]);
            $produtos_formatados = implode('<br>', array_map('htmlspecialchars', $produtos));
        ?>
            <tr>
                <td><?= htmlspecialchars($row["nome_devedor"]) ?></td>
                <td><?= $produtos_formatados ?></td>
                <td>R$ <?= number_format($row["valor_total"], 2, ",", ".") ?></td>
                <td>R$ <?= number_format($row["valor_pago"], 2, ",", ".") ?></td>
                <td id="valor_restante_<?= $row['id'] ?>">R$ <?= number_format($restante, 2, ",", ".") ?></td>
               <td><?= formatarDataBR($row["ultimo_pagamento"]) ?></td>
                <td class="<?= $classe_status ?>"><?= ucfirst($row["status"]) ?></td>
                <td><?= ucfirst($row["status_entrega"]) ?></td>
                <td>
                    <button class="btn btn-sm bg-success" onclick="registrarPagamento(<?= $row['id'] ?>)">Registrar Pagamento</button>
                    <button class="btn btn-sm bg-warning" onclick="alterarStatusEntrega(<?= $row['id'] ?>)">
                        <?= $row["status_entrega"] == 'encomendado' ? 'Marcar Entregue' : 'Marcar Encomendado' ?>
                    </button>
                    <button class="btn btn-sm bg-info" onclick="verHistoricoPagamentos(<?= $row['id'] ?>)">Histórico</button>
                    <button class="btn btn-sm btn-edit" onclick="editarVendaSimples(<?= $row['id'] ?>)">Editar</button>
                    <button class="btn btn-sm bg-danger" onclick="excluirVenda(<?= $row['id'] ?>)">Excluir</button>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<!-- Modal Nova Venda -->
<div class="modal fade" id="novaVendaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova Venda</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formVenda" method="post">
                    <div class="mb-3">
                        <label for="devedor_id" class="form-label">Cliente</label>
                        <select class="form-select" id="devedor_id" name="devedor_id" required>
                            <option value="">Selecione um cliente...</option>
                            <?php
                            $sql = "SELECT * FROM devedores ORDER BY nome";
                            $stmt = $conn->query($sql);
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value=\"" . $row["id"] . "\">" . htmlspecialchars($row["nome"]) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <h6>Produtos</h6>
                        <div id="produtos-container">
                            <div class="produto-item mb-2">
                                <div class="row">
                                    <div class="col-md-5">
                                        <select class="form-select produto-select" name="produtos[]" required>
                                            <option value="">Selecione um produto...</option>
                                            <?php
                                            $sql = "SELECT * FROM produtos ORDER BY nome";
                                            $stmt = $conn->query($sql);
                                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                echo "<option value=\"" . $row["id"] . "\" data-preco=\"" . $row["preco_padrao"] . "\">"
                                                    . htmlspecialchars($row["nome"]) . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="number" class="form-control quantidade" name="quantidades[]" placeholder="Qtd" min="1" required>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="number" step="0.01" class="form-control valor-unitario" name="valores[]" placeholder="Valor" required>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-danger btn-sm remover-produto">X</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm mt-2" onclick="adicionarProduto()">+ Adicionar Produto</button>
                    </div>
                    <div class="mb-3">
                        <label for="data_venda" class="form-label">Data da Venda</label>
                        <input type="date" class="form-control" id="data_venda" name="data_venda" required
                            value="<?php echo date("Y-m-d"); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="status_entrega" class="form-label">Status da Entrega</label>
                        <select class="form-select" id="status_entrega" name="status_entrega" required>
                            <option value="encomendado">Encomendado</option>
                            <option value="entregue">Entregue</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" onclick="salvarVenda()">Salvar</button>
            </div>
        </div>
    </div>
</div>
<!-- Adicione este modal no final do arquivo, antes do </body> -->
<div class="modal fade" id="registrarPagamentoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Registrar Pagamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formPagamento">
                    <input type="hidden" id="pagamento_venda_id" name="id">
                    <div class="mb-3">
                        <label for="valor_pagamento" class="form-label">Valor</label>
                        <input type="number" step="0.01" class="form-control" id="valor_pagamento" required>
                    </div>
                    <div class="mb-3">
                        <label for="data_pagamento" class="form-label">Data do Pagamento</label>
                        <input type="date" class="form-control" id="data_pagamento" required
                            value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="confirmarPagamento()">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<script>
    function adicionarProduto() {
        const container = document.getElementById('produtos-container');
        const template = container.children[0].cloneNode(true);

        // Limpar valores
        template.querySelector('.produto-select').value = '';
        template.querySelector('.quantidade').value = '';
        template.querySelector('.valor-unitario').value = '';

        // Adicionar evento para remover
        template.querySelector('.remover-produto').addEventListener('click', function() {
            if (container.children.length > 1) {
                this.closest('.produto-item').remove();
                calcularTotal();
            }
        });

        container.appendChild(template);
    }

    function calcularTotal() {
        let total = 0;
        const items = document.querySelectorAll('.produto-item');

        items.forEach(item => {
            const quantidade = parseFloat(item.querySelector('.quantidade').value) || 0;
            const valor = parseFloat(item.querySelector('.valor-unitario').value) || 0;
            total += quantidade * valor;
        });

        document.getElementById('valor_total').value = total.toFixed(2);
    }

    function verHistoricoPagamentos(id) {
        fetch("../processar/vendas.php", {
                method: "POST",
                body: new URLSearchParams({
                    acao: "buscar_pagamentos",
                    id: id
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    // Criar HTML para o modal
                    let html = `
            <div class="modal-header">
                <h5 class="modal-title">Histórico de Pagamentos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <strong>Cliente:</strong> ${data.venda.nome_devedor}<br>
                    <strong>Produtos:</strong> ${data.venda.produtos}<br>
                    <strong>Valor Total:</strong> R$ ${Number(data.venda.valor_total).toLocaleString('pt-BR', {minimumFractionDigits: 2})}
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody>`;

                    if (data.pagamentos.length > 0) {
                        data.pagamentos.forEach(pagamento => {
                            html += `
                    <tr>
                        <td>${new Date(pagamento.data_pagamento).toLocaleDateString()}</td>
                        <td>R$ ${Number(pagamento.valor).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                    </tr>`;
                        });
                    } else {
                        html += '<tr><td colspan="2" class="text-center">Nenhum pagamento registrado</td></tr>';
                    }

                    html += `
                    </tbody>
                </table>
            </div>`;

                    // Criar/atualizar o modal
                    let modalEl = document.getElementById('historicoPagamentosModal');
                    if (!modalEl) {
                        modalEl = document.createElement('div');
                        modalEl.className = 'modal fade';
                        modalEl.id = 'historicoPagamentosModal';
                        modalEl.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content">
                        ${html}
                    </div>
                </div>`;
                        document.body.appendChild(modalEl);
                    } else {
                        modalEl.querySelector('.modal-content').innerHTML = html;
                    }

                    // Mostrar o modal
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                } else {
                    alert("Erro: " + data.message);
                }
            })
            .catch(error => {
                alert("Erro ao buscar histórico de pagamentos");
                console.error(error);
            });
    }

    // Substitua a função registrarPagamento atual por esta:
    function registrarPagamento(id) {
        document.getElementById('pagamento_venda_id').value = id;
        document.getElementById('valor_pagamento').value = '';
        document.getElementById('data_pagamento').value = new Date().toISOString().split('T')[0];

        const modal = new bootstrap.Modal(document.getElementById('registrarPagamentoModal'));
        modal.show();
    }

    // Adicione esta nova função
    function confirmarPagamento() {
        const id = document.getElementById('pagamento_venda_id').value;
        const valor = parseFloat(document.getElementById('valor_pagamento').value);
        const data = document.getElementById('data_pagamento').value;

        try {
            const valorRestante = parseFloat(document.getElementById('valor_restante_' + id).textContent
                .replace('R$', '')
                .replace('.', '')
                .replace(',', '.'));

            if (!valor || valor <= 0) {
                alert('Por favor, informe um valor válido');
                return;
            }

            if (valor > valorRestante) {
                alert(`O valor do pagamento (R$ ${valor.toLocaleString('pt-BR', {minimumFractionDigits: 2})}) não pode ser maior que o valor restante (R$ ${valorRestante.toLocaleString('pt-BR', {minimumFractionDigits: 2})})`);
                return;
            }

            if (!data) {
                alert('Por favor, selecione uma data');
                return;
            }

            fetch("../processar/vendas.php", {
                    method: "POST",
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        acao: "registrar_pagamento",
                        id: id,
                        valor: valor,
                        data_pagamento: data
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
                    alert("Erro ao registrar pagamento: " + error.message);
                    console.error(error);
                });
        } catch (error) {
            alert("Erro ao processar dados: " + error.message);
            console.error(error);
        }
    }

    function alterarStatusEntrega(id) {
        fetch("../processar/vendas.php", {
                method: "POST",
                body: new URLSearchParams({
                    acao: "alterar_status_entrega",
                    id: id
                })
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.status === "success") {
                    window.location.reload();
                }
            })
            .catch(error => {
                alert("Erro ao alterar status de entrega");
                console.error(error);
            });
    }

    function pesquisarVenda() {
        filtrarVendas();
    }

    function salvarVenda() {
        const form = document.getElementById("formVenda");
        const formData = new FormData(form);

        // Verifica se é uma edição ou criação
        if (form.querySelector("input[name='id']")) {
            formData.append("acao", "editar");
        } else {
            formData.append("acao", "criar");
        }

        fetch("../processar/vendas.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert("Erro: " + data.message);
                }
            })
            .catch(error => {
                alert("Erro ao processar requisição");
                console.error(error);
            });
    }

    function editarVenda(id) {
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

                    // Limpa o container de produtos exceto o primeiro item
                    const container = document.getElementById('produtos-container');
                    while (container.children.length > 1) {
                        container.removeChild(container.lastChild);
                    }

                    // Preenche o formulário
                    document.getElementById("devedor_id").value = venda.devedor_id;
                    document.getElementById("data_venda").value = venda.data_venda;
                    document.getElementById("status_entrega").value = venda.status_entrega;

                    // Separa os arrays de produtos
                    const produtos = venda.produtos ? venda.produtos.split(',') : [];
                    const quantidades = venda.quantidades ? venda.quantidades.split(',') : [];
                    const valores = venda.valores_unitarios ? venda.valores_unitarios.split(',') : [];

                    // Preenche o primeiro produto
                    if (produtos.length > 0) {
                        const firstItem = container.children[0];
                        firstItem.querySelector('.produto-select').value = produtos[0];
                        firstItem.querySelector('.quantidade').value = quantidades[0];
                        firstItem.querySelector('.valor-unitario').value = valores[0];
                    }

                    // Adiciona os produtos adicionais
                    for (let i = 1; i < produtos.length; i++) {
                        adicionarProduto();
                        const item = container.children[container.children.length - 1];
                        item.querySelector('.produto-select').value = produtos[i];
                        item.querySelector('.quantidade').value = quantidades[i];
                        item.querySelector('.valor-unitario').value = valores[i];
                    }

                    // Adiciona campo hidden com ID
                    const form = document.getElementById("formVenda");
                    let idInput = form.querySelector("input[name='id']");
                    if (!idInput) {
                        idInput = document.createElement("input");
                        idInput.type = "hidden";
                        idInput.name = "id";
                        form.appendChild(idInput);
                    }
                    idInput.value = id;

                    // Abre o modal
                    const modal = new bootstrap.Modal(document.getElementById("novaVendaModal"));
                    modal.show();
                } else {
                    alert("Erro: " + data.message);
                }
            })
            .catch(error => {
                alert("Erro ao buscar dados da venda");
                console.error(error);
            });
    }

    function marcarComoPago(id) {
        if (confirm("Confirma que este item foi pago?")) {
            fetch("../processar/vendas.php", {
                    method: "POST",
                    body: new URLSearchParams({
                        acao: "marcar_pago",
                        id: id
                    })
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.status === "success") {
                        window.location.reload();
                    }
                })
                .catch(error => {
                    alert("Erro ao marcar como pago");
                    console.error(error);
                });
        }
    }

    function excluirVenda(id) {
        if (confirm("Tem certeza que deseja excluir esta venda?")) {
            fetch("../processar/vendas.php", {
                    method: "POST",
                    body: new URLSearchParams({
                        acao: "excluir",
                        id: id
                    })
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.status === "success") {
                        window.location.reload();
                    }
                })
                .catch(error => {
                    alert("Erro ao excluir venda");
                    console.error(error);
                });
        }
    }

    function filtrarVendas() {
        const termo = document.getElementById("pesquisaVenda").value.toLowerCase();
        const statusPagamento = document.getElementById("filtroStatusPagamento").value;
        const statusEntrega = document.getElementById("filtroStatusEntrega").value;

        const tabela = document.querySelector("table tbody");
        const linhas = tabela.getElementsByTagName("tr");

        for (let linha of linhas) {
            const cliente = linha.getElementsByTagName("td")[0].textContent.toLowerCase();
            const produto = linha.getElementsByTagName("td")[1].textContent.toLowerCase();
            const pagamento = linha.getElementsByTagName("td")[6].textContent.toLowerCase();
            const entrega = linha.getElementsByTagName("td")[7].textContent.toLowerCase();

            // Verifica se atende todos os critérios de filtro
            const atendeTermoBusca = cliente.includes(termo) || produto.includes(termo);
            const atendeStatusPagamento = !statusPagamento || pagamento.toLowerCase() === statusPagamento.toLowerCase();
            const atendeStatusEntrega = !statusEntrega || entrega.toLowerCase() === statusEntrega.toLowerCase();

            if (atendeTermoBusca && atendeStatusPagamento && atendeStatusEntrega) {
                linha.style.display = "";
            } else {
                linha.style.display = "none";
            }
        }
    }

    function limparFiltros() {
        document.getElementById("pesquisaVenda").value = "";
        document.getElementById("filtroStatusPagamento").value = "";
        document.getElementById("filtroStatusEntrega").value = "";
        filtrarVendas();
    }

    function editarVendaSimples(id) {
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
            <div class="modal-header">
                <h5 class="modal-title">Editar Venda</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formEditarVenda">
                    <input type="hidden" name="id" value="${venda.id}">
                    <div class="mb-3">
                        <label for="edit_devedor_id" class="form-label">Cliente</label>
                        <select class="form-select" id="edit_devedor_id" name="devedor_id" required>
                            <?php
                            $sql = "SELECT * FROM devedores ORDER BY nome";
                            $stmt = $conn->query($sql);
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value=\"" . $row["id"] . "\">" . htmlspecialchars($row["nome"]) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_data_venda" class="form-label">Data da Venda</label>
                        <input type="date" class="form-control" id="edit_data_venda" name="data_venda" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_status_entrega" class="form-label">Status da Entrega</label>
                        <select class="form-select" id="edit_status_entrega" name="status_entrega" required>
                            <option value="encomendado">Encomendado</option>
                            <option value="entregue">Entregue</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="salvarEdicaoVenda()">Salvar</button>
            </div>`;

                    // Criar/atualizar o modal
                    let modalEl = document.getElementById('editarVendaModal');
                    if (!modalEl) {
                        modalEl = document.createElement('div');
                        modalEl.className = 'modal fade';
                        modalEl.id = 'editarVendaModal';
                        modalEl.innerHTML = `<div class="modal-dialog"><div class="modal-content">${html}</div></div>`;
                        document.body.appendChild(modalEl);
                    } else {
                        modalEl.querySelector('.modal-content').innerHTML = html;
                    }

                    // Preencher os campos com os valores atuais
                    document.getElementById('edit_devedor_id').value = venda.devedor_id;
                    document.getElementById('edit_data_venda').value = venda.data_venda;
                    document.getElementById('edit_status_entrega').value = venda.status_entrega;

                    // Mostrar o modal
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                }
            });
    }

    function salvarEdicaoVenda() {
        const form = document.getElementById('formEditarVenda');
        const formData = new FormData(form);
        formData.append('acao', 'editar_simples');

        fetch("../processar/vendas.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert("Erro: " + data.message);
                }
            });
    }
</script>

<?php require_once "../includes/footer.php"; ?>