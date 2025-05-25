<?php
require_once "../includes/conexao.php";
require_once "../includes/header.php";

// Verifica cobranças pendentes (mesmo alerta em todas as páginas)
$sql_pendentes = "SELECT COUNT(*) as total FROM vendas v WHERE v.status = \"pendente\"";
$stmt = $conn->query($sql_pendentes);
$total_pendentes = $stmt->fetch(PDO::FETCH_ASSOC)["total"];

if ($total_pendentes > 0) {
    echo "<div class=\"alert alert-warning\">Existem $total_pendentes cobrança(s) pendente(s)!</div>";
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Produtos</h2>
    <button type="button" class="btn btn-new-debtor" data-bs-toggle="modal" data-bs-target="#novoProdutoModal">
        Novo Produto
    </button>
</div>

<!-- Campo de pesquisa -->
<div class="mb-3">
    <input type="text" class="form-control" id="pesquisaProduto" placeholder="Pesquisar produto..." onkeyup="pesquisarProduto()">
</div>


<table class="table table-striped">
    <thead>
        <tr>
            <th>Nome</th>
            <th>Descrição</th>
            <th>Preço Padrão</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $sql = "SELECT * FROM produtos ORDER BY nome";
        $stmt = $conn->query($sql);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row["nome"]) . "</td>";
            echo "<td>" . htmlspecialchars($row["descricao"]) . "</td>";
            echo "<td>R$ " . number_format($row["preco_padrao"], 2, ",", ".") . "</td>";
            echo "<td>
                    <button class=\"btn btn-sm bg-info\" onclick=\"editarProduto(" . $row["id"] . ")\">Editar</button>
                    <button class=\"btn btn-sm bg-danger\" onclick=\"excluirProduto(" . $row["id"] . ")\">Excluir</button>
                  </td>";
            echo "</tr>";
        }
        ?>
    </tbody>
</table>

<!-- Modal Novo Produto -->
<div class="modal fade" id="novoProdutoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Produto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formProduto" method="post">
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome</label>
                        <input type="text" class="form-control" id="nome" name="nome" required>
                    </div>
                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" name="descricao"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="preco_padrao" class="form-label">Preço Padrão</label>
                        <input type="number" step="0.01" class="form-control" id="preco_padrao" name="preco_padrao" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" onclick="salvarProduto()">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script>
    function pesquisarProduto() {
        const termo = document.getElementById("pesquisaProduto").value.toLowerCase();
        const tabela = document.querySelector("table tbody");
        const linhas = tabela.getElementsByTagName("tr");

        for (let linha of linhas) {
            const nome = linha.getElementsByTagName("td")[0].textContent.toLowerCase();
            const descricao = linha.getElementsByTagName("td")[1].textContent.toLowerCase();

            if (nome.includes(termo) || descricao.includes(termo)) {
                linha.style.display = "";
            } else {
                linha.style.display = "none";
            }
        }
    }

    function salvarProduto() {
        const form = document.getElementById("formProduto");
        const formData = new FormData(form);

        // Verifica se é edição ou criação
        if (form.querySelector("input[name='id']")) {
            formData.append("acao", "editar");
        } else {
            formData.append("acao", "criar");
        }

        fetch("../processar/produtos.php", {
                method: "POST",
                body: formData
            })
            .then(response => {
                // Verificar se a resposta tem formato JSON válido
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.includes("application/json")) {
                    return response.json();
                }
                // Se não for JSON, capture o texto para depuração
                throw new Error("Formato de resposta inválido. Verifique os logs do servidor.");
            })
            .then(data => {
                if (data.status === "success") {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert("Erro: " + data.message);
                }
            })
            .catch(error => {
                alert("Erro ao processar requisição: " + error.message);
                console.error(error);
            });
    }

    function editarProduto(id) {
        fetch("../processar/produtos.php", {
                method: "POST",
                body: new URLSearchParams({
                    acao: "buscar",
                    id: id
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    const produto = data.data;

                    // Preenche o formulário
                    document.getElementById("nome").value = produto.nome;
                    document.getElementById("descricao").value = produto.descricao;
                    document.getElementById("preco_padrao").value = produto.preco_padrao;

                    // Adiciona campo hidden com ID
                    const form = document.getElementById("formProduto");
                    let idInput = form.querySelector("input[name='id']");
                    if (!idInput) {
                        idInput = document.createElement("input");
                        idInput.type = "hidden";
                        idInput.name = "id";
                        form.appendChild(idInput);
                    }
                    idInput.value = id;

                    // Abre o modal
                    const modal = new bootstrap.Modal(document.getElementById("novoProdutoModal"));
                    modal.show();
                } else {
                    alert("Erro: " + data.message);
                }
            })
            .catch(error => {
                alert("Erro ao buscar dados do produto");
                console.error(error);
            });
    }

    function excluirProduto(id) {
        if (confirm("Tem certeza que deseja excluir este produto?")) {
            fetch("../processar/produtos.php", {
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
                    alert("Erro ao excluir produto");
                    console.error(error);
                });
        }
    }
</script>
<?php require_once "../includes/footer.php"; ?>