<?php
// Ativa exibição de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$config = require 'config.php';
$dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}";
$user = $config['user'];
$pass = $config['pass'];

try {
    $conn = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $conn->exec("SET search_path TO sysemp;");
} catch (PDOException $e) {
    die("❌ Erro ao conectar ao banco: " . $e->getMessage());
}

// Checa upload
if (!isset($_FILES['arquivo_excel_lote_utiliza']) || $_FILES['arquivo_excel_lote_utiliza']['error'] !== UPLOAD_ERR_OK) {
    die("❌ Nenhum arquivo enviado ou erro no upload.");
}

$arquivo_tmp = $_FILES['arquivo_excel_lote_utiliza']['tmp_name'];

try {
    // Inicia transação
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
            $sql = "UPDATE produto SET lote_utiliza = :controle WHERE id_produto = :id";
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

    // Confirma a transação
    $conn->commit();

    echo "<h2>✅ Importação Concluída</h2>";
    echo "<p>📦 Total de registros lidos: <strong>$total</strong></p>";
    echo "<p>🛠 Atualizações realizadas: <strong>$atualizados</strong></p>";

} catch (Exception $e) {
    $conn->rollBack();
    echo "<h2>❌ Erro durante o processamento</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

echo '<p><a href="upload.php">🔙 Retornar</a></p>';
?>
