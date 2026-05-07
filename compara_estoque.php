<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'vendor/autoload.php';
require 'conexao.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

/* =========================
   RECEBE DADOS
========================= */

$tipo_codigo = $_POST['tipo_codigo'];
$num_empresa = $_POST['num_empresa'];
$arquivo_tmp = $_FILES['arquivo_excel']['tmp_name'];

/* =========================
   SALVA ARQUIVO TEMP
========================= */

$pastaTemp = "temp/";

if (!is_dir($pastaTemp)) {
    mkdir($pastaTemp);
}

$nome_temp = $pastaTemp . 'temp_' . session_id() . '.xlsx';

move_uploaded_file($arquivo_tmp, $nome_temp);

$_SESSION['arquivo_excel_temp'] = $nome_temp;

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

    if (!$codigo) {
        continue;
    }

    $dados_planilha[$codigo] = [

        'estoque1' => round((float)$sheet->getCell("B$linha")->getCalculatedValue(), 4),

        'estoque2' => round((float)$sheet->getCell("D$linha")->getCalculatedValue(), 4),

        'estoque3' => round((float)$sheet->getCell("E$linha")->getCalculatedValue(), 4),

        'estoque4' => round((float)$sheet->getCell("F$linha")->getCalculatedValue(), 4),

        'estoque5' => round((float)$sheet->getCell("G$linha")->getCalculatedValue(), 4),

        'estoque6' => round((float)$sheet->getCell("H$linha")->getCalculatedValue(), 4),

        'estoque7' => round((float)$sheet->getCell("I$linha")->getCalculatedValue(), 4),
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
            pi.estoque1,
            pi.estoque2,
            pi.estoque3,
            pi.estoque4,
            pi.estoque5,
            pi.estoque6,
            pi.estoque6

        FROM schema.estprod pi

        JOIN schema.produto p 
            ON p.codprod = pi.codprod

        WHERE p.$tipo_codigo IN ($placeholders)
        AND pi.num_empresa = ?
    ";

    $stmt = $pdo->prepare($sql);

    $params = $codigos;
    $params[] = $num_empresa;

    $stmt->execute($params);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $dados_banco[$row['codigo']] = [

            'estoque1' => round((float)$row['estoque1'], 4),

            'estoque2' => round((float)$row['estoque2'], 4),

            'estoque3' => round((float)$row['estoque3'], 4),

            'estoque4' => round((float)$row['estoque4'], 4),

            'estoque5' => round((float)$row['estoque5'], 4),

            'estoque6' => round((float)$row['estoque6'], 4),

            'estoque7' => round((float)$row['estoque7'], 4),
        ];
    }
}

/* =========================
   FUNÇÕES
========================= */

function normalizaNumero($valor, $casas = 4)
{
    return round((float)$valor, $casas);
}

function diferente($a, $b, $casas = 4)
{
    return normalizaNumero($a, $casas) != normalizaNumero($b, $casas);
}

/* =========================
   EXIBIR RESULTADO
========================= */

echo "
<style>
    body {
        font-family: Arial;
        padding: 20px;
    }

    table {
        border-collapse: collapse;
        width: 100%;
    }

    th, td {
        padding: 10px;
        border: 1px solid #ccc;
        vertical-align: top;
    }

    th {
        background: #eee;
    }
</style>
";

echo "<h2>📊 Resultado da Conferência</h2>";

echo "
<table>
<tr>
    <th>Código</th>
    <th>Status</th>
</tr>
";

foreach ($dados_planilha as $codigo => $plan) {

    if (!isset($dados_banco[$codigo])) {

        echo "
        <tr style='background:#ff9999'>
            <td>$codigo</td>
            <td>❌ NÃO ENCONTRADO</td>
        </tr>
        ";

        continue;
    }

    $db = $dados_banco[$codigo];

    $div = false;
    $erros = [];

    /* =========================
       COMPARAÇÕES
    ========================= */

    if (diferente($db['estoque'], $plan['estoque1'])) {

        $div = true;

        $erros[] =
            "estoque 
            (planilha: {$plan['estoque1']} | banco: {$db['estoque1']})";
    }

    if (diferente($db['estoque2'], $plan['assistencia'])) {

        $div = true;

        $erros[] =
            "assistência 
            (planilha: {$plan['estoque2']} | banco: {$db['estoque2']})";
    }

    if (diferente($db['estoque3'], $plan['avaliacao'])) {

        $div = true;

        $erros[] =
            "avaliação 
            (planilha: {$plan['estoque3']} | banco: {$db['estoque3']})";
    }

    if (diferente($db['estoque4'], $plan['deposito'])) {

        $div = true;

        $erros[] =
            "depósito 
            (planilha: {$plan['estoque4']} | banco: {$db['estoque4']})";
    }

    if (diferente($db['estoque5'], $plan['perda'])) {

        $div = true;

        $erros[] =
            "perda 
            (planilha: {$plan['estoque5']} | banco: {$db['estoque5']})";
    }

    if (diferente($db['estoque6'], $plan['terceiro'])) {

        $div = true;

        $erros[] =
            "terceiro 
            (planilha: {$plan['estoque6']} | banco: {$db['estoque6']})";
    }

    if (diferente($db['estoque7'], $plan['fifo'])) {

        $div = true;

        $erros[] =
            "fifo 
            (planilha: {$plan['estoque7']} | banco: {$db['estoque7']})";
    }

    /* =========================
       STATUS
    ========================= */

    if ($div) {

        $status = "⚠️ Divergente:<br>" . implode("<br>", $erros);

        $cor = "#ffcccc";

    } else {

        $status = "✅ OK";

        $cor = "#ccffcc";
    }

    echo "
    <tr style='background:$cor'>
        <td>$codigo</td>
        <td>$status</td>
    </tr>
    ";
}

echo "</table>";

/* =========================
   EXPORTAR
========================= */

echo "
<br><br>

<form action='exporta_estoque_excel.php' method='POST'>

    <input type='hidden' 
           name='tipo_codigo' 
           value='$tipo_codigo'>

    <input type='hidden' 
           name='num_empresa' 
           value='$num_empresa'>

    <button type='submit'>
        📥 Exportar Excel
    </button>

</form>
";