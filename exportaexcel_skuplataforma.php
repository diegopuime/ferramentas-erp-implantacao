<?php

ini_set('memory_limit', '1024M');
set_time_limit(0);

require 'vendor/autoload.php';
include 'conexao.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    die('Acesso inválido.');
}

$nummkplace = $_POST['nummkplace'] ?? '';
$tipo_codigo = $_POST['tipo_codigo'] ?? '';

$camposPermitidos = [
    'cod_prod',
    'cod_sku',
    'cod_forn',
    'cod_barra'
];

if (!in_array($tipo_codigo, $camposPermitidos)) {
    die('Tipo de código inválido.');
}

$sqlPlataforma = "
    SELECT descricao
    FROM plataforma
    WHERE nummkplace = :nummkplace
";

$stmtPlataforma = $pdo->prepare($sqlPlataforma);

$stmtPlataforma->execute([
    ':nummkplace' => $nummkplace
]);

$plataforma = $stmtPlataforma->fetch(PDO::FETCH_ASSOC);

$nomePlataforma = $plataforma['descricao'] ?? 'PLATAFORMA';

$sql = "
    SELECT
        p.cod_prod,
        p.descricao,
        p.cod_barra,
        p.cod_sku,
        p.cod_forn,
        pp.cod_sku AS sku_plataforma
    FROM produto p
    INNER JOIN produto_plataforma pp
        ON pp.cod_prod = p.cod_prod
    WHERE pp.nummkplace = :nummkplace
    ORDER BY p.cod_prod
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':nummkplace' => $nummkplace
]);

$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$cabecalho = [
    'CODIGO',
    'SKU PLATAFORMA',
    'CODPROD',
    'DESCRICAO',
    'SKU',
    'CODIGO FORN',
    'CODIGO BARRAS'
];

$sheet->fromArray($cabecalho, NULL, 'A1');

$linha = 2;

foreach ($dados as $item) {

    switch ($tipo_codigo) {

        case 'cod_prod':
            $codigo = $item['cod_prod'];
            break;

        case 'cod_sku':
            $codigo = $item['cod_sku'];
            break;

        case 'cod_forn':
            $codigo = $item['cod_forn'];
            break;

        case 'cod_barra':
            $codigo = $item['cod_barra'];
            break;

        default:
            $codigo = '';
    }

    $sheet->setCellValue("A$linha", $codigo);
    $sheet->setCellValue("B$linha", $item['sku_plataforma']);
    $sheet->setCellValue("C$linha", $item['cod_prod']);
    $sheet->setCellValue("D$linha", $item['descricao']);
    $sheet->setCellValue("E$linha", $item['cod_sku']);
    $sheet->setCellValue("F$linha", $item['cod_forn']);
    $sheet->setCellValueExplicit(
        "G$linha",
        $item['cod_barra'],
        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
    );

    $linha++;
}

foreach ($sheet->getColumnIterator() as $column) {

    $sheet->getColumnDimension(
        $column->getColumnIndex()
    )->setAutoSize(true);
}

$sheet->freezePane('A2');

$sheet->getStyle('A1:G1')->getFont()->setBold(true);

$nomeArquivo = 'exporta_sku_plataforma_' .
    preg_replace('/[^A-Za-z0-9]/', '_', $nomePlataforma) .
    '_' .
    date('Ymd_His') .
    '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $nomeArquivo . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

exit;