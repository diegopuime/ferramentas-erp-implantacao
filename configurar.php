<?php
session_start();

if (isset($_POST['salvar'])) {
    $config = [
        'host' => $_POST['host'],
        'dbname' => $_POST['dbname'],
        'user' => $_POST['user'],
        'pass' => $_POST['pass'],
        'port' => $_POST['port']
    ];

    $_SESSION['database_selecionado'] = $config['dbname'];

    $conteudo = "<?php\nreturn " . var_export($config, true) . ";\n?>";
    file_put_contents('config.php', $conteudo);

    $mensagem = "✅ Configuração salva com sucesso!";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Configuração do Banco</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f6f8;
            padding: 30px;
            max-width: 700px;
            margin: auto;
            color: #333;
        }

        h2 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }

        label {
            display: block;
            margin-top: 10px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        button {
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background-color: #2980b9;
        }

        .mensagem {
            background-color: #dff0d8;
            color: #3c763d;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .banco-info {
            background: #ecf0f1;
            padding: 10px;
            border-radius: 6px;
            margin-top: 20px;
            font-weight: bold;
        }

        a button {
            margin-top: 20px;
            background-color: #2ecc71;
        }

        a button:hover {
            background-color: #27ae60;
        }
    </style>
</head>
<body>

    <h2>🔧 Configurar Banco de Dados</h2>

    <?php if (isset($mensagem)): ?>
        <div class="mensagem"><?php echo $mensagem; ?></div>
    <?php endif; ?>

    <form method="post">
        <label for="host">Host:</label>
        <input type="text" name="host" id="host" required>

        <label for="dbname">Database:</label>
        <input type="text" name="dbname" id="dbname" required>

        <label for="user">Usuário:</label>
        <input type="text" name="user" id="user" required>

        <label for="pass">Senha:</label>
        <input type="password" name="pass" id="pass" required>

        <label for="port">Porta:</label>
        <input type="text" name="port" id="port" value="5432" required>

        <button type="submit" name="salvar">💾 Salvar Configuração</button>
    </form>

    <?php if (isset($_SESSION['database_selecionado'])): ?>
        <div class="banco-info">
            🗄 Banco Selecionado: <?php echo htmlspecialchars($_SESSION['database_selecionado']); ?>
        </div>
    <?php endif; ?>

    <a href="upload.php">
        <button>📂 Ir para Upload</button>
    </a>

</body>
</html>
