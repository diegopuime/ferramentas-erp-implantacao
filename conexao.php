<?php

// Evita erro de session duplicada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Carrega config padrão
$config = require 'config.php';

// Define banco (sessão tem prioridade)
$nome_banco = $_SESSION['database_selecionado'] ?? $config['dbname'];

try {

    // Validação básica (evita erro silencioso)
    if (empty($config['host']) || empty($config['port']) || empty($config['user'])) {
        throw new Exception("Configuração do banco incompleta.");
    }

    // Cria conexão
    $conn = new PDO(
        "pgsql:host={$config['host']};port={$config['port']};dbname={$nome_banco}",
        $config['user'],
        $config['pass']
    );

    // Configurações importantes
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // 🔥 Compatibilidade total (resolve seu problema)
    $pdo = $conn;

} catch (PDOException $e) {

    echo "<h3>Erro ao conectar no banco:</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    exit;

} catch (Exception $e) {

    echo "<h3>Erro de configuração:</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    exit;
}