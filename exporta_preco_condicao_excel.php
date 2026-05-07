<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');

require 'vendor/autoload.php';
require 'conexao.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/* =========================
   VALIDAR ARQUIVO
========================= */

if (!isset($_SESSION['arquivo_excel_condicao'])) {
    die("❌ Arquivo não encontrado na sessão.");
}

$arquivo = $_SESSION['arquivo_excel_condicao'];

$tipo_codigo = $_POST['tipo_codigo'];
$idprodvalores = $_POST['idprodvalores'];
$id_condpagto = $_POST['id_condpagto'];

/* =========================
   LER PLANILHA
========================= */

$spreadsheet = IOFactory::load($arquivo);
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

    // evita erro com muitos parâmetros
    $chunks = array_chunk($codigos, 1000);

    foreach ($chunks as $chunk) {

        $placeholders = implode(',', array_fill(0, count($chunk), '?'));

        $sql = "
            SELECT 
                p.$tipo_codigo AS codigo,
                COALESCE(tcp.valor, 0) AS venda
            FROM sysemp.produto p
            LEFT JOIN sysemp.valorcondprod tcp
                ON tcp.codprod = p.codprod
                AND tcp.idprodvalores = ?
                AND tcp.id_condpagto = ?
            WHERE p.$tipo_codigo IN ($placeholders)
        ";

        $params = [$idprodvalores, $id_condpagto];
        $params = array_merge($params, $chunk);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dados_banco[$row['codigo']] = $row;
        }
    }
}

/* =========================
   GERAR EXCEL
========================= */

$excel = new Spreadsheet();
$sheetExcel = $excel->getActiveSheet();

$sheetExcel->fromArray(
    ['Código','Venda Plan','Venda DB','Status','Detalhes'],
    NULL,
    'A1'
);

$linha = 2;

foreach ($dados_planilha as $codigo => $plan) {

    $db = $dados_banco[$codigo] ?? ['venda' => 0];

    if (!isset($dados_banco[$codigo])) {

        $status = "NAO ENCONTRADO";
        $detalhe = "";
        $cor = 'FFFF9999';

    } else {

        if ($db['venda'] != $plan['venda']) {
            $status = "DIVERGENTE";
            $detalhe = "valor diferente";
            $cor = 'FFFFCCCC';
        } else {
            $status = "OK";
            $detalhe = "";
            $cor = 'FFCCFFCC';
        }
    }

    $sheetExcel->fromArray([
        $codigo,
        $plan['venda'],
        $db['venda'],
        $status,
        $detalhe
    ], NULL, "A$linha");

    // cor na linha
    $sheetExcel->getStyle("A$linha:E$linha")->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB($cor);

    $linha++;
}

/* =========================
   DOWNLOAD
========================= */

ob_clean();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="conferencia_preco_condicao.xlsx"');

$writer = new Xlsx($excel);
$writer->save('php://output');

/* LIMPA TEMP */
unlink($arquivo);
unset($_SESSION['arquivo_excel_condicao']);

exit;