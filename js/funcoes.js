// Funções para Devedores
function salvarDevedor() {
  const form = document.getElementById("formDevedor");
  const formData = new FormData(form);
  formData.append("acao", "criar");

  fetch("/processar/devedores.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
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
      id: id,
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
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
  if (confirm("Tem certeza que deseja excluir este devedor?")) {
    fetch("/processar/devedores.php", {
      method: "POST",
      body: new URLSearchParams({
        acao: "excluir",
        id: id,
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        alert(data.message);
        if (data.status === "success") {
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
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
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
      id: id,
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
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
  if (confirm("Tem certeza que deseja excluir este produto?")) {
    fetch("/processar/produtos.php", {
      method: "POST",
      body: new URLSearchParams({
        acao: "excluir",
        id: id,
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        alert(data.message);
        if (data.status === "success") {
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
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
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
      id: id,
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "success") {
        const venda = data.data;
        document.getElementById("devedor_id").value = venda.devedor_id;
        document.getElementById("produto_id").value = venda.produto_id;
        document.getElementById("quantidade").value = venda.quantidade;
        document.getElementById("valor_unitario").value = venda.valor_unitario;
        document.getElementById("data_venda").value = venda.data_venda;
        document.getElementById("data_vencimento").value =
          venda.data_vencimento;

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
  if (confirm("Confirma que este item foi pago?")) {
    fetch("/processar/vendas.php", {
      method: "POST",
      body: new URLSearchParams({
        acao: "marcar_pago",
        id: id,
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        alert(data.message);
        if (data.status === "success") {
          window.location.reload();
        }
      });
  }
}

function excluirVenda(id) {
  if (confirm("Tem certeza que deseja excluir esta venda?")) {
    fetch("/processar/vendas.php", {
      method: "POST",
      body: new URLSearchParams({
        acao: "excluir",
        id: id,
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        alert(data.message);
        if (data.status === "success") {
          window.location.reload();
        }
      });
  }
}

// Adicionar esta função auxiliar no início do arquivo
function formatarMoedaParaNumero(valor) {
  if (typeof valor === "number") return valor;

  // Remove o símbolo da moeda e pontos, substitui vírgula por ponto
  return parseFloat(
    valor
      .replace(/[^\d,.-]/g, "") // Remove tudo exceto dígitos, vírgulas, pontos e sinais
      .replace(/\./g, "") // Remove pontos
      .replace(",", ".") // Substitui vírgula por ponto
  );
}

// Nova função para registrar pagamento que usa a função auxiliar
function confirmarPagamento() {
  const id = document.getElementById("pagamento_venda_id").value;
  const valor = parseFloat(document.getElementById("valor_pagamento").value);
  const data = document.getElementById("data_pagamento").value;

  try {
    // Obter o valor restante usando a função auxiliar
    const elementoValorRestante = document.getElementById(
      "valor_restante_" + id
    );
    const valorRestante = formatarMoedaParaNumero(
      elementoValorRestante.textContent
    );

    if (!valor || valor <= 0) {
      alert("Por favor, informe um valor válido");
      return;
    }

    if (valor > valorRestante) {
      alert(
        `O valor do pagamento (R$ ${valor.toLocaleString("pt-BR", {
          minimumFractionDigits: 2,
        })}) não pode ser maior que o valor restante (R$ ${valorRestante.toLocaleString(
          "pt-BR",
          { minimumFractionDigits: 2 }
        )})`
      );
      return;
    }

    fetch("/processar/pagamentos.php", {
      method: "POST",
      body: new URLSearchParams({
        acao: "registrar_pagamento",
        id: id,
        valor: valor,
        data_pagamento: data,
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.status === "success") {
          alert(data.message);
          window.location.reload();
        } else {
          throw new Error(data.message);
        }
      })
      .catch((error) => {
        alert("Erro ao registrar pagamento: " + error.message);
        console.error(error);
      });
  } catch (error) {
    alert("Erro ao processar dados: " + error.message);
    console.error(error);
  }
}
