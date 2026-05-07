<?php
// Ativa erros para depuração
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// HTML: estrutura visual
echo '<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Importação - compra_nrserie</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f8; padding: 40px; color: #333; }
        .container { max-width: 700px; margin: auto; background: white; border-radius: 10px; padding: 30px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        h2 { color: #007bff; }
        .msg { margin: 10px 0; padding: 10px; background-color: #f1f1f1; border-left: 5px solid #007bff; font-size: 16px; }
        .erro { border-left-color: red; background-color: #ffeaea; }
        .sucesso { border-left-color: green; background-color: #eaffea; }
        .aviso { border-left-color: orange; background-color: #fff3cd; }
        .btn-voltar { margin-top: 30px; display: inline-block; padding: 10px 25px; font-size: 16px; background-color: #007bff; color: white; text-decoration: none; border-radius: 6px; }
        .btn-voltar:hover { background-color: #0056b3; }
        pre { background-color: #f9f9f9; padding: 10px; overflow-x: auto; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="container">
<h2>📥 Importação de compra_nrserie</h2>
';

// Conexão com o banco
$config = require 'config.php';
$dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}";
$user = $config['user'];
$pass = $config['pass'];

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo '<div class="msg sucesso">✅ Conexão com o banco estabelecida com sucesso!</div>';
} catch (PDOException $e) {
    die('<div class="msg erro">❌ Erro ao conectar ao banco: ' . $e->getMessage() . '</div>');
}

// Verifica se o arquivo foi enviado corretamente
if (!isset($_FILES['arquivo_excel_entrada_nf']) || $_FILES['arquivo_excel_entrada_nf']['error'] !== UPLOAD_ERR_OK) {
    die('<div class="msg erro">❌ Erro no upload! Código: ' . ($_FILES['arquivo_excel_entrada_nf']['error'] ?? 'Nenhum arquivo enviado') . '</div>');
}

$arquivo = $_FILES['arquivo_excel_entrada_nf']['tmp_name'];
$extensao = pathinfo($_FILES['arquivo_excel_entrada_nf']['name'], PATHINFO_EXTENSION);

// Verifica extensão válida
if (!in_array($extensao, ['xls', 'xlsx'])) {
    die('<div class="msg erro">❌ Formato inválido! Apenas arquivos .xls ou .xlsx são permitidos.</div>');
}

try {
    $spreadsheet = IOFactory::load($arquivo);
    $sheet = $spreadsheet->getActiveSheet();
    $dados = $sheet->toArray();

    echo '<div class="msg sucesso">📄 Arquivo Excel lido com sucesso!</div>';

    $pdo->beginTransaction(); // Início da transação

    // 🔹 Apaga tudo antes de inserir novamente
    $pdo->exec("DELETE FROM compra_nrserie;");
    echo "<div class='msg aviso'>🧹 Tabela compra_nrserie limpa com sucesso!</div>";

    $inseridos = 0;
    foreach ($dados as $index => $linha) {
        if ($index == 0) continue; // Pula cabeçalho

        $id_entrada_nf = trim($linha[0] ?? '');
        $id_produto    = trim($linha[1] ?? '');
        $nrserie       = trim($linha[2] ?? '');

        if (!$id_entrada_nf || !$id_produto || !$nrserie) {
            echo "<div class='msg erro'>⚠ Linha $index ignorada: campos obrigatórios ausentes!</div>";
            continue;
        }

        $nritem = 1; // preenchido automaticamente

        // INSERT com ON CONFLICT para evitar duplicidade
        $sql = "INSERT INTO compra_nrserie (id_entrada_nf, id_produto, nritem, nrserie)
                VALUES (:id_entrada_nf, :id_produto, :nritem, :nrserie)
                ON CONFLICT (id_entrada_nf, id_produto, nrserie) DO NOTHING;";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id_entrada_nf' => $id_entrada_nf,
            'id_produto'    => $id_produto,
            'nritem'        => $nritem,
            'nrserie'       => $nrserie
        ]);

        $inseridos++;
    }

    $pdo->commit(); // Finaliza a transação
    echo "<div class='msg sucesso'><strong>✅ Importação concluída com sucesso!</strong><br>📦 Registros inseridos: $inseridos</div>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo '<div class="msg erro">❌ Erro ao processar: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// Botão de retorno
echo '<a class="btn-voltar" href="upload.php">🔙 Retornar</a>';

echo '</div></body></html>';
?>
