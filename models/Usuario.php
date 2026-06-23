<?php
/**
 * LicitAI - Model de Usuário
 */
class Usuario
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /** @return array|false */
    public function findByEmail(string $email)
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1"
        );
        $stmt->execute([strtolower(trim($email))]);
        return $stmt->fetch();
    }

    /** @return array|false */
    public function findById(int $id)
    {
        $stmt = $this->db->prepare(
            "SELECT id, nome, email, perfil, ativo, criado_em, ultimo_acesso FROM usuarios WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function all(): array
    {
        return $this->db->query(
            "SELECT id, nome, email, perfil, ativo, criado_em, ultimo_acesso FROM usuarios ORDER BY nome"
        )->fetchAll();
    }

    public function registrarAcesso(int $id, string $ip): void
    {
        $this->db->prepare(
            "UPDATE usuarios SET ultimo_acesso = CURRENT_TIMESTAMP, ip_ultimo_acesso = ? WHERE id = ?"
        )->execute([$ip, $id]);
    }

    public function criar(string $nome, string $email, string $senha, string $perfil = 'analista'): int
    {
        $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $this->db->prepare(
            "INSERT INTO usuarios (nome, email, senha, perfil) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([trim($nome), strtolower(trim($email)), $hash, $perfil]);
        return (int) $this->db->lastInsertId();
    }

    public function atualizarSenha(int $id, string $novaSenha): bool
    {
        $hash = password_hash($novaSenha, PASSWORD_BCRYPT, ['cost' => 12]);
        return (bool) $this->db->prepare(
            "UPDATE usuarios SET senha = ? WHERE id = ?"
        )->execute([$hash, $id]);
    }

    public function alterarStatus(int $id, bool $ativo): bool
    {
        return (bool) $this->db->prepare(
            "UPDATE usuarios SET ativo = ? WHERE id = ?"
        )->execute([(int) $ativo, $id]);
    }

    /** Anonimiza dados pessoais — conformidade LGPD Art. 18 */
    public function anonimizar(int $id): bool
    {
        $token = substr(hash('sha256', (string) $id . random_bytes(8)), 0, 12);
        return (bool) $this->db->prepare(
            "UPDATE usuarios SET nome = ?, email = ?, senha = 'ANONIMIZADO', ativo = 0 WHERE id = ?"
        )->execute(["Usuário Removido", "anonimizado_{$token}@removed.local", $id]);
    }
}
