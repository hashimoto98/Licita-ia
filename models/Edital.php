<?php
/**
 * LicitAI - Model de Edital
 */
class Edital
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Retorna editais paginados, ordenados por % de match (maior primeiro).
     */
    public function listar(int $pagina = 1, array $filtros = []): array
    {
        $offset = ($pagina - 1) * ITEMS_PER_PAGE;
        $where  = ['1=1'];
        $params = [];

        if (!empty($filtros['busca'])) {
            $where[]  = "(objeto LIKE ? OR orgao LIKE ?)";
            $termo    = '%' . $filtros['busca'] . '%';
            $params[] = $termo;
            $params[] = $termo;
        }
        if (!empty($filtros['modalidade'])) {
            $where[]  = "modalidade = ?";
            $params[] = $filtros['modalidade'];
        }
        if (!empty($filtros['status'])) {
            $where[]  = "status = ?";
            $params[] = $filtros['status'];
        }
        if (!empty($filtros['match_min'])) {
            $where[]  = "porcentagem_match >= ?";
            $params[] = (float) $filtros['match_min'];
        }

        $whereStr = implode(' AND ', $where);

        $stmt = $this->db->prepare("
            SELECT * FROM editais
            WHERE {$whereStr}
            ORDER BY porcentagem_match DESC, data_publicacao DESC
            LIMIT " . ITEMS_PER_PAGE . " OFFSET {$offset}
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function total(array $filtros = []): int
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filtros['busca'])) {
            $where[]  = "(objeto LIKE ? OR orgao LIKE ?)";
            $termo    = '%' . $filtros['busca'] . '%';
            $params[] = $termo;
            $params[] = $termo;
        }
        if (!empty($filtros['modalidade'])) {
            $where[]  = "modalidade = ?";
            $params[] = $filtros['modalidade'];
        }
        if (!empty($filtros['status'])) {
            $where[]  = "status = ?";
            $params[] = $filtros['status'];
        }
        if (!empty($filtros['match_min'])) {
            $where[]  = "porcentagem_match >= ?";
            $params[] = (float) $filtros['match_min'];
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM editais WHERE " . implode(' AND ', $where)
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return array|false */
    public function findById(int $id)
    {
        $stmt = $this->db->prepare("SELECT * FROM editais WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /** @return array|false */
    public function findByPncpId(string $pncpId)
    {
        $stmt = $this->db->prepare("SELECT * FROM editais WHERE pncp_id = ? LIMIT 1");
        $stmt->execute([$pncpId]);
        return $stmt->fetch();
    }

    public function inserir(array $dados): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO editais
                (pncp_id, orgao, cnpj_orgao, objeto, modalidade, valor_estimado,
                 data_publicacao, data_encerramento, link_edital, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'novo')
        ");
        $stmt->execute([
            $dados['pncp_id']          ?? null,
            $dados['orgao']            ?? '',
            $dados['cnpj_orgao']       ?? null,
            $dados['objeto']           ?? '',
            $dados['modalidade']       ?? null,
            $dados['valor_estimado']   ?? null,
            $dados['data_publicacao']  ?? null,
            $dados['data_encerramento'] ?? null,
            $dados['link_edital']      ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function salvarAnalise(int $id, ?string $textoTr, array $requisitosIa, float $pct, array $itensMatch): void
    {
        $this->db->prepare("
            UPDATE editais SET
                texto_tr         = ?,
                requisitos_ia    = ?,
                porcentagem_match = ?,
                itens_match      = ?,
                status           = 'analisado',
                erro_analise     = NULL,
                atualizado_em    = CURRENT_TIMESTAMP
            WHERE id = ?
        ")->execute([
            $textoTr,
            json_encode($requisitosIa,  JSON_UNESCAPED_UNICODE),
            $pct,
            json_encode($itensMatch, JSON_UNESCAPED_UNICODE),
            $id,
        ]);
    }

    public function marcarErro(int $id, string $mensagem): void
    {
        $this->db->prepare(
            "UPDATE editais SET status = 'erro', erro_analise = ?, atualizado_em = CURRENT_TIMESTAMP WHERE id = ?"
        )->execute([$mensagem, $id]);
    }

    public function marcarAnalisando(int $id): void
    {
        $this->db->prepare(
            "UPDATE editais SET status = 'analisando', atualizado_em = CURRENT_TIMESTAMP WHERE id = ?"
        )->execute([$id]);
    }

    // ─── Estatísticas para o Dashboard ───────────────────────────────────────

    public function totalPorStatus(): array
    {
        return $this->db->query(
            "SELECT status, COUNT(*) as total FROM editais GROUP BY status"
        )->fetchAll();
    }

    public function totalAltaCompatibilidade(): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM editais WHERE porcentagem_match >= ? AND status = 'analisado'"
        );
        $stmt->execute([MATCH_HIGH]);
        return (int) $stmt->fetchColumn();
    }

    public function valorTotalEstimado(): ?float
    {
        $val = $this->db->query(
            "SELECT SUM(valor_estimado) FROM editais WHERE status != 'erro'"
        )->fetchColumn();
        return ($val !== null && $val !== false) ? (float) $val : null;
    }

    public function capturadosPorSemana(int $semanas = 8): array
    {
        $desde     = date('Y-m-d H:i:s', strtotime("-{$semanas} weeks"));
        $semanaExpr = DB_DRIVER === 'sqlite'
            ? "strftime('%Y-%W', criado_em)"
            : "DATE_FORMAT(criado_em, '%Y-%u')";

        $stmt = $this->db->prepare("
            SELECT
                {$semanaExpr} AS semana,
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'analisado' THEN 1 ELSE 0 END) AS analisados,
                SUM(CASE WHEN porcentagem_match >= ? THEN 1 ELSE 0 END) AS compativeis
            FROM editais
            WHERE criado_em >= ?
            GROUP BY semana
            ORDER BY semana
        ");
        $stmt->execute([MATCH_HIGH, $desde]);
        return $stmt->fetchAll();
    }

    public function distribuicaoPorModalidade(): array
    {
        return $this->db->query(
            "SELECT modalidade, COUNT(*) AS total FROM editais
             WHERE modalidade IS NOT NULL GROUP BY modalidade ORDER BY total DESC"
        )->fetchAll();
    }

    public function modalidades(): array
    {
        return $this->db->query(
            "SELECT DISTINCT modalidade FROM editais WHERE modalidade IS NOT NULL ORDER BY modalidade"
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    public function pendentesDeAnalise(int $limite = 10): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM editais WHERE status = 'novo' AND link_edital IS NOT NULL
             ORDER BY criado_em ASC LIMIT ?"
        );
        $stmt->execute([$limite]);
        return $stmt->fetchAll();
    }

    public function deletar(int $id): bool
    {
        return (bool) $this->db->prepare("DELETE FROM editais WHERE id = ?")->execute([$id]);
    }

    /** Retorna todos os editais para exportação CSV. */
    public function todosCsv(): array
    {
        return $this->db->query(
            "SELECT id, pncp_id, orgao, cnpj_orgao, objeto, modalidade, valor_estimado,
                    data_publicacao, data_encerramento, porcentagem_match, status, criado_em
             FROM editais ORDER BY porcentagem_match DESC, criado_em DESC"
        )->fetchAll();
    }

    /** Top N editais analisados por % de match. */
    public function topPorMatch(int $limite = 20): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, pncp_id, orgao, objeto, modalidade, valor_estimado,
                    data_encerramento, porcentagem_match
             FROM editais WHERE status = 'analisado'
             ORDER BY porcentagem_match DESC LIMIT ?"
        );
        $stmt->execute([$limite]);
        return $stmt->fetchAll();
    }

    /** Distribuição de editais por faixa de compatibilidade. */
    public function distribuicaoPorFaixaMatch(): array
    {
        $row = $this->db->prepare("
            SELECT
                SUM(CASE WHEN status != 'analisado' OR porcentagem_match IS NULL THEN 1 ELSE 0 END) AS nao_analisado,
                SUM(CASE WHEN status = 'analisado' AND porcentagem_match < ?                         THEN 1 ELSE 0 END) AS baixa,
                SUM(CASE WHEN status = 'analisado' AND porcentagem_match >= ? AND porcentagem_match < ? THEN 1 ELSE 0 END) AS media,
                SUM(CASE WHEN status = 'analisado' AND porcentagem_match >= ?                        THEN 1 ELSE 0 END) AS alta
            FROM editais
        ");
        $row->execute([MATCH_MEDIUM, MATCH_MEDIUM, MATCH_HIGH, MATCH_HIGH]);
        return $row->fetch() ?: ['nao_analisado' => 0, 'baixa' => 0, 'media' => 0, 'alta' => 0];
    }

    /** Evolução mensal de capturas. */
    public function capturadosPorMes(int $meses = 6): array
    {
        $desde = date('Y-m-d H:i:s', strtotime("-{$meses} months"));

        if (DB_DRIVER === 'sqlite') {
            $mesExpr   = "strftime('%m/%Y', criado_em)";
            $ordemExpr = "strftime('%Y-%m', criado_em)";
        } else {
            $mesExpr   = "DATE_FORMAT(criado_em, '%m/%Y')";
            $ordemExpr = "DATE_FORMAT(criado_em, '%Y-%m')";
        }

        $stmt = $this->db->prepare("
            SELECT
                {$mesExpr} AS mes,
                COUNT(*) AS total,
                SUM(CASE WHEN porcentagem_match >= ? THEN 1 ELSE 0 END) AS alta_comp
            FROM editais
            WHERE criado_em >= ?
            GROUP BY {$ordemExpr}
            ORDER BY {$ordemExpr}
        ");
        $stmt->execute([MATCH_HIGH, $desde]);
        return $stmt->fetchAll();
    }
}
