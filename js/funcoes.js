// Funções para Devedores
function salvarDevedor() {
    const form = document.getElementById("formDevedor");
    const formData = new FormData(form);
    formData.append("acao", "criar");

    fetch("/processar/devedores.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === "success") {
            alert(data.message);
            window.location.reload();
        } else {
            alert("Erro: " + data.message);
        }
    });
}

function editarDevedor(id) {
    fetch("/processar/devedores.php", {
        method: "POST",
        body: new URLSearchParams({
            acao: "buscar",
            id: id
        })
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === "success") {
            const devedor = data.data;
            document.getElementById("nome").value = devedor.nome;
            document.getElementById("telefone").value = devedor.telefone;
            document.getElementById("email").value = devedor.email;
            document.getElementById("endereco").value = devedor.endereco;
            
            // Adiciona o ID ao form para identificar que é uma edição
            const form = document.getElementById("formDevedor");
            const idInput = document.createElement("input");
            idInput.type = "hidden";
            idInput.name = "id";
            idInput.value = id;
            form.appendChild(idInput);
            
            // Abre o modal
            new bootstrap.Modal(document.getElementById("novoDevedorModal")).show();
        }
    });
}

function excluirDevedor(id) {
    if(confirm("Tem certeza que deseja excluir este devedor?")) {
        fetch("/processar/devedores.php", {
            method: "POST",
            body: new URLSearchParams({
                acao: "excluir",
                id: id
            })
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if(data.status === "success") {
                window.location.reload();
            }
        });
    }
}

// Funções para Produtos
function salvarProduto() {
    const form = document.getElementById("formProduto");
    const formData = new FormData(form);
    formData.append("acao", "criar");

    fetch("/processar/produtos.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === "success") {
            alert(data.message);
            window.location.reload();
        } else {
            alert("Erro: " + data.message);
        }
    });
}

function editarProduto(id) {
    fetch("/processar/produtos.php", {
        method: "POST",
        body: new URLSearchParams({
            acao: "buscar",
            id: id
        })
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === "success") {
            const produto = data.data;
            document.getElementById("nome").value = produto.nome;
            document.getElementById("descricao").value = produto.descricao;
            document.getElementById("preco_padrao").value = produto.preco_padrao;
            
            // Adiciona o ID ao form
            const form = document.getElementById("formProduto");
            const idInput = document.createElement("input");
            idInput.type = "hidden";
            idInput.name = "id";
            idInput.value = id;
            form.appendChild(idInput);
            
            new bootstrap.Modal(document.getElementById("novoProdutoModal")).show();
        }
    });
}

function excluirProduto(id) {
    if(confirm("Tem certeza que deseja excluir este produto?")) {
        fetch("/processar/produtos.php", {
            method: "POST",
            body: new URLSearchParams({
                acao: "excluir",
                id: id
            })
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if(data.status === "success") {
                window.location.reload();
            }
        });
    }
}

// Funções para Vendas
function salvarVenda() {
    const form = document.getElementById("formVenda");
    const formData = new FormData(form);
    formData.append("acao", "criar");

    fetch("/processar/vendas.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === "success") {
            alert(data.message);
            window.location.reload();
        } else {
            alert("Erro: " + data.message);
        }
    });
}

function editarVenda(id) {
    fetch("/processar/vendas.php", {
        method: "POST",
        body: new URLSearchParams({
            acao: "buscar",
            id: id
        })
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === "success") {
            const venda = data.data;
            document.getElementById("devedor_id").value = venda.devedor_id;
            document.getElementById("produto_id").value = venda.produto_id;
            document.getElementById("quantidade").value = venda.quantidade;
            document.getElementById("valor_unitario").value = venda.valor_unitario;
            document.getElementById("data_venda").value = venda.data_venda;
            document.getElementById("data_vencimento").value = venda.data_vencimento;
            
            // Adiciona o ID ao form
            const form = document.getElementById("formVenda");
            const idInput = document.createElement("input");
            idInput.type = "hidden";
            idInput.name = "id";
            idInput.value = id;
            form.appendChild(idInput);
            
            new bootstrap.Modal(document.getElementById("novaVendaModal")).show();
        }
    });
}

function marcarComoPago(id) {
    if(confirm("Confirma que este item foi pago?")) {
        fetch("/processar/vendas.php", {
            method: "POST",
            body: new URLSearchParams({
                acao: "marcar_pago",
                id: id
            })
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if(data.status === "success") {
                window.location.reload();
            }
        });
    }
}

function excluirVenda(id) {
    if(confirm("Tem certeza que deseja excluir esta venda?")) {
        fetch("/processar/vendas.php", {
            method: "POST",
            body: new URLSearchParams({
                acao: "excluir",
                id: id
            })
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if(data.status === "success") {
                window.location.reload();
            }
        });
    }
}
