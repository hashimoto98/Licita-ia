<?php
/**
 * LicitAI - Model de Módulo da Empresa (Catálogo Semântico)
 */
class Modulo
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function todos(bool $apenasAtivos = true): array
    {
        $sql = "SELECT * FROM modulos_empresa";
        if ($apenasAtivos) {
            $sql .= " WHERE ativo = 1";
        }
        $sql .= " ORDER BY categoria, nome";
        return $this->db->query($sql)->fetchAll();
    }

    /** @return array|false */
    public function findById(int $id)
    {
        $stmt = $this->db->prepare("SELECT * FROM modulos_empresa WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function criar(array $dados): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO modulos_empresa (nome, descricao, palavras_chave, categoria, versao, ativo)
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            trim($dados['nome']),
            trim($dados['descricao'] ?? ''),
            $this->normalizarPalavrasChave($dados['palavras_chave'] ?? []),
            trim($dados['categoria'] ?? ''),
            trim($dados['versao'] ?? ''),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function atualizar(int $id, array $dados): bool
    {
        return (bool) $this->db->prepare("
            UPDATE modulos_empresa
            SET nome = ?, descricao = ?, palavras_chave = ?, categoria = ?, versao = ?, ativo = ?
            WHERE id = ?
        ")->execute([
            trim($dados['nome']),
            trim($dados['descricao'] ?? ''),
            $this->normalizarPalavrasChave($dados['palavras_chave'] ?? []),
            trim($dados['categoria'] ?? ''),
            trim($dados['versao'] ?? ''),
            (int) ($dados['ativo'] ?? 1),
            $id,
        ]);
    }

    public function excluir(int $id): bool
    {
        return (bool) $this->db->prepare(
            "DELETE FROM modulos_empresa WHERE id = ?"
        )->execute([$id]);
    }

    public function alterarStatus(int $id, bool $ativo): bool
    {
        return (bool) $this->db->prepare(
            "UPDATE modulos_empresa SET ativo = ? WHERE id = ?"
        )->execute([(int) $ativo, $id]);
    }

    public function categorias(): array
    {
        return $this->db->query(
            "SELECT DISTINCT categoria FROM modulos_empresa WHERE categoria != '' ORDER BY categoria"
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Normaliza palavras-chave: aceita string separada por vírgula ou array.
     * Retorna JSON array de strings em minúsculas sem duplicatas.
     */
    private function normalizarPalavrasChave(mixed $input): string
    {
        if (is_string($input)) {
            $palavras = array_map('trim', explode(',', $input));
        } elseif (is_array($input)) {
            $palavras = $input;
        } else {
            return '[]';
        }

        $palavras = array_values(array_unique(
            array_filter(array_map(
                fn(string $p) => mb_strtolower(trim($p), 'UTF-8'),
                $palavras
            ))
        ));

        return json_encode($palavras, JSON_UNESCAPED_UNICODE);
    }
}
