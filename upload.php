<?php
session_start();

include 'conexao.php';
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ferramenta ERP - Importações e Conferências</title>

    <style>

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f9f9f9;
            color: #333;
            padding: 20px;
            max-width: 900px;
            margin: auto;
        }

        h1, h2 {
            color: #2c3e50;
        }

        h1 {
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }

        section {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        button {
            background-color: #3498db;
            border: none;
            color: white;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 10px;
        }

        button:hover {
            background-color: #2980b9;
        }

        input[type="file"],
        select,
        input[type="number"] {

            margin-top: 10px;
            padding: 6px;
            border-radius: 5px;
            border: 1px solid #ccc;
            min-width: 250px;
        }

        .legenda {

            font-size: 0.95em;
            color: #555;
            background: #f1f1f1;
            padding: 12px;
            border-radius: 6px;
            margin-top: 15px;
            line-height: 1.6;
        }

        .legenda strong {
            color: #2c3e50;
        }

        .descricao-sistema {
            background: #ecf5ff;
            border-left: 5px solid #3498db;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 30px;
            line-height: 1.7;
        }

        footer {
            text-align: center;
            color: #777;
            margin-top: 40px;
            font-size: 14px;
        }

    </style>

</head>

<body>

<h1>
    📦 Ferramenta ERP - Importações e Conferências
</h1>

<div class="descricao-sistema">

    <strong>Projeto desenvolvido em PHP + PostgreSQL</strong><br><br>

    Sistema criado para automatizar processos de:
    importação de planilhas Excel,
    validações operacionais,
    conferências de estoque,
    conferência de preços,
    controle de lotes e números de série,
    além de validações de integração com marketplaces.

</div>

<section>

    <p>
        <strong>📘 Documentação:</strong>
        Baixe o detalhamento do funcionamento das importações.
    </p>

    <a href="modelos/detalhamento importacao.docx" download>
        <button>📥 Baixar Documentação</button>
    </a>

</section>

<!-- IMPORTAÇÕES -->

<section>

    <h2>📦 Importação de Inventário por Número de Série</h2>

    <a href="modelos/produto_inventario_nrserie.xlsx" download>
        <button>📥 Planilha Modelo</button>
    </a>

    <form action="processa_importacao.php" method="POST" enctype="multipart/form-data">

        <input type="file" name="arquivo_excel" required><br>

        <button type="submit">Enviar Arquivo</button>

    </form>

</section>

<section>

    <h2>📋 Importação de Movimentação de Estoque por Série</h2>

    <a href="modelos/log_estoque_nrserie.xlsx" download>
        <button>📥 Planilha Modelo</button>
    </a>

    <form action="processa_importacao_log.php" method="post" enctype="multipart/form-data">

        <input type="file" name="arquivo_excel_log" required><br>

        <button type="submit">Enviar Arquivo</button>

    </form>

</section>

<section>

    <h2>🧾 Importação de Histórico de Lotes e Séries</h2>

    <a href="modelos/historico_lote_nrserie.xlsx" download>
        <button>📥 Planilha Modelo</button>
    </a>

    <form action="processa_importacao_historico.php" method="post" enctype="multipart/form-data">

        <input type="file" name="arquivo_excel_historico" required><br>

        <button type="submit">Enviar Arquivo</button>

    </form>

</section>

<section>

    <h2>📥 Importação de Itens de Entrada Fiscal</h2>

    <a href="modelos/entrada_nf_itens_nrserie.xlsx" download>
        <button>📥 Planilha Modelo</button>
    </a>

    <form action="processa_importacao_entrada_nf.php" method="post" enctype="multipart/form-data">

        <input type="file" name="arquivo_excel_entrada_nf" required><br>

        <button type="submit">Enviar Arquivo</button>

    </form>

</section>

<section>

    <h2>⚙️ Ajuste de Controle por Número de Série</h2>

    <a href="modelos/ajustar_controle_nrserie.xlsx" download>
        <button>📥 Planilha Modelo</button>
    </a>

    <form action="processa_ajuste_controle_nrserie.php" method="post" enctype="multipart/form-data">

        <input type="file" name="arquivo_excel_controle" required><br>

        <button type="submit">Enviar Arquivo</button>

    </form>

</section>

<section>

    <h2>📦 Importação de Inventário por Lote</h2>

    <a href="modelos/PRODUTO_INVENTARIO_LOTE.xlsx" download>
        <button>📥 Planilha Modelo</button>
    </a>

    <form action="processa_importacao_inventario_lote.php" method="post" enctype="multipart/form-data">

        <input type="file" name="arquivo_excel_inventario_lote" required><br>

        <button type="submit">Enviar Arquivo</button>

    </form>

</section>

<section>

    <h2>📋 Importação de Movimentação de Estoque por Lote</h2>

    <a href="modelos/log_estoque_lote.xlsx" download>
        <button>📥 Planilha Modelo</button>
    </a>

    <form action="processa_importacao_log_lote.php" method="post" enctype="multipart/form-data">

        <input type="file" name="arquivo_excel_log_lote" required><br>

        <button type="submit">Enviar Arquivo</button>

    </form>

</section>

<section>

    <h2>⚙️ Ajuste de Controle por Lote</h2>

    <a href="modelos/ajustar_controle_lote.xlsx" download>
        <button>📥 Planilha Modelo</button>
    </a>

    <form action="processa_ajuste_controle_lote.php" method="post" enctype="multipart/form-data">

        <input type="file" name="arquivo_excel_controle_lote" required><br>

        <button type="submit">Enviar Arquivo</button>

    </form>

</section>

<!-- CONFERÊNCIA ESTOQUE -->

<section>

    <h2>📊 Conferência de Estoque</h2>

    <form action="compara_estoque.php" method="post" enctype="multipart/form-data">

        <input type="file" name="arquivo_excel" required><br>

        <select name="tipo_codigo" required>

            <option value="id_produto">ID Produto</option>
            <option value="codigo_auxiliar">Código Auxiliar</option>
            <option value="cod_fabrica">Código Fabricação</option>
            <option value="cod_barra">Código de Barras</option>

        </select><br>

        <input type="number" name="id_empresa" placeholder="ID Empresa" required><br>

        <button type="submit">🔍 Conferir Estoque</button>

    </form>

</section>

<!-- CONFERÊNCIA COMPRA X VENDA -->

<section>

    <h2>💰 Conferência Compra x Venda</h2>

    <form action="confere_compra_venda.php" method="post" enctype="multipart/form-data">

        <input type="file" name="arquivo_excel" required><br>

        <select name="tipo_codigo" required>

            <option value="id_produto">ID Produto</option>
            <option value="codigo_auxiliar">Código Auxiliar</option>
            <option value="cod_fabrica">Código Fabricação</option>
            <option value="cod_barra">Código de Barras</option>

        </select><br>

        <input type="number" name="id_empresa" placeholder="ID Empresa" required><br>

        <input type="number" name="id_tb_preco" placeholder="Tabela de Preço" required><br>

        <button type="submit">🔍 Conferir Valores</button>

    </form>

</section>

<!-- CONFERÊNCIA PREÇO CONDIÇÃO -->

<section>

    <h2>💳 Conferência de Preço por Condição</h2>

    <form action="confere_preco_condicao.php" method="post" enctype="multipart/form-data">

        <input type="file" name="arquivo_excel" required><br>

        <select name="tipo_codigo" required>

            <option value="id_produto">ID Produto</option>
            <option value="codigo_auxiliar">Código Auxiliar</option>
            <option value="cod_fabrica">Código Fabricação</option>
            <option value="cod_barra">Código de Barras</option>

        </select><br>

        <input type="number" name="id_tb_preco" placeholder="Tabela de Preço" required><br>

        <input type="number" name="id_condpagto" placeholder="Condição de Pagamento" required><br>

        <button type="submit">🔍 Conferir Condição</button>

    </form>

</section>

<!-- CONFERÊNCIA SKU -->

<section>

    <h2>🛒 Conferência SKU Marketplace</h2>

    <form action="conferenciaplataformassku.php" method="post" enctype="multipart/form-data">

        <input type="file" name="arquivo" required><br>

        <select name="tipo_codigo" required>

            <option value="id_produto">ID Produto</option>
            <option value="codigo_auxiliar">Código Auxiliar</option>
            <option value="cod_fabricante">Código Fabricante</option>
            <option value="cod_barra">Código de Barras</option>

        </select><br>

        <input type="number"
               name="id_plataforma"
               placeholder="ID Plataforma"
               required><br>

        <button type="submit">
            🔍 Conferir Marketplace
        </button>

    </form>

</section>

<footer>

    PHP • PostgreSQL • HTML • CSS • Excel Automation

</footer>

</body>
</html>