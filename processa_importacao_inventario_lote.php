<?php
// Ativa erros para depuração
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

// Função robusta para converter datas do Excel
function formatarDataValor($valor) {
    if ($valor instanceof RichText) {
        $valor = $valor->getPlainText();
    }

    $valor = trim((string)$valor);
    if ($valor === '' || strtoupper($valor) === 'NULL') return null;

    // Se for número (serial do Excel)
    if (is_numeric($valor)) {
        try {
            $data = Date::excelToDateTimeObject($valor);
            return $data->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }

    // Tenta ler string no formato m/d/Y (Excel padrão americano)
    $dt = DateTime::createFromFormat('m/d/Y', $valor);
    if ($dt !== false) return $dt->format('Y-m-d');

    // Tenta ler string no formato d/m/Y (caso usuário tenha digitado manualmente)
    $dt = DateTime::createFromFormat('d/m/Y', $valor);
    if ($dt !== false) return $dt->format('Y-m-d');

    return null;
}

// HTML inicial
echo '<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Importação de lotes_produtos</title>
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
<h2>📦 Importação de lotes_produtos</h2>';

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
    die('<div class="msg erro">❌ Erro ao conectar ao banco: ' . htmlspecialchars($e->getMessage()) . '</div>');
}

// Checa arquivo enviado
if (!isset($_FILES['arquivo_excel_inventario_lote']) || $_FILES['arquivo_excel_inventario_lote']['error'] !== UPLOAD_ERR_OK) {
    die('<div class="msg erro">❌ Nenhum arquivo enviado ou erro no upload.</div>');
}

$arquivo = $_FILES['arquivo_excel_inventario_lote']['tmp_name'];

// Carrega planilha
try {
    $spreadsheet = IOFactory::load($arquivo);
    $sheet = $spreadsheet->getActiveSheet();
    $dados = $sheet->toArray(null, true, true, true); // mantém colunas como A,B,C...

    echo '<div class="msg sucesso">📄 Arquivo Excel lido com sucesso!</div>';

    $pdo->beginTransaction();
    $pdo->exec("DELETE FROM lotes_produtos;");
    echo '<div class="msg sucesso">🧹 Tabela lotes_produtos limpa com sucesso!</div>';

    $insertSQL = "INSERT INTO lotes_produtos 
        (data, estoque, id_empresa, id_origem, id_produto, id_sequencia, id_sequencia_inventario, lote, origem_mercadoria, vencimento, origem, data_fabricacao, larguralote)
        VALUES (:data, :estoque, :id_empresa, :id_origem, :id_produto, :id_sequencia, :id_sequencia_inventario, :lote, :origem_mercadoria, :vencimento, :origem, :data_fabricacao, :larguralote)";

    $stmt = $pdo->prepare($insertSQL);
    $inseridos = 0;
    $id_origem_seq = 1;
    $id_sequencia_seq = 1;

    foreach ($dados as $index => $linha) {
        if ($index == 1) continue; // pular cabeçalho

        $params = [
            'data' => date('Y-m-d'), // data atual
            'estoque' => limparValor($linha['A'] ?? null),
            'id_empresa' => limparValor($linha['B'] ?? null),
            'id_origem' => $id_origem_seq,
            'id_produto' => limparValor($linha['C'] ?? null),
            'id_sequencia' => $id_sequencia_seq,
            'id_sequencia_inventario' => 0, // fixo
            'lote' => limparValor($linha['D'] ?? null),
            'origem_mercadoria' => 0, // fixo
            'vencimento' => formatarDataValor($linha['E'] ?? null),
            'origem' => 0, // fixo
            'data_fabricacao' => formatarDataValor($linha['F'] ?? null),
            'larguralote' => 0, // fixo
        ];

        $stmt->execute($params);
        $inseridos++;

        // incrementa para próxima linha
        $id_origem_seq++;
        $id_sequencia_seq++;
    }

    $pdo->commit();
    echo "<div class='msg sucesso'>✅ Importação concluída com sucesso! Registros inseridos: <strong>{$inseridos}</strong></div>";

    // --- Ajuste da sequência ---
    try {
        $stmt = $pdo->query("SELECT MAX(id_sequencia) FROM lotes_produtos");
        $ultimo_id = $stmt->fetchColumn();

        if ($ultimo_id) {
            $pdo->exec("SELECT setval('gen_lotes_produtos', $ultimo_id, true)");
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
