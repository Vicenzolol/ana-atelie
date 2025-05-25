<?php
date_default_timezone_set('America/Sao_Paulo');
setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'pt_BR.utf-8', 'portuguese');
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ana Ateliê - Sistema de Cobranças</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark mb-4" style="background-color:#a4636c;">
        <div class="container">
            <a class="navbar-brand" href="/">Ana Ateliê</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="../pages/devedores.php">Devedores</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../pages/produtos.php">Produtos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../pages/vendas.php">Vendas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../pages/pagamentos.php">Pagamentos</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container">