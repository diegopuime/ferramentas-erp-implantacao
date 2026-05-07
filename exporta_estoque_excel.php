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
   PEGA ARQUIVO DA SESSÃO
========================= */

if (!isset($_SESSION['arquivo_excel_temp'])) {
    die("Arquivo não encontrado.");
}

$arquivo = $_SESSION['arquivo_excel_temp'];

$tipo_codigo = $_POST['tipo_codigo'];
$id_empresa = $_POST['id_empresa'];

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
        'estoque1' => (float)$sheet->getCell("B$linha")->getValue(),
        'estoque2' => (float)$sheet->getCell("D$linha")->getValue(),
        'estoque3' => (float)$sheet->getCell("E$linha")->getValue(),
        'estoque4' => (float)$sheet->getCell("F$linha")->getValue(),
        'estoque5' => (float)$sheet->getCell("G$linha")->getValue(),
        'estoque6' => (float)$sheet->getCell("H$linha")->getValue(),
        'estoque7' => (float)$sheet->getCell("I$linha")->getValue(),
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
                pi.estoque,
                pi.estoque2,
                pi.estoque3,
                pi.estoque4,
                pi.estoque5,
                pi.estoque6,
                pi.estoque7
            FROM schema.estoque_prod pi
            JOIN schema.produto p ON p.codprod = pi.codprod
            WHERE p.$tipo_codigo IN ($placeholders)
            AND pi.id_empresa = ?
        ";

        $stmt = $pdo->prepare($sql);
        $params = $chunk;
        $params[] = $id_empresa;
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

$sheetExcel->setCellValue('A1', 'Código');
$sheetExcel->setCellValue('B1', 'Status');
$sheetExcel->setCellValue('C1', 'Detalhes');

$linha = 2;

foreach ($dados_planilha as $codigo => $plan) {

    if (!isset($dados_banco[$codigo])) {

        $status = "NAO ENCONTRADO";
        $detalhe = "";
        $cor = 'FFFF9999';

    } else {

        $db = $dados_banco[$codigo];

        $div = false;
        $erros = [];

        if ($db['estoque1'] != $plan['estoque1']) $erros[] = "estoque1";
        if ($db['estoque2'] != $plan['estoque2']) $erros[] = "estoque2";
        if ($db['estoque3'] != $plan['estoque3']) $erros[] = "estoque3;
        if ($db['estoque4'] != $plan['estoque4']) $erros[] = "estoque4";
        if ($db['estoque5'] != $plan['estoque5']) $erros[] = "estoque5";
        if ($db['estoque6'] != $plan['estoque6']) $erros[] = "estoque6";
        if ($db['estoque7'] != $plan['estoque7']) $erros[] = "estoque7";

        if ($erros) {
            $status = "DIVERGENTE";
            $detalhe = implode(", ", $erros);
            $cor = 'FFFFCCCC';
        } else {
            $status = "OK";
            $detalhe = "";
            $cor = 'FFCCFFCC';
        }
    }

    $sheetExcel->setCellValue("A$linha", $codigo);
    $sheetExcel->setCellValue("B$linha", $status);
    $sheetExcel->setCellValue("C$linha", $detalhe);

    $sheetExcel->getStyle("A$linha:C$linha")->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB($cor);

    $linha++;
}

/* =========================
   DOWNLOAD
========================= */

ob_clean();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="conferencia_estoque.xlsx"');

$writer = new Xlsx($excel);
$writer->save('php://output');

/* LIMPA */
unlink($arquivo);
unset($_SESSION['arquivo_excel_temp']);

exit;