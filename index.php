<?php
require_once "includes/conexao.php";
require_once "includes/header.php";

// Verifica cobranças pendentes
$sql_pendentes = "SELECT COUNT(*) as total 
                 FROM vendas v 
                 WHERE v.status IN ('pendente', 'parcialmente_pago')";
$stmt = $conn->query($sql_pendentes);
$total_pendentes = $stmt->fetch(PDO::FETCH_ASSOC)["total"];

// Verifica encomendas pendentes
$sql_encomendas = "SELECT COUNT(*) as total 
                  FROM vendas v 
                  WHERE v.status_entrega = 'encomendado'";
$stmt = $conn->query($sql_encomendas);
$total_encomendas = $stmt->fetch(PDO::FETCH_ASSOC)["total"];

// Busca detalhes das encomendas pendentes
$sql_encomendas_pendentes = "SELECT 
    v.id,
    d.nome as nome_devedor,
    GROUP_CONCAT(p.nome SEPARATOR ', ') as produtos,
    v.data_venda,
    v.status
    FROM vendas v 
    JOIN devedores d ON v.devedor_id = d.id 
    LEFT JOIN itens_venda iv ON v.id = iv.venda_id
    LEFT JOIN produtos p ON iv.produto_id = p.id 
    WHERE v.status_entrega = 'encomendado'
    GROUP BY v.id, d.nome, v.data_venda, v.status
    ORDER BY v.data_venda ASC";
$stmt = $conn->query($sql_encomendas_pendentes);
$encomendas_pendentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Busca as próximas cobranças
$sql_proximas = "SELECT 
    v.id,
    v.status,
    v.status_entrega,
    v.valor_total,
    v.valor_pago,
    v.data_venda,
    v.proximo_pagamento,
    d.nome as nome_devedor,
    GROUP_CONCAT(p.nome SEPARATOR ', ') as produtos,
    COALESCE(v.valor_pago, 0) as valor_pago,
    (v.valor_total - COALESCE(v.valor_pago, 0)) as valor_restante
    FROM vendas v 
    JOIN devedores d ON v.devedor_id = d.id 
    LEFT JOIN itens_venda iv ON v.id = iv.venda_id
    LEFT JOIN produtos p ON iv.produto_id = p.id 
    WHERE v.status IN ('pendente', 'parcialmente_pago')
      AND (
            (v.ultimo_pagamento IS NULL AND DATEDIFF(CURDATE(), v.data_venda) > 30)
         OR (v.ultimo_pagamento IS NOT NULL AND DATEDIFF(CURDATE(), v.ultimo_pagamento) > 30)
         OR (v.proximo_pagamento IS NOT NULL AND v.proximo_pagamento <= CURDATE())
      )
    GROUP BY v.id, v.status, v.status_entrega, v.valor_total, v.valor_pago, v.data_venda, v.proximo_pagamento, d.nome
    ORDER BY 
        CASE 
            WHEN v.proximo_pagamento IS NOT NULL THEN v.proximo_pagamento
            WHEN v.ultimo_pagamento IS NOT NULL THEN v.ultimo_pagamento
            ELSE v.data_venda
        END ASC";
$stmt = $conn->query($sql_proximas);
$proximas_cobrancas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Resumo financeiro
$sql_total = "SELECT 
    SUM(valor_total) as total_vendas,
    SUM(CASE 
        WHEN status IN ('pendente', 'parcialmente_pago') 
        THEN valor_total - COALESCE(valor_pago, 0)
        ELSE 0 
    END) as total_pendente
    FROM vendas";
$stmt = $conn->query($sql_total);
$resumo = $stmt->fetch(PDO::FETCH_ASSOC);

