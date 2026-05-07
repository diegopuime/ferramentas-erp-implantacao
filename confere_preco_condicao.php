<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');

require 'vendor/autoload.php';
require 'conexao.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

/* =========================
   RECEBER DADOS
========================= */

$tipo_codigo = $_POST['tipo_codigo'];
$idprodvalores = $_POST['idprodvalores'];
$codvalorcondprod = $_POST['codvalorcondprod'];
$arquivo = $_FILES['arquivo_excel']['tmp_name'];

/* =========================
   SALVAR TEMP PRA EXPORTAR
========================= */

$pastaTemp = "temp/";
if (!is_dir($pastaTemp)) {
    mkdir($pastaTemp);
}

$nome_temp = $pastaTemp . 'temp_condicao_' . session_id() . '.xlsx';
move_uploaded_file($arquivo, $nome_temp);

$_SESSION['arquivo_excel_condicao'] = $nome_temp;

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
        'venda' => (float)$sheet->getCell("B$linha")->getValue()
    ];

    $codigos[] = $codigo;
}

/* =========================
   BUSCAR BANCO
========================= */

$dados_banco = [];

if ($codigos) {

    $chunks = array_chunk($codigos, 1000);

    foreach ($chunks as $chunk) {

        $placeholders = implode(',', array_fill(0, count($chunk), '?'));

        $sql = "
            SELECT 
                p.$tipo_codigo AS codigo,
                COALESCE(tcp.valor, 0) AS venda
            FROM sysemp.produto p
            LEFT JOIN sysemp.valorcondprod tcp
                ON tcp.id_produto = p.id_produto
                AND tcp.idprodvalores = ?
                AND tcp.codvalorcondprod = ?
            WHERE p.$tipo_codigo IN ($placeholders)
        ";

        $params = [$idprodvalores, $codvalorcondprod];
        $params = array_merge($params, $chunk);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dados_banco[$row['codigo']] = $row;
        }
    }
}

/* =========================
   EXIBIR RESULTADO
========================= */

echo "<h2>💳 Conferência Preço por Condição</h2>";

echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>
<tr style='background:#ddd'>
<th>Código</th>
<th>Venda Plan</th>
<th>Venda DB</th>
<th>Status</th>
<th>Detalhes</th>
</tr>";

foreach ($dados_planilha as $codigo => $plan) {

    if (!isset($dados_banco[$codigo])) {

        echo "<tr style='background:#ff9999'>
            <td>$codigo</td>
            <td>{$plan['venda']}</td>
            <td>-</td>
            <td>❌ NÃO ENCONTRADO</td>
            <td></td>
        </tr>";
        continue;
    }

    $db = $dados_banco[$codigo];

    $planVenda = (float)$plan['venda'];
    $dbVenda = (float)$db['venda'];

    if ($planVenda != $dbVenda) {
        $status = "⚠️ DIVERGENTE";
        $detalhe = "Plan: $planVenda / DB: $dbVenda";
        $cor = "#ffcccc";
    } else {
        $status = "✅ OK";
        $detalhe = "";
        $cor = "#ccffcc";
    }

    echo "<tr style='background:$cor'>
        <td>$codigo</td>
        <td>$planVenda</td>
        <td>$dbVenda</td>
        <td>$status</td>
        <td>$detalhe</td>
    </tr>";
}

echo "</table>";

/* =========================
   BOTÃO EXPORTAR
========================= */

echo "<br><br>
<form action='exporta_preco_condicao_excel.php' method='POST'>
    <input type='hidden' name='tipo_codigo' value='$tipo_codigo'>
    <input type='hidden' name='idprodvalores' value='$idprodvalores'>
    <input type='hidden' name='codvalorcondprod' value='$codvalorcondprod'>
    <button type='submit'>📥 Exportar Excel</button>
</form>";