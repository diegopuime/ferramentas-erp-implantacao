<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

function limparValor($valor) {
    $valor = trim((string)$valor);
    return ($valor === '' || strtoupper($valor) === 'NULL') ? null : $valor;
}

$config = require 'config.php';
$dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}";
$user = $config['user'];
$pass = $config['pass'];

// Início do HTML
echo '<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Importação de Inventário</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f2f2f2;
            padding: 30px;
            text-align: center;
            color: #333;
        }
        .container {
            background: #fff;
            padding: 25px 40px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: inline-block;
        }
        h1 { color: #2c3e50; }
        .resultado { margin-top: 20px; font-size: 18px; }
        .botao-voltar { margin-top: 30px; }
        button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
        }
        button:hover { background-color: #2980b9; }
    </style>
</head>
<body>
<div class="container">
<h1>📦 Importação de Inventário (nrserie_deprodutos)</h1>';

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "<div class='resultado'>✅ Conectado ao banco com sucesso!</div>";
} catch (PDOException $e) {
    die("<div class='resultado'>❌ Erro ao conectar ao banco: " . $e->getMessage() . "</div>");
}

if (isset($_FILES['arquivo_excel']) && $_FILES['arquivo_excel']['error'] === UPLOAD_ERR_OK) {
    $arquivo = $_FILES['arquivo_excel']['tmp_name'];

    try {
        $pdo->beginTransaction(); // Inicia a transação

        $spreadsheet = IOFactory::load($arquivo);
        $sheet = $spreadsheet->getActiveSheet();
        $dados = $sheet->toArray();

        // 🔥 Limpa tabela antes da importação
        $pdo->exec("DELETE FROM nrserie_deprodutos");
        echo "<div class='resultado'>🧹 Tabela nrserie_deprodutos limpa com sucesso!</div>";

        $inseridos = 0;
        $sequencia_id = 1; // contador automático para id_nrserie_deprodutos

        $insertSQL = "INSERT INTO nrserie_deprodutos 
            (id_empresa, id_entrada_nf, id_produto, id_nrserie_deprodutos, nrserie, temporario, origem, ESTOQUE, id_sequencia, id_controle, id_recebimento)
            VALUES 
            (:id_empresa, :id_entrada_nf, :id_produto, :id_nrserie_deprodutos, :nrserie, :temporario, :origem, :estoque, :id_sequencia, :id_controle, :id_recebimento)";
        $stmt = $pdo->prepare($insertSQL);

        foreach ($dados as $index => $linha) {
            if ($index == 0) continue; // pula cabeçalho

            // Planilha agora só tem: id_empresa | id_entrada_nf | id_produto | nrserie
            $id_empresa = limparValor($linha[0] ?? null);
            $id_entrada_nf = limparValor($linha[1] ?? null);
            $id_produto = limparValor($linha[2] ?? null);
            $nrserie = limparValor($linha[3] ?? null);

            $params = [
                'id_empresa' => $id_empresa,
                'id_entrada_nf' => $id_entrada_nf,
                'id_produto' => $id_produto,
                'id_nrserie_deprodutos' => $sequencia_id, // contador automático
                'nrserie' => $nrserie,
                'temporario' => 'F', // fixo
                'origem' => 'NFE', // fixo
                'estoque' => null, // sempre NULL
                'id_sequencia' => 0, // fixo
                'id_controle' => 0, // fixo
                'id_recebimento' => 0 // fixo
            ];

            $stmt->execute($params);
            $inseridos++;
            $sequencia_id++; // incrementa contador
        }

        // Ajusta sequência no PostgreSQL
        $stmt = $pdo->query("SELECT MAX(id_nrserie_deprodutos) FROM nrserie_deprodutos");
        $ultimo_id = $stmt->fetchColumn();

        if ($ultimo_id) {
            $pdo->exec("SELECT setval('nrserie_deprodutos_id_nrserie_deprodutos_seq', $ultimo_id, true)");
            echo "<div class='resultado'>📌 Sequência ajustada para <strong>$ultimo_id</strong></div>";
        } else {
            echo "<div class='resultado'>⚠ Nenhum ID encontrado para ajustar sequência.</div>";
        }

        $pdo->commit();
        echo "<div class='resultado'><br>✅ Importação finalizada com sucesso!</div>";
        echo "<div class='resultado'>📥 Inseridos: <strong>$inseridos</strong></div>";

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<div class='resultado'>❌ Erro durante importação: " . htmlspecialchars($e->getMessage()) . "</div>";
    }

} else {
    echo "<div class='resultado'>❌ Erro ao enviar o arquivo!</div>";
}

echo '<div class="botao-voltar"><a href="upload.php"><button>🔙 Retornar</button></a></div>';
echo '</div></body></html>';
?>