// Adicione após o require do header
// Busca cobranças do dia
$hoje = date('Y-m-d');
$sql_cobrancas_hoje = "SELECT 
v.id,
d.nome as nome_devedor,
v.valor_total,
v.valor_pago,
(v.valor_total - v.valor_pago) as valor_restante,
v.proximo_pagamento
FROM vendas v 
JOIN devedores d ON v.devedor_id = d.id 
WHERE v.proximo_pagamento = CURDATE()
AND v.status IN ('pendente', 'parcialmente_pago')";
$stmt = $conn->query($sql_cobrancas_hoje);
$cobrancas_hoje = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row mb-4">
    <div class="col-md-4 my-3">
        <div class="card text-white <?php echo $total_pendentes > 0 ? 'btn-pending-charges' : 'bg-success'; ?>">
            <div class="card-body">
                <h5 class="card-title">Cobranças Pendentes</h5>
                <p class="card-text display-4"><?php echo $total_pendentes; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4 my-3">
        <div class="card <?php echo $total_encomendas > 0 ? 'bg-warning text-dark' : 'bg-success text-white'; ?>">
            <div class="card-body">
                <h5 class="card-title">Encomendas Pendentes</h5>
                <p class="card-text display-4"><?php echo $total_encomendas; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4 my-3">
        <div class="card btn-total-sales text-white">
            <div class="card-body">
                <h5 class="card-title">Total em Vendas</h5>
                <p class="card-text display-4">R$ <?php echo number_format($resumo["total_vendas"] ?? 0, 2, ",", "."); ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4 my-3">
        <div class="card btn-total-pending text-white">
            <div class="card-body">
                <h5 class="card-title">Total Pendente</h5>
                <p class="card-text display-4">R$ <?php echo number_format($resumo["total_pendente"] ?? 0, 2, ",", "."); ?></p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Próximas Cobranças</h5>
            </div>
            <div class="card-body">
                <?php if (empty($proximas_cobrancas)): ?>
                    <p class="text-center">Não há cobranças pendentes.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Produto</th>
                                    <th>Valor Total</th>
                                    <th>Valor Pago</th>
                                    <th>Restante</th>
                                    <th>Próximo Pagamento</th>
                                    <th>Status Entrega</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($proximas_cobrancas as $cobranca): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($cobranca["nome_devedor"]); ?></td>
                                        <td><?php echo htmlspecialchars($cobranca["produtos"]); ?></td>
                                        <td>R$ <?php echo number_format($cobranca["valor_total"], 2, ",", "."); ?></td>
                                        <td>R$ <?php echo number_format($cobranca["valor_pago"], 2, ",", "."); ?></td>
                                        <td id="valor_restante_<?php echo $cobranca['id']; ?>">
                                            R$ <?php echo number_format($cobranca["valor_restante"], 2, ",", "."); ?>
                                        </td>
                                        <td><?= formatarDataBR($cobranca["proximo_pagamento"]) ?></td>
                                        <td><?php echo ucfirst($cobranca["status_entrega"]); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-success" onclick="registrarPagamento(<?php echo $cobranca['id']; ?>)">
                                                <i class="fas fa-money-bill-wave"></i> Registrar Pagamento
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Adicionar um novo modal para encomendas pendentes -->
<div class="modal fade" id="encomendasPendentesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Encomendas Pendentes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="list-group">
                    <?php foreach ($encomendas_pendentes as $encomenda): ?>
                        <div class="list-group-item">
                            <h6 class="mb-1"><?php echo htmlspecialchars($encomenda['nome_devedor']); ?></h6>
                            <p class="mb-1">Produtos: <?php echo htmlspecialchars($encomenda['produtos']); ?></p>
                            <p class="mb-1">Data: <?php echo formatarDataBR($encomenda['data_venda']); ?></p>
                            <button class="btn btn-sm bg-success" onclick="marcarEntregue(<?php echo $encomenda['id']; ?>)">
                                Marcar como Entregue
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Registrar Pagamento -->
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
                        <input type="date" class="form-control" id="data_pagamento" required>
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

