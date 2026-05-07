<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'vendor/autoload.php';
require 'conexao.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

/* =========================
   RECEBE DADOS + SALVA ARQUIVO
========================= */

$tipo_codigo = $_POST['tipo_codigo'];
$numempresa = $_POST['numempresa'];
$tabpreco = $_POST['tabpreco'];

/* SALVA ARQUIVO NA SESSÃO */
$pastaTemp = "temp/";
if (!is_dir($pastaTemp)) {
    mkdir($pastaTemp);
}

$nome_temp = $pastaTemp . 'temp_preco_' . session_id() . '.xlsx';
move_uploaded_file($_FILES['arquivo_excel']['tmp_name'], $nome_temp);

$_SESSION['arquivo_excel_preco'] = $nome_temp;

/* =========================
   VALIDAR EMPRESA
========================= */

$sql = "SELECT empresas_autorizadas 
        FROM schema.tabpreco
        WHERE tabpreco = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$tabpreco]);

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    die("❌ Tabela de preço não encontrada.");
}

$empresas = array_map('trim', explode(',', $row['empresas_autorizadas']));

if (!in_array($numempresa, $empresas)) {
    die("❌ Empresa não vinculada à tabela de preço.");
}

/* =========================
   LER PLANILHA
========================= */

$spreadsheet = IOFactory::load($nome_temp);
$sheet = $spreadsheet->getActiveSheet();

$dados_planilha = [];
$codigos = [];

foreach ($sheet->getRowIterator(2) as $row) {

    $linha = $row->getRowIndex();
    $codigo = trim($sheet->getCell("A$linha")->getValue());

    if (!$codigo) continue;

    $dados_planilha[$codigo] = [
        'compra' => (float)$sheet->getCell("B$linha")->getValue(),
        'venda' => (float)$sheet->getCell("C$linha")->getValue(),
    ];

    $codigos[] = $codigo;
}

/* =========================
   BUSCAR BANCO
========================= */

$dados_banco = [];

if ($codigos) {

    $placeholders = implode(',', array_fill(0, count($codigos), '?'));

    $sql = "
        SELECT 
            p.$tipo_codigo AS codigo,
            COALESCE(pe.compra, 0) AS compra,
            COALESCE(tp.preco, 0) AS venda
        FROM schema.produto p
        LEFT JOIN schema.precovenda pe 
            ON pe.codprod = p.codprod AND pe.numempresa = ?
        LEFT JOIN schema.prodvalores tp 
            ON tp.codprod = p.codprod AND tp.tabpreco = ?
        WHERE p.$tipo_codigo IN ($placeholders)
    ";

    $params = [$numempresa, $tabpreco];
    $params = array_merge($params, $codigos);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dados_banco[$row['codigo']] = $row;
    }
}

/* =========================
   EXIBIR
========================= */

echo "<h2>💰 Conferência Compra e Venda</h2>";

echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>
<tr style='background:#ddd'>
<th>Código</th>
<th>Compra Plan</th>
<th>Compra DB</th>
<th>Venda Plan</th>
<th>Venda DB</th>
<th>Status</th>
</tr>";

foreach ($dados_planilha as $codigo => $plan) {

    $db = $dados_banco[$codigo] ?? [
        'compra' => 0,
        'venda' => 0
    ];

    if (!isset($dados_banco[$codigo])) {
        echo "<tr style='background:#ff9999'>
            <td>$codigo</td>
            <td>{$plan['compra']}</td>
            <td>-</td>
            <td>{$plan['venda']}</td>
            <td>-</td>
            <td>❌ NÃO ENCONTRADO</td>
        </tr>";
        continue;
    }

    $erros = [];

    if ($db['compra'] != $plan['compra']) $erros[] = "compra";
    if ($db['venda'] != $plan['venda']) $erros[] = "venda";

    if ($erros) {
        $status = "⚠️ Divergente: " . implode(", ", $erros);
        $cor = "#ffcccc";
    } else {
        $status = "✅ OK";
        $cor = "#ccffcc";
    }

    echo "<tr style='background:$cor'>
        <td>$codigo</td>
        <td>{$plan['compra']}</td>
        <td>{$db['compra']}</td>
        <td>{$plan['venda']}</td>
        <td>{$db['venda']}</td>
        <td>$status</td>
    </tr>";
}

echo "</table>";

/* =========================
   BOTÃO EXPORTAR
========================= */

echo "<br><br>
<form action='exporta_compra_venda_excel.php' method='POST'>
    <input type='hidden' name='tipo_codigo' value='$tipo_codigo'>
    <input type='hidden' name='numempresa' value='$numempresa'>
    <input type='hidden' name='tabpreco' value='$tabpreco'>
    <button type='submit'>📥 Exportar Excel</button>
</form>";