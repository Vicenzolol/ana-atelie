<?php
require_once "../includes/conexao.php";
require_once "../includes/header.php";

// Verifica se existem cobranças pendentes
$sql_pendentes = "SELECT COUNT(*) as total FROM vendas v
                 JOIN devedores d ON v.devedor_id = d.id
                 WHERE v.status = \"pendente        \"";
$stmt = $conn->query($sql_pendentes);
$total_pendentes = $stmt->fetch(PDO::FETCH_ASSOC)["total"];

if ($total_pendentes > 0) {
    echo "<div class=\"alert alert-warning\">Existem $total_pendentes cobrança(s) pendente(s)!</div>";
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Devedores</h2>
    <button type="button" class="btn btn-new-debtor" data-bs-toggle="modal" data-bs-target="#novoDevedorModal">
        Novo Devedor
    </button>
</div>

<!-- Adicionar campo de pesquisa -->
<div class="mb-3">
    <input type="text" class="form-control" id="pesquisaDevedor" placeholder="Pesquisar devedor..." onkeyup="pesquisarDevedor()">
</div>


<table class="table table-striped">
    <thead>
        <tr>
            <th>Nome</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $sql = "SELECT * FROM devedores ORDER BY nome";
        $stmt = $conn->query($sql);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['nome']) . "</td>";
            echo "<td>
                <button class='btn btn-sm btn-edit' onclick='editarDevedor(" . $row['id'] . ")'>Editar</button>
                <button class='btn btn-sm bg-danger' onclick='excluirDevedor(" . $row['id'] . ")'>Excluir</button>
              </td>";
            echo "</tr>";
        }
        ?>
    </tbody>
</table>

<!-- Simplificar o modal -->
<div class="modal fade" id="novoDevedorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title ">Novo Devedor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formDevedor" method="post">
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome</label>
                        <input type="text" class="form-control" id="nome" name="nome" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" onclick="salvarDevedor()">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script>
    function pesquisarDevedor() {
        const termo = document.getElementById("pesquisaDevedor").value.toLowerCase();
        const tabela = document.querySelector("table tbody");
        const linhas = tabela.getElementsByTagName("tr");

        for (let linha of linhas) {
            const nome = linha.getElementsByTagName("td")[0].textContent.toLowerCase();
            if (nome.includes(termo)) {
                linha.style.display = "";
            } else {
                linha.style.display = "none";
            }
        }
    }

    function salvarDevedor() {
        const form = document.getElementById("formDevedor");
        const formData = new FormData(form);

        // Verifica se é uma edição ou criação
        if (form.querySelector("input[name='id']")) {
            formData.append("acao", "editar");
        } else {
            formData.append("acao", "criar");
        }

        fetch("../processar/devedores.php", {
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

    function editarDevedor(id) {
        fetch("../processar/devedores.php", {
                method: "POST",
                body: new URLSearchParams({
                    acao: "buscar",
                    id: id
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    const devedor = data.data;

                    // Preenche apenas o campo nome
                    document.getElementById("nome").value = devedor.nome;

                    // Adiciona campo hidden com ID
                    const form = document.getElementById("formDevedor");
                    let idInput = form.querySelector("input[name='id']");
                    if (!idInput) {
                        idInput = document.createElement("input");
                        idInput.type = "hidden";
                        idInput.name = "id";
                        form.appendChild(idInput);
                    }
                    idInput.value = id;

                    // Abre o modal
                    const modal = new bootstrap.Modal(document.getElementById("novoDevedorModal"));
                    modal.show();
                } else {
                    alert("Erro: " + data.message);
                }
            })
            .catch(error => {
                alert("Erro ao buscar dados do devedor");
                console.error(error);
            });
    }

    function buscarDevedor(id) {
        fetch("../processar/devedores.php", {
                method: "POST",
                body: new URLSearchParams({
                    acao: "buscar",
                    id: id
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    const devedor = data.data;
                    console.log("Dados do devedor:", devedor);
                    // Aqui você pode usar os dados do devedor
                    // Por exemplo: devedor.nome
                } else {
                    alert("Erro: " + data.message);
                }
            })
            .catch(error => {
                alert("Erro ao buscar dados do devedor");
                console.error(error);
            });
    }

    function excluirDevedor(id) {
        if (confirm("Tem certeza que deseja excluir este devedor?")) {
            fetch("../processar/devedores.php", {
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
                    alert("Erro ao excluir devedor");
                    console.error(error);
                });
        }
    }
</script>

<?php require_once "../includes/footer.php"; ?>