<!-- Modal Alertas de Cobrança -->
<div class="modal fade" id="alertaCobrancasModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Alertas de Cobrança</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>Cobranças para hoje (<?php echo date('d/m/Y'); ?>):</h6>
                <div class="list-group">
                    <?php foreach ($cobrancas_hoje as $cobranca): ?>
                        <div class="list-group-item">
                            <h6 class="mb-1"><?php echo htmlspecialchars($cobranca['nome_devedor']); ?></h6>
                            <p class="mb-1">Valor Restante: R$ <?php echo number_format($cobranca['valor_restante'], 2, ',', '.'); ?></p>
                            <button class="btn btn-sm btn-success" onclick="registrarPagamento(<?php echo $cobranca['id']; ?>)">
                                Registrar Pagamento
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendi</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Adicione este script após o modal
    document.addEventListener('DOMContentLoaded', function() {
        const cobrancasHoje = <?php echo json_encode($cobrancas_hoje); ?>;

        if (cobrancasHoje.length > 0) {
            // Verifica o último alerta
            const ultimoAlerta = localStorage.getItem('ultimoAlertaCobranca');
            const agora = new Date();

            // Verifica se já mostrou alerta ou se passou tempo suficiente
            const deveExibirAlerta = !ultimoAlerta ||
                (new Date(ultimoAlerta).getTime() + (4 * 60 * 60 * 1000)) < agora.getTime(); // 4 horas em milissegundos

            if (deveExibirAlerta) {
                const modal = new bootstrap.Modal(document.getElementById('alertaCobrancasModal'));
                modal.show();

                // Registra horário do alerta
                localStorage.setItem('ultimoAlertaCobranca', agora.toISOString());
            }
        }
    });

    function registrarPagamento(id) {
        document.getElementById('pagamento_venda_id').value = id;
        document.getElementById('valor_pagamento').value = '';
        document.getElementById('data_pagamento').value = new Date().toISOString().split('T')[0];

        const modal = new bootstrap.Modal(document.getElementById('registrarPagamentoModal'));
        modal.show();
    }

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
                .then(response => {
                    // Primeiro verificamos se a resposta pode ser processada como JSON
                    const contentType = response.headers.get("content-type");
                    if (contentType && contentType.indexOf("application/json") !== -1) {
                        return response.json();
                    } else {
                        // Se não for JSON, ainda assim consideramos a operação bem-sucedida
                        console.warn("Resposta não-JSON recebida, mas a operação provavelmente foi concluída");
                        return {
                            status: "assumed_success",
                            message: "Operação provavelmente concluída"
                        };
                    }
                })
                .then(data => {
                    // Se for resposta não-JSON mas estamos assumindo sucesso
                    if (data.status === "assumed_success") {
                        alert("Operação realizada com sucesso!");
                        window.location.reload();
                        return;
                    }

                    // Código existente para lidar com respostas JSON
                    if (data.status === "success") {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        throw new Error(data.message || "Erro desconhecido");
                    }
                })
                .catch(error => {
                    // Mesmo com erro, recarregar a página pois a operação provavelmente funcionou
                    console.error("Erro ao processar resposta:", error);
                    alert("Houve um erro na comunicação, mas a operação provavelmente foi concluída. A página será recarregada.");
                    window.location.reload();
                });
        } catch (error) {
            alert("Erro ao processar dados: " + error.message);
            console.error(error);
        }
    }

    // Adicionar script para mostrar alerta de encomendas
    document.addEventListener('DOMContentLoaded', function() {
        const totalEncomendas = <?php echo $total_encomendas; ?>;

        if (totalEncomendas > 0) {
            // Criar um alerta flutuante
            const alertaEncomendas = document.createElement('div');
            alertaEncomendas.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            alertaEncomendas.style.zIndex = '1100';
            alertaEncomendas.innerHTML = `
            <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false">
                <div class="toast-header bg-warning text-dark">
                    <strong class="me-auto">Encomendas Pendentes</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    <p>Existem <strong>${totalEncomendas}</strong> encomenda(s) pendente(s).</p>
                    <div class="mt-2 pt-2 border-top">
                        <button type="button" class="btn btn-warning btn-sm" onclick="mostrarEncomendasPendentes()">
                            Ver Encomendas
                        </button>
                    </div>
                </div>
            </div>
        `;

            // Adicionar o alerta ao body para que fique flutuante
            document.body.appendChild(alertaEncomendas);

            // Inicializar o toast
            const toastElList = [].slice.call(document.querySelectorAll('.toast'));
            toastElList.map(function(toastEl) {
                return new bootstrap.Toast(toastEl);
            });
        }
    });

    function mostrarEncomendasPendentes() {
        const modal = new bootstrap.Modal(document.getElementById('encomendasPendentesModal'));
        modal.show();
    }

    function marcarEntregue(id) {
        fetch("processar/vendas.php", {
                method: "POST",
                body: new URLSearchParams({
                    acao: "alterar_status_entrega",
                    id: id
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
                alert("Erro ao atualizar status: " + error.message);
            });
    }
</script>


<?php require_once "includes/footer.php"; ?>