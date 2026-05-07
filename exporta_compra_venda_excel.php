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

/* ARQUIVO */
if (!isset($_SESSION['arquivo_excel_preco'])) {
    die("Arquivo não encontrado.");
}

$arquivo = $_SESSION['arquivo_excel_preco'];

$tipo_codigo = $_POST['tipo_codigo'];
$numempresa = $_POST['numempresa'];
$id_tb_preco = $_POST['id_tb_preco'];

/* LER */
$spreadsheet = IOFactory::load($arquivo);
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

/* BANCO */
$dados_banco = [];

if ($codigos) {

    $placeholders = implode(',', array_fill(0, count($codigos), '?'));

    $sql = "
        SELECT 
            p.$tipo_codigo AS codigo,
            COALESCE(pe.precocompra, 0) AS precocompra,
            COALESCE(tp.preco, 0) AS venda
        FROM schema.produto p
        LEFT JOIN schema.precoprod pe 
            ON pe.cod_prog = p.cod_prog AND pe.numempresa = ?
        LEFT JOIN schema.prodvalores tp 
            ON tp.cod_prog = p.cod_prog AND tp.id_tb_preco = ?
        WHERE p.$tipo_codigo IN ($placeholders)
    ";

    $params = [$numempresa, $id_tb_preco];
    $params = array_merge($params, $codigos);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dados_banco[$row['codigo']] = $row;
    }
}

/* EXCEL */
$excel = new Spreadsheet();
$sheetExcel = $excel->getActiveSheet();

$sheetExcel->fromArray(
    ['Código','Compra Plan','Compra DB','Venda Plan','Venda DB','Status','Detalhes'],
    NULL,
    'A1'
);

$linha = 2;

foreach ($dados_planilha as $codigo => $plan) {

    $db = $dados_banco[$codigo] ?? [
        'precocompra' => 0,
        'venda' => 0
    ];

    if (!isset($dados_banco[$codigo])) {
        $status = "NAO ENCONTRADO";
        $detalhe = "";
        $cor = 'FFFF9999';
    } else {
        $erros = [];

        if ($db['precocompra'] != $plan['compra']) $erros[] = "compra";
        if ($db['venda'] != $plan['venda']) $erros[] = "venda";

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

    $sheetExcel->fromArray([
        $codigo,
        $plan['compra'],
        $db['precocompra'],
        $plan['venda'],
        $db['venda'],
        $status,
        $detalhe
    ], NULL, "A$linha");

    $sheetExcel->getStyle("A$linha:G$linha")->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB($cor);

    $linha++;
}

/* DOWNLOAD */
ob_clean();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="conferencia_preco.xlsx"');

$writer = new Xlsx($excel);
$writer->save('php://output');

/* LIMPA */
unlink($arquivo);
unset($_SESSION['arquivo_excel_preco']);

exit;