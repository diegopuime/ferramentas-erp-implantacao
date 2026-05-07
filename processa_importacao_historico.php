<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

use PhpOffice\PhpSpreadsheet\IOFactory;
require 'vendor/autoload.php';
require 'conexao.php'; // já define $conn

// Cria alias $pdo para compatibilidade
$pdo = $conn;

echo "<h2>📦 Importação de num_serie_lote (Automatizado)</h2>";

try {
    if (!isset($_FILES['arquivo_excel_historico']) || $_FILES['arquivo_excel_historico']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Nenhum arquivo enviado ou erro no upload.');
    }

    $arquivo_tmp = $_FILES['arquivo_excel_historico']['tmp_name'];
    $spreadsheet = IOFactory::load($arquivo_tmp);
    $sheet = $spreadsheet->getActiveSheet();
    $dados = $sheet->toArray(null, true, true, true);

    $pdo->beginTransaction();

    // 🔹 Limpa a tabela antes da nova importação
    $pdo->exec("DELETE FROM num_serie_lote;");
    echo "<p>🧹 Tabela num_serie_lote limpa com sucesso!</p>";

    // 🔹 Prepara o comando INSERT (sem id_historico)
    $sql = "INSERT INTO num_serie_lote (
                data_fabricacao,
                data_movimentacao,
                data_validade,
                descricao_operacao,
                id_empresa,
                id_estoque,
                id_origem,
                id_parceiro,
                id_produto,
                id_usuario,
                lote_nrserie,
                origem,
                quantidade_movimentada,
                saldo_anterior,
                tipo,
                tipo_operacao,
                usuario
            ) VALUES (
                :data_fabricacao,
                :data_movimentacao,
                :data_validade,
                :descricao_operacao,
                :id_empresa,
                :id_estoque,
                :id_origem,
                :id_parceiro,
                :id_produto,
                :id_usuario,
                :lote_nrserie,
                :origem,
                :quantidade_movimentada,
                :saldo_anterior,
                :tipo,
                :tipo_operacao,
                :usuario
            )
            RETURNING id_historico";

    $stmt = $pdo->prepare($sql);
    $total = 0;

    foreach ($dados as $index => $linha) {
        if ($index === 1) continue; // pula cabeçalho

        // Campos obrigatórios da planilha
        $id_empresa   = trim($linha[0] ?? $linha['A'] ?? null);
        $id_origem    = trim($linha[1] ?? $linha['B'] ?? null);
        $id_parceiro  = trim($linha[2] ?? $linha['C'] ?? null);
        $id_produto   = trim($linha[3] ?? $linha['D'] ?? null);
        $lote_nrserie = trim($linha[4] ?? $linha['E'] ?? null);

        if (!$id_empresa || !$id_origem || !$id_parceiro || !$id_produto || !$lote_nrserie) {
            echo "<p>⚠ Linha $index ignorada: campos obrigatórios ausentes.</p>";
            continue;
        }

        $agora = date('Y-m-d H:i:s');

        $params = [
            ':data_fabricacao'        => $agora,
            ':data_movimentacao'      => $agora,
            ':data_validade'          => $agora,
            ':descricao_operacao'     => 'INCLUSÃO ITEM NOTA FISCAL DE ENTRADA',
            ':id_empresa'             => $id_empresa,
            ':id_estoque'             => 0,
            ':id_origem'              => $id_origem,
            ':id_parceiro'            => $id_parceiro,
            ':id_produto'             => $id_produto,
            ':id_usuario'             => 1,
            ':lote_nrserie'           => $lote_nrserie,
            ':origem'                 => 'NFE',
            ':quantidade_movimentada' => '1.00',
            ':saldo_anterior'         => '0.00',
            ':tipo'                   => 'S',
            ':tipo_operacao'          => 'C',
            ':usuario'                => 'SYSEMP'
        ];

        $stmt->execute($params);

        // Captura o id gerado automaticamente
        $idGerado = $stmt->fetchColumn();

        echo "<p>✅ Linha $index inserida (id_historico gerado: {$idGerado})</p>";
        $total++;
    }

    $pdo->commit();

    echo "<h3>✅ Importação concluída com sucesso!</h3>";
    echo "<p>📄 Total de registros importados: <strong>$total</strong></p>";
    echo '<p><a href="upload.php">⬅️ Retornar</a></p>';

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<h3>❌ Erro durante a importação</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo '<p><a href="upload.php">⬅️ Retornar</a></p>';
}
?>
