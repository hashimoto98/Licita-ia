<?php
/**
 * LicitAI - Model de Log de Auditoria
 *
 * Registra toda ação relevante conforme exigências da
 * Lei nº 12.737/2012 (Lei Carolina Dieckmann) e LGPD.
 */
class LogAuditoria
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function registrar(
        string $acao,
        string $descricao = '',
        ?int   $usuarioId = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): void {
        $ip        = $ip        ?? ($_SERVER['REMOTE_ADDR']     ?? '0.0.0.0');
        $userAgent = $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);

        // Trunca user agent para caber no VARCHAR(255)
        if ($userAgent && strlen($userAgent) > 255) {
            $userAgent = substr($userAgent, 0, 252) . '...';
        }

        try {
            $this->db->prepare("
                INSERT INTO logs_auditoria (usuario_id, acao, descricao, ip, user_agent)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$usuarioId, $acao, $descricao, $ip, $userAgent]);
        } catch (PDOException $e) {
            // Não propaga erro de log para não interromper o fluxo principal
            error_log('[LicitAI] Falha ao registrar log: ' . $e->getMessage());
        }
    }

    public function listar(int $limite = 200, ?string $filtroAcao = null): array
    {
        if ($filtroAcao) {
            $stmt = $this->db->prepare("
                SELECT l.*, u.nome AS usuario_nome, u.email AS usuario_email
                FROM logs_auditoria l
                LEFT JOIN usuarios u ON u.id = l.usuario_id
                WHERE l.acao = ?
                ORDER BY l.criado_em DESC
                LIMIT ?
            ");
            $stmt->execute([$filtroAcao, $limite]);
        } else {
            $stmt = $this->db->prepare("
                SELECT l.*, u.nome AS usuario_nome, u.email AS usuario_email
                FROM logs_auditoria l
                LEFT JOIN usuarios u ON u.id = l.usuario_id
                ORDER BY l.criado_em DESC
                LIMIT ?
            ");
            $stmt->execute([$limite]);
        }
        return $stmt->fetchAll();
    }

    /** Remove logs com mais de N dias (manutenção periódica). */
    public function limparAntigos(int $dias = 365): int
    {
        $antes = date('Y-m-d H:i:s', strtotime("-{$dias} days"));
        $stmt  = $this->db->prepare(
            "DELETE FROM logs_auditoria WHERE criado_em < ?"
        );
        $stmt->execute([$antes]);
        return $stmt->rowCount();
    }
}
