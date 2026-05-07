try {
    // Inicia a transação
    $conn->beginTransaction();

    $spreadsheet = IOFactory::load($arquivo_tmp);
    $worksheet = $spreadsheet->getActiveSheet();
    $linhas = $worksheet->toArray();

    $total = 0;
    $atualizados = 0;

    foreach ($linhas as $index => $linha) {
        if ($index === 0) continue; // pula o cabeçalho

        $id_produto = trim($linha[0]);
        $controle = strtoupper(trim($linha[1]));

        if (in_array($controle, ['F', 'T', 'C']) && is_numeric($id_produto)) {
            $sql = "UPDATE produto SET nrserie_ajusta = :controle WHERE id_produto = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':controle' => $controle,
                ':id' => $id_produto
            ]);

            if ($stmt->rowCount() > 0) {
                $atualizados++;
            }
        }

        $total++;
    }

    // Tudo certo, confirma a transação
    $conn->commit();

    echo "<h2>✅ Importação Concluída</h2>";
    echo "<p>📦 Total de registros lidos: <strong>$total</strong></p>";
    echo "<p>🛠 Atualizações realizadas: <strong>$atualizados</strong></p>";

} catch (Exception $e) {
    // Algo deu errado, desfaz as alterações
    $conn->rollBack();
    echo "<h2>❌ Erro durante o processamento</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
