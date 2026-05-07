<?php
ini_set('memory_limit', '1024M');
set_time_limit(0);

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

include 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (!isset($_FILES['arquivo'])) {
        die("Arquivo não enviado.");
    }

    $nummkplace = $_POST['nummkplace'];
    $tipo_codigo = $_POST['tipo_codigo'];

    $campos_permitidos = [
        'codprod',
        'cod_barra',
        'sku',
        'cod_forn'
    ];

    if (!in_array($tipo_codigo, $campos_permitidos)) {
        die("Campo inválido.");
    }

    $arquivo = $_FILES['arquivo']['tmp_name'];

    $spreadsheet = IOFactory::load($arquivo);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    $resultado = [];

    foreach ($rows as $index => $row) {

        if ($index == 0) {
            continue;
        }

        $codigo_excel = trim($row[0] ?? '');
        $sku_excel = trim($row[1] ?? '');

        $sku_excel = mb_strtoupper($sku_excel);

        if (empty($codigo_excel)) {
            continue;
        }

        $sql = "
               SELECT
        p.codprod,
        p.descricao,
        p.cod_barra,
        p.sku,
        p.cod_forn,
        pp.cod_sku AS sku_plataforma

    FROM schema.produto p

    LEFT JOIN schema.skuprod pp
        ON pp.codprod = p.codprod
       AND pp.nummkplace = :nummkplace

    WHERE p.$tipo_codigo = :codigo

    LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':nummkplace' => $nummkplace,
            ':codigo' => $codigo_excel
        ]);

        $dados = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dados) {

            $status = 'PRODUTO NÃO ENCONTRADO';

            $resultado[] = [
                $codigo_excel,
                $sku_excel,
                '',
                '',
                '',
                '',
                '',
                $status
            ];

            continue;
        }

        $sku_banco = trim($dados['sku_plataforma'] ?? '');
        $sku_banco = mb_strtoupper($sku_banco);

        if (empty($sku_banco)) {

            $status = 'SEM CADASTRO NA PLATAFORMA';

        } elseif ($sku_excel == $sku_banco) {

            $status = 'OK';

        } else {

            $status = 'DIVERGENTE';
        }

        $resultado[] = [
            $codigo_excel,
            $sku_excel,
            $sku_banco,
            $dados['codprod'],
            $dados['descricao'],
            $dados['sku'],
            $dados['cod_forn'],
            $status
        ];
    }

    $novoSpreadsheet = new Spreadsheet();
    $novaSheet = $novoSpreadsheet->getActiveSheet();

    $cabecalho = [
        'CODIGO EXCEL',
        'SKU EXCEL',
        'SKU BANCO',
        'ID PRODUTO',
        'DESCRICAO',
        'COD SKU',
        'COD FORN',
        'RESULTADO'
    ];

    $novaSheet->fromArray($cabecalho, NULL, 'A1');
    $novaSheet->fromArray($resultado, NULL, 'A2');

    foreach ($novaSheet->getColumnIterator() as $column) {
        $novaSheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
    }

    $linhaFinal = count($resultado) + 1;

    for ($i = 2; $i <= $linhaFinal; $i++) {

        $status = $novaSheet->getCell("H$i")->getValue();

        if ($status == 'OK') {

            $novaSheet->getStyle("A$i:H$i")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('C6EFCE');

        } elseif ($status == 'DIVERGENTE') {

            $novaSheet->getStyle("A$i:H$i")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFC7CE');

        } else {

            $novaSheet->getStyle("A$i:H$i")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFEB9C');
        }
    }

    $nomeArquivo = 'conferencia_plataforma_sku_' . date('Ymd_His') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment;filename=\"$nomeArquivo\"");
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($novoSpreadsheet);
    $writer->save('php://output');

    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Conferência Plataforma SKU</title>
</head>
<body>

<h2>Conferência Plataforma SKU</h2>

<form method="POST" enctype="multipart/form-data">

    <label>Plataforma:</label>
    <select name="nummkplace" required>

        <?php

        $sqlPlataforma = "SELECT nummkplace, descricao
    FROM schema.plataforma
    ORDER BY descricao";
        $stmtPlataforma = $pdo->query($sqlPlataforma);

        while ($plat = $stmtPlataforma->fetch(PDO::FETCH_ASSOC)) {

            echo '<option value="' . $plat['nummkplace'] . '">'
                . $plat['descricao'] .
                '</option>';
        }

        ?>

    </select>

    <br><br>

    <label>Tipo Código Coluna A:</label>

    <select name="tipo_codigo" required>
        <option value="codprod">ID Produto</option>
        <option value="cod_barra">Código Barras</option>
        <option value="sku">Cód sku</option>
        <option value="cod_forn">Código Forn</option>
    </select>

    <br><br>

    <label>Planilha:</label>
    <input type="file" name="arquivo" accept=".xlsx,.xls,.csv" required>

    <br><br>

    <button type="submit">Conferir</button>

</form>

<br>

<b>Formato da planilha:</b>

<table border="1" cellpadding="5">
    <tr>
        <th>Coluna A</th>
        <th>Coluna B</th>
    </tr>
    <tr>
        <td>Código Produto</td>
        <td>SKU Plataforma</td>
    </tr>
</table>

</body>
</html>