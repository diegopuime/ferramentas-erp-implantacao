<?php
// Ativa erros para depuração leve
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\RichText;

// Função para limpar valores
function limparValor($valor) {
    $valor = trim((string)$valor);
    return ($valor === '' || strtoupper($valor) === 'NULL') ? null : $valor;
}

// Função para converter datas do Excel
function formatarDataValor($valor) {
    if ($valor instanceof RichText) {
        $valor = $valor->getPlainText();
    }

    $valor = trim((string)$valor);
    if ($valor === '' || strtoupper($valor) === 'NULL') return null;

    if (is_numeric($valor)) {
        try {
            $data = Date::excelToDateTimeObject($valor);
            return $data->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }

    $dt = DateTime::createFromFormat('m/d/Y', $valor);
    if ($dt !== false) return $dt->format('Y-m-d');

    $dt = DateTime::createFromFormat('d/m/Y', $valor);
    if ($dt !== false) return $dt->format('Y-m-d');

    return null;
}

// HTML inicial
echo '<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Importação de lote_log</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f8; padding: 40px; color: #333; }
        .container { max-width: 700px; margin: auto; background: white; border-radius: 10px; padding: 30px;
                     box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        h2 { color: #007bff; }
        .msg { margin: 10px 0; padding: 10px; background-color: #f1f1f1; border-left: 5px solid #007bff; font-size: 16px; }
        .erro { border-left-color: red; background-color: #ffeaea; }
        .sucesso { border-left-color: green; background-color: #eaffea; }
        .btn-voltar { margin-top: 30px; display: inline-block; padding: 10px 25px; font-size: 16px; background-color: #007bff;
                      color: white; text-decoration: none; border-radius: 6px; }
        .btn-voltar:hover { background-color: #0056b3; }
        .resultado { margin-top: 15px; padding: 10px; background: #f1f1f1; border-left: 5px solid #007bff; }
    </style>
</head>
<body>
<div class="container">
<h2>📦 Importação de lote_log</h2>';

// Conexão com o banco
$config = require 'config.php';
$dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}";
$user = $config['user'];
$pass = $config['pass'];

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("SET search_path TO sysemp;");
    $pdo->exec("SET datestyle TO 'ISO, DMY';");
    echo '<div class="msg sucesso">✅ Conexão com o banco estabelecida com sucesso!</div>';
} catch (PDOException $e) {
    die('<div class="msg erro">❌ Erro ao conectar ao banco: ' . $e->getMessage() . '</div>');
}

// Verifica o upload
if (!isset($_FILES['arquivo_excel_log_lote']) || $_FILES['arquivo_excel_log_lote']['error'] !== UPLOAD_ERR_OK) {
    die('<div class="msg erro">❌ Nenhum arquivo enviado ou erro no upload.</div>');
}

$arquivo = $_FILES['arquivo_excel_log_lote']['tmp_name'];

try {
    $spreadsheet = IOFactory::load($arquivo);
    $sheet = $spreadsheet->getActiveSheet();
    $dados = $sheet->toArray(null, true, true, true); // mantém colunas A, B, C...

    $pdo->beginTransaction();
    $pdo->exec("DELETE FROM lote_log;");
    echo '<div class="msg sucesso">🧹 Tabela lote_log limpa com sucesso!</div>';

    $insertSQL = "INSERT INTO lote_log 
        (data, dthr_atualizacao, estoque, id_empresa, id_origem, id_produto, id_sequencia, id_sequencia_estoque, lote, origem, qtde, usuario, data_fabricacao)
        VALUES 
        (:data, :dthr_atualizacao, :estoque, :id_empresa, :id_origem, :id_produto, :id_sequencia, :id_sequencia_estoque, :lote, :origem, :qtde, :usuario, :data_fabricacao)";

    $stmt = $pdo->prepare($insertSQL);
    $inseridos = 0;
    $sequencia = 1;
    $dataAtual = date('Y-m-d');

    foreach ($dados as $index => $linha) {
        if ($index == 1) continue; // pular cabeçalho

        $params = [
            'data' => $dataAtual,
            'dthr_atualizacao' => $dataAtual,
            'estoque' => 0,
            'id_empresa' => limparValor($linha['A'] ?? null),
            'id_origem' => 0,
            'id_produto' => limparValor($linha['B'] ?? null),
            'id_sequencia' => $sequencia,
            'id_sequencia_estoque' => 0,
            'lote' => limparValor($linha['C'] ?? null),
            'origem' => 'LOT',
            'qtde' => limparValor($linha['D'] ?? null),
            'usuario' => 'SYSEMP',
            'data_fabricacao' => formatarDataValor($linha['E'] ?? null),
        ];

        $stmt->execute($params);
        $sequencia++;
        $inseridos++;
    }

    $pdo->commit();
    echo "<div class='msg sucesso'>✅ Importação concluída com sucesso! Registros inseridos: <strong>{$inseridos}</strong></div>";

    // Ajustar sequência
    try {
        $stmtSeq = $pdo->query("SELECT MAX(id_sequencia) FROM sysemp.lote_log");
        $ultimo_id = $stmtSeq->fetchColumn();

        if ($ultimo_id) {
            $pdo->exec("SELECT setval('gen_lote_log', $ultimo_id, true)");
            echo "<div class='resultado'>📌 Sequência ajustada para <strong>$ultimo_id</strong></div>";
        } else {
            echo "<div class='resultado'>⚠ Nenhum ID encontrado para ajustar a sequência.</div>";
        }
    } catch (Exception $e) {
        echo "<div class='resultado'>❌ Erro ao ajustar sequência: " . htmlspecialchars($e->getMessage()) . "</div>";
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo '<div class="msg erro">❌ Erro ao processar importação: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

echo '<a class="btn-voltar" href="upload.php">🔙 Retornar</a>';
echo '</div></body></html>';
?>
