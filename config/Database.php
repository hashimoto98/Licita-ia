<?php
/**
 * LicitAI - Singleton de Conexão PDO
 *
 * Garante uma única instância de conexão por request, usando PDO com
 * prepared statements e charset utf8mb4 para suporte completo a Unicode.
 */
class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        try {
            if (DB_DRIVER === 'sqlite') {
                $dir = dirname(DB_SQLITE_PATH);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $this->pdo = new PDO('sqlite:' . DB_SQLITE_PATH, null, null, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                $this->pdo->exec('PRAGMA foreign_keys = ON');
                $this->pdo->exec('PRAGMA journal_mode = WAL');
            } else {
                $dsn = sprintf(
                    'mysql:host=%s;dbname=%s;charset=%s',
                    DB_HOST, DB_NAME, DB_CHARSET
                );
                $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ]);
            }
        } catch (PDOException $e) {
            error_log('[LicitAI] Falha na conexão PDO: ' . $e->getMessage());
            http_response_code(503);
            die('Serviço temporariamente indisponível. Tente novamente em instantes.');
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /** Impede clonagem do Singleton */
    private function __clone() {}

    /** Impede recriação via unserialize */
    public function __wakeup(): void
    {
        throw new \Exception('Não é permitido deserializar o Singleton Database.');
    }
}
