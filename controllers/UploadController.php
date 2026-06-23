<?php
/**
 * LicitAI - Controller de Upload de Arquivos
 *
 * Responsável por receber, validar, armazenar e processar arquivos
 * de Termos de Referência enviados manualmente pelo usuário.
 *
 * Formatos aceitos: PDF, DOC, DOCX, TXT
 * Tamanho máximo: 10 MB
 */
class UploadController
{
    /** Tamanho máximo em bytes (10 MB) */
    private const MAX_SIZE = 10 * 1024 * 1024;

    /** MIME types permitidos */
    private const ALLOWED_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
    ];

    /** Extensões permitidas */
    private const ALLOWED_EXTS = ['pdf', 'doc', 'docx', 'txt'];

    private Edital $modelEdital;
    private MatchController $matchCtrl;
    private LogAuditoria $log;

    public function __construct()
    {
        $this->modelEdital = new Edital();
        $this->matchCtrl   = new MatchController();
        $this->log         = new LogAuditoria();
    }

    /**
     * Processa o upload de um arquivo TR para um edital existente.
     * Responde JSON para chamada AJAX.
     */
    public function uploadTR(int $editalId): void
    {
        AuthController::requerAutenticacao();

        header('Content-Type: application/json; charset=utf-8');

        if (empty($_FILES['arquivo_tr'])) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum arquivo enviado.']);
            return;
        }

        $edital = $this->modelEdital->findById($editalId);
        if (!$edital) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Edital não encontrado.']);
            return;
        }

        $arquivo = $_FILES['arquivo_tr'];

        // ── Validação do arquivo ───────────────────────────────────────────
        $erroValidacao = $this->validar($arquivo);
        if ($erroValidacao) {
            echo json_encode(['sucesso' => false, 'mensagem' => $erroValidacao]);
            return;
        }

        // ── Armazenamento seguro ───────────────────────────────────────────
        $caminho = $this->salvar($arquivo, $editalId);
        if (!$caminho) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Falha ao salvar o arquivo no servidor.']);
            return;
        }

        // ── Extração de texto ──────────────────────────────────────────────
        $textoTr = $this->extrairTexto($caminho, $arquivo['type']);
        if (empty($textoTr)) {
            @unlink($caminho);
            echo json_encode(['sucesso' => false, 'mensagem' => 'Não foi possível extrair texto do arquivo. Verifique se o PDF não está protegido ou digitalizado.']);
            return;
        }

        // ── Persiste texto e dispara análise IA ───────────────────────────
        // Atualiza o texto_tr antes de analisar
        $this->atualizarTextoTR($editalId, $textoTr);

        $this->log->registrar(
            'UPLOAD_TR',
            "Edital #{$editalId} | Arquivo: {$arquivo['name']} | Tamanho: " . round($arquivo['size'] / 1024) . "KB",
            $_SESSION['usuario_id'] ?? null
        );

        // Dispara análise completa
        $resultado = $this->matchCtrl->analisarEdital($editalId, $_SESSION['usuario_id'] ?? null);
        $resultado['arquivo_nome'] = basename($caminho);

        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    }

    // ─── Helpers Privados ─────────────────────────────────────────────────────

    private function validar(array $arquivo): ?string
    {
        if ($arquivo['error'] !== UPLOAD_ERR_OK) {
            $erros = [
                UPLOAD_ERR_INI_SIZE   => 'Arquivo excede o limite do servidor (upload_max_filesize).',
                UPLOAD_ERR_FORM_SIZE  => 'Arquivo excede o limite do formulário.',
                UPLOAD_ERR_PARTIAL    => 'Upload incompleto. Tente novamente.',
                UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo selecionado.',
                UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário indisponível.',
                UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar no disco.',
            ];
            return $erros[$arquivo['error']] ?? "Erro de upload (código {$arquivo['error']}).";
        }

        if ($arquivo['size'] > self::MAX_SIZE) {
            return 'Arquivo muito grande. Tamanho máximo: 10 MB.';
        }

        // Verifica extensão
        $ext = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTS, true)) {
            return 'Formato não permitido. Envie PDF, DOC, DOCX ou TXT.';
        }

        // Verifica MIME type real (não confiar no informado pelo browser)
        $mime = mime_content_type($arquivo['tmp_name']);
        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            return "Tipo de arquivo inválido ({$mime}). Envie um PDF, Word ou texto.";
        }

        // Garante que é um upload legítimo (não um path traversal)
        if (!is_uploaded_file($arquivo['tmp_name'])) {
            return 'Arquivo inválido.';
        }

        return null;
    }

    private function salvar(array $arquivo, int $editalId): ?string
    {
        $uploadsDir = __DIR__ . '/../uploads';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0750, true);
        }

        $ext      = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        $filename = sprintf('tr_%d_%s_%s.%s',
            $editalId,
            date('Ymd_His'),
            bin2hex(random_bytes(4)), // Previne enumeration
            $ext
        );
        $destino = $uploadsDir . '/' . $filename;

        if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
            return null;
        }

        // Restringe permissões do arquivo
        chmod($destino, 0640);

        return $destino;
    }

    private function extrairTexto(string $caminho, string $mime): ?string
    {
        // ── TXT ───────────────────────────────────────────────────────────
        if ($mime === 'text/plain') {
            $conteudo = file_get_contents($caminho);
            return $conteudo !== false ? mb_substr($conteudo, 0, 50000, 'UTF-8') : null;
        }

        // ── PDF: extração direta (não via cURL — arquivo já está local) ───
        if ($mime === 'application/pdf') {
            return $this->extrairPdf($caminho);
        }

        // ── DOC / DOCX ────────────────────────────────────────────────────
        if (str_contains($mime, 'word') || str_contains($mime, 'openxmlformats')) {
            return $this->extrairDocx($caminho);
        }

        return null;
    }

    /**
     * Extrai texto de um PDF local.
     * Ordem de tentativa: pdftotext (poppler-utils) → extração binária de strings.
     */
    private function extrairPdf(string $caminho): ?string
    {
        // Tenta pdftotext se disponível no servidor
        if ($this->comandoDisponivel('pdftotext')) {
            // Redireciona stderr para /dev/null (Linux) ou NUL (Windows)
            $null   = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
            $texto  = shell_exec(
                'pdftotext -enc UTF-8 ' . escapeshellarg($caminho) . ' - 2>' . $null
            );
            if ($texto && mb_strlen(trim($texto), 'UTF-8') > 50) {
                return mb_substr(trim($texto), 0, 50000, 'UTF-8');
            }
        }

        // Fallback: extração básica de strings do PDF binário
        $conteudo = file_get_contents($caminho);
        if (!$conteudo) {
            return null;
        }
        preg_match_all('/\(([^)]{4,200})\)/', $conteudo, $m);
        $linhas = array_filter(
            $m[1] ?? [],
            fn($t) => preg_match('/[a-zA-ZÀ-ÿ]{3,}/', $t)
        );
        $resultado = mb_substr(trim(implode(' ', $linhas)), 0, 30000, 'UTF-8');
        return $resultado !== '' ? $resultado : null;
    }

    private function extrairDocx(string $caminho): ?string
    {
        // DOCX é um ZIP: extrai word/document.xml e remove tags
        if (!class_exists('ZipArchive')) {
            return null;
        }
        $zip = new ZipArchive();
        if ($zip->open($caminho) !== true) {
            return null;
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (!$xml) {
            return null;
        }
        // Remove tags XML e preserva espaços
        $texto = preg_replace('/<w:p[ >]/', "\n", $xml);
        $texto = strip_tags($texto);
        $texto = html_entity_decode($texto, ENT_QUOTES, 'UTF-8');
        $texto = preg_replace('/\s+/', ' ', $texto);
        return mb_substr(trim($texto), 0, 50000, 'UTF-8');
    }

    private function atualizarTextoTR(int $editalId, string $texto): void
    {
        $db = Database::getInstance()->getConnection();
        $db->prepare(
            "UPDATE editais SET texto_tr = ?, status = 'novo', atualizado_em = CURRENT_TIMESTAMP WHERE id = ?"
        )->execute([$texto, $editalId]);
    }

    private function comandoDisponivel(string $cmd): bool
    {
        $disabled = array_map('trim', explode(',', ini_get('disable_functions')));
        if (in_array('shell_exec', $disabled, true)) {
            return false;
        }
        // escapeshellarg previne injeção de comando
        $null  = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $where = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';
        $teste = shell_exec($where . ' ' . escapeshellarg($cmd) . ' 2>' . $null);
        return !empty(trim((string) $teste));
    }
}
