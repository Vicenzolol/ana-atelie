
CREATE TABLE devedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    telefone VARCHAR(20),
    email VARCHAR(100),
    endereco TEXT,
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    preco_padrao DECIMAL(10,2),
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE vendas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    devedor_id INT,
    produto_id INT,
    quantidade INT NOT NULL,
    valor_unitario DECIMAL(10,2) NOT NULL,
    data_venda DATE NOT NULL,
    data_vencimento DATE NOT NULL,
    status ENUM('pendente', 'pago') DEFAULT 'pendente',
    FOREIGN KEY (devedor_id) REFERENCES devedores(id),
    FOREIGN KEY (produto_id) REFERENCES produtos(id)
);
