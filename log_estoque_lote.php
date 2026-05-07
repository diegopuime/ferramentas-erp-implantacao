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

// Função para converter datas do Excel
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

    // Tenta ler string no formato m/d/Y
    $dt = DateTime::createFromFormat('m/d/Y', $valor);
    if ($dt !== false) return $dt->format('Y-m-d');

    // Tenta ler string no formato d/m/Y
    $dt = DateTime::createFromFormat('d/m/Y', $valor);
    if ($dt !== false) return $dt->format('Y-m-d');

    return null;
}

// HTML inicial
echo '<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Importação log_dos_lotes</title>
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
        pre { background: #f1f1f1; padding: 10px; border-radius: 6px; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        input[type="file"] { margin-top: 10px; }
        button { margin-top: 20px; padding: 10px 25px; font-size: 16px; background-color: #007bff; color: white; border: none; border-radius: 6px; cursor: pointer; }
        button:hover { background-color: #0056b3; }
    </style>
</head>
<body>
<div class="container">
<h2>📤 Upload e Importação log_dos_lotes</h2>';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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

    if (!isset($_FILES['arquivo_excel_log_dos_lotes']) || $_FILES['arquivo_excel_log_dos_lotes']['error'] !== UPLOAD_ERR_OK) {
        echo '<pre>';
        print_r($_FILES);
        echo '</pre>';
        die('<div class="msg erro">❌ Nenhum arquivo enviado ou erro no upload.</div>');
    }

    $arquivo = $_FILES['arquivo_excel_log_dos_lotes']['tmp_name'];

    try {
        $spreadsheet = IOFactory::load($arquivo);
        $sheet = $spreadsheet->getActiveSheet();
        $dados = $sheet->toArray(null, true, true, true); // colunas A,B,C...

        echo '<div class="msg sucesso">📄 Arquivo Excel lido com sucesso!</div>';

        // DEBUG: mostra valores lidos
        echo '<h3>📊 Dados lidos:</h3><pre>';
        foreach ($dados as $linhaIndex => $linha) {
            echo "Linha $linhaIndex:\n";
            foreach ($linha as $col => $valor) {
                $debugValor = $valor instanceof RichText ? $valor->getPlainText() : $valor;
                echo "  Coluna $col: " . var_export($debugValor, true) . "\n";
            }
        }
        echo '</pre>';

        $pdo->beginTransaction();
        $pdo->exec("DELETE FROM log_dos_lotes;");
        echo '<div class="msg sucesso">🧹 Tabela log_dos_loteslimpa com sucesso!</div>';

        $insertSQL = "INSERT INTO log_dos_lotes
            (data, dthr_atualizacao, estoque, idempresa, origem, idproduto, idsequencia, idsequenciaestoque, lote, origem2, qtde, usuario, datafab)
            VALUES (:data, :dthr_atualizacao, :estoque, :idempresa, :origem, :idproduto, :idsequencia, :idsequenciaestoque, :lote, :origem2, :qtde, :usuario, :datafab)";

        $stmt = $pdo->prepare($insertSQL);
        $inseridos = 0;

        foreach ($dados as $index => $linha) {
            if ($index == 1) continue; // pula cabeçalho

            $params = [
                'data' => formatarDataValor($linha['A'] ?? null),
                'dthr_atualizacao' => formatarDataValor($linha['B'] ?? null),
                'estoque' => limparValor($linha['C'] ?? null),
                'idempresa' => limparValor($linha['D'] ?? null),
                'origem' => limparValor($linha['E'] ?? null),
                'idproduto' => limparValor($linha['F'] ?? null),
                'idsequencia' => limparValor($linha['G'] ?? null),
                'idsequenciaestoque' => limparValor($linha['H'] ?? null),
                'lote' => limparValor($linha['I'] ?? null),
                'origem2' => limparValor($linha['J'] ?? null),
                'qtde' => limparValor($linha['K'] ?? null),
                'usuario' => limparValor($linha['L'] ?? null),
                'datafab' => formatarDataValor($linha['M'] ?? null),
            ];

            // DEBUG: mostra parâmetros
            echo '<pre>INSERT LINHA ' . $index . ' PARAMS: ' . var_export($params, true) . "</pre>";

            $stmt->execute($params);
            $inseridos++;
        }

        $pdo->commit();
        echo "<div class='msg sucesso'>✅ Importação concluída! Registros inseridos: <strong>{$inseridos}</strong></div>";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo '<div class="msg erro">❌ Erro ao processar importação: ' . $e->getMessage() . '</div>';
    }

    echo '<a class="btn-voltar" href="">🔙 Retornar</a>';

} else {
    // Mostra formulário se não enviou arquivo ainda
    echo '<form method="post" enctype="multipart/form-data">
            <label>Escolha o arquivo Excel:</label>
            <input type="file" name="arquivo_excel_log_dos_lotes" accept=".xlsx,.xls" required>
            <button type="submit">Enviar</button>
          </form>';
}

echo '</div></body></html>';
?>
