<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// 🔧 Função de limpeza
function limparValor($valor) {
    $valor = trim((string)$valor);
    return ($valor === '' || strtoupper($valor) === 'NULL') ? null : $valor;
}

$config = require 'config.php';
$dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}";
$user = $config['user'];
$pass = $config['pass'];

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "✅ Conexão com o banco estabelecida com sucesso!<br>";
} catch (PDOException $e) {
    die("❌ Erro ao conectar ao banco: " . $e->getMessage());
}

if (!isset($_FILES['arquivo_excel_log']) || $_FILES['arquivo_excel_log']['error'] !== UPLOAD_ERR_OK) {
    die("❌ Erro no upload! Código: " . ($_FILES['arquivo_excel_log']['error'] ?? 'Nenhum arquivo enviado'));
}

$arquivo = $_FILES['arquivo_excel_log']['tmp_name'];
$extensao = strtolower(pathinfo($_FILES['arquivo_excel_log']['name'], PATHINFO_EXTENSION));
if (!in_array($extensao, ['xls', 'xlsx'])) {
    die("❌ Formato inválido! Apenas arquivos Excel são permitidos.");
}

try {
    $spreadsheet = IOFactory::load($arquivo);
    $sheet = $spreadsheet->getActiveSheet();
    $dados = $sheet->toArray(null, true, true, true);

    echo "📄 Arquivo Excel lido com sucesso!<br>";

    $pdo->beginTransaction();

    $pdo->exec("DELETE FROM numserie_log");
    echo "🧹 Tabela numserie_log limpa com sucesso!<br>";

    $id_sequencia_auto = 1;
    $inseridos = 0;

    foreach ($dados as $index => $linha) {
        if ($index === 1) continue;

        $id_empresa  = limparValor($linha['A'] ?? null);
        $id_origem   = limparValor($linha['B'] ?? null);
        $id_produto  = limparValor($linha['C'] ?? null);
        $nrserie     = limparValor($linha['D'] ?? null);
        $id_parceiro = limparValor($linha['E'] ?? null);

        if (!$id_empresa || !$id_origem || !$id_produto || !$nrserie) {
            echo "⚠ Linha $index ignorada (campos obrigatórios ausentes)<br>";
            continue;
        }

        $params = [
            'data' => date('Y-m-d'),
            'DTHR_ATUALIZACAO' => null,
            'id_empresa' => $id_empresa,
            'id_origem' => $id_origem,
            'id_produto' => $id_produto,
            'id_sequencia' => $id_sequencia_auto,
            'nrserie' => $nrserie,
            'origem' => 'NFE',
            'qtde' => 1,
            'temporario' => 'F',
            'usuario' => 'SYSEMP',
            'id_parceiro' => $id_parceiro,
            'estoque' => null,
            'id_sequencia_estoque' => null
        ];

        $sql = "INSERT INTO numserie_log 
        (data, DTHR_ATUALIZACAO, id_empresa, id_origem, id_produto, id_sequencia, nrserie, origem, qtde, temporario, usuario, id_parceiro, estoque, id_sequencia_estoque) 
        VALUES 
        (:data, :DTHR_ATUALIZACAO, :id_empresa, :id_origem, :id_produto, :id_sequencia, :nrserie, :origem, :qtde, :temporario, :usuario, :id_parceiro, :estoque, :id_sequencia_estoque)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo "✅ Linha $index inserida (sequência {$id_sequencia_auto})<br>";

        $id_sequencia_auto++;
        $inseridos++;
    }

    $pdo->commit();
  // 🔄 Atualiza a sequência do banco
    if ($inseridos > 0) {
        $novo_valor = $id_sequencia_auto - 1;
        $pdo->exec("ALTER SEQUENCE gen_numserie_log RESTART WITH " . ($novo_valor + 1));
        echo "🔄 Sequência 'gen_numserie_log' atualizada para iniciar em " . ($novo_valor + 1) . "<br>";
    }
    echo "✅ Importação concluída com sucesso!<br>";
    echo "📥 Total inserido: {$inseridos}<br>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "❌ Erro ao processar o arquivo: " . $e->getMessage();
}
echo '<div class="botao-voltar"><a href="upload.php"><button>🔙 Retornar</button></a></div>';
echo '</div></body></html>';
?>


