<?php
/**
 * LicitAI - Integração com a API PNCP
 *
 * Usa a nova API de busca do PNCP (GET /api/search/) com parâmetro `q`
 * para busca textual direta, eliminando a necessidade de filtrar client-side.
 *
 * @see https://pncp.gov.br/app/api/swagger-ui/index.html
 */
class ApiPncpController
{
    private Edital $modelEdital;
    private LogAuditoria $log;

    /** Setores disponíveis e seus labels */
    public const SETORES = [
        'ti'          => 'Tecnologia da Informação',
        'tributario'  => 'Tributário / Fiscal',
        'educacao'    => 'Educação',
        'todos'       => 'Todos os Setores',
    ];

    /**
     * Termos de busca testados na API do PNCP (GET /api/search/).
     *
     * Regras descobertas:
     * - Múltiplas palavras sem operador = AND (todos os termos devem estar presentes)
     * - "frase entre aspas" = busca exata pela frase
     * - OR explícito funciona: termo1 OR termo2
     * - Queries que retornam > ~30k resultados tendem a ser rate-limitadas
     */
    private const TERMOS_BUSCA = [
        // "tecnologia da informacao" (AND exato) → ~14.350 editais de TI
        'ti'         => '"tecnologia da informacao"',
        // OR entre os termos tributários mais distintivos → ~11.319 editais
        'tributario' => 'tributario OR SEFAZ OR arrecadacao',
        // AND entre "escola" e "sistema" → ~22.564 editais de sistemas escolares
        'educacao'   => '"escola" "sistema"',
        // Combina TI + tributário (dois setores mais específicos)
        'todos'      => '"tecnologia da informacao" OR tributario OR SEFAZ',
    ];

    public function __construct()
    {
        $this->modelEdital = new Edital();
        $this->log         = new LogAuditoria();
    }

    /**
     * Busca e salva editais relevantes do PNCP.
     *
     * @param string $dataInicial  Não usado na busca (mantido para compatibilidade de interface)
     * @param string $dataFinal    Não usado na busca
     * @param string $setor        'ti' | 'tributario' | 'educacao' | 'todos'
     * @param int    $maxPaginas   Páginas a percorrer (50 itens/pág, máx 20)
     */
    public function capturar(
        string $dataInicial = '',
        string $dataFinal   = '',
        string $setor       = 'ti',
        int    $maxPaginas  = 10
    ): array {
        $setor      = array_key_exists($setor, self::SETORES) ? $setor : 'ti';
        $maxPaginas = max(1, min($maxPaginas, 20));

        $resultado = [
            'total_api'     => 0,
            'novos_salvos'  => 0,
            'ja_existentes' => 0,
            'filtrados'     => 0,
            'erros'         => 0,
            'paginas'       => 0,
            'setor'         => self::SETORES[$setor],
        ];

        $keywords      = $this->getKeywords($setor);
        $termoBusca    = self::TERMOS_BUSCA[$setor];
        $tamanhoPagina = 50;
        $pagina        = 1;
        $totalPaginas  = 1;

        do {
            $dados = $this->buscarPagina($termoBusca, $pagina, $tamanhoPagina);

            if ($dados === null) {
                $resultado['erros']++;
                break;
            }

            $total        = (int) ($dados['total'] ?? 0);
            $totalPaginas = max(1, (int) ceil($total / $tamanhoPagina));
            $resultado['total_api'] += count($dados['items'] ?? []);
            $resultado['paginas']    = $totalPaginas;

            foreach ($dados['items'] ?? [] as $item) {
                try {
                    $processado = $this->processarItem($item, $keywords);
                    match ($processado) {
                        'novo'      => $resultado['novos_salvos']++,
                        'existente' => $resultado['ja_existentes']++,
                        default     => $resultado['filtrados']++,
                    };
                } catch (\Exception $e) {
                    $resultado['erros']++;
                    error_log('[PNCP] Erro ao processar item: ' . $e->getMessage());
                }
            }

            $pagina++;

            // Aguarda 1s entre páginas para respeitar rate-limit do PNCP
            if ($pagina <= $totalPaginas && $pagina <= $maxPaginas) {
                usleep(1_000_000);
            }
        } while ($pagina <= $totalPaginas && $pagina <= $maxPaginas);

        $this->log->registrar(
            'PNCP_CAPTURA',
            "Setor: {$setor} | Busca: '{$termoBusca}' | " .
            "Novos: {$resultado['novos_salvos']} | Filtrados: {$resultado['filtrados']} | " .
            "Páginas: " . ($pagina - 1)
        );

        return $resultado;
    }

    // ─── Helpers Públicos ─────────────────────────────────────────────────────

    /**
     * Retorna as keywords do array de configuração para pós-filtro.
     * O setor 'todos' usa a união de todos os conjuntos.
     */
    public function getKeywords(string $setor): array
    {
        return match ($setor) {
            'tributario' => array_unique(array_merge(TI_KEYWORDS, TRIBUTARIO_KEYWORDS)),
            'educacao'   => array_unique(array_merge(TI_KEYWORDS, EDUCACAO_KEYWORDS)),
            'todos'      => array_unique(array_merge(TI_KEYWORDS, TRIBUTARIO_KEYWORDS, EDUCACAO_KEYWORDS)),
            default      => TI_KEYWORDS,
        };
    }

    // ─── Helpers Privados ─────────────────────────────────────────────────────

    private function buscarPagina(string $termoBusca, int $pagina, int $tamanho): ?array
    {
        $url = PNCP_SEARCH_URL . '?' . http_build_query([
            'tipos_documento' => 'edital',
            'q'               => $termoBusca,
            'ordenacao'       => '-data',
            'pagina'          => $pagina,
            'tam_pagina'      => $tamanho,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT      => 'LicitAI/' . APP_VERSION . ' (' . APP_URL . ')',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => false, // PNCP usa certificado não coberto pelo cacert local
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $resposta  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("[PNCP] cURL error: {$curlError}");
            return null;
        }
        if ($httpCode !== 200) {
            error_log("[PNCP] HTTP {$httpCode}: {$url}");
            return null;
        }

        $dados = json_decode($resposta, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[PNCP] JSON inválido: ' . json_last_error_msg());
            return null;
        }

        // Normaliza o campo de itens (a API usa 'items')
        if (!isset($dados['items'])) {
            $dados['items'] = [];
        }

        return $dados;
    }

    /**
     * Processa um item da API search. Retorna 'novo', 'existente' ou 'filtrado'.
     */
    private function processarItem(array $item, array $keywords): string
    {
        $pncpId = $item['numero_controle_pncp'] ?? '';
        if (empty($pncpId)) {
            return 'filtrado';
        }

        // Já temos esse edital?
        if ($this->modelEdital->findByPncpId($pncpId)) {
            return 'existente';
        }

        // Objeto/descrição do edital
        $objeto = $item['description'] ?? $item['title'] ?? '';

        // Pós-filtro: valida se o objeto contém palavras-chave relevantes
        // (a busca por `q` na API faz pré-filtro; este garante precisão)
        if (!$this->ehEditalRelevante($objeto, $keywords)) {
            return 'filtrado';
        }

        // Mapeia campos da nova API para o schema do banco
        $orgao         = $item['orgao_nome']              ?? $item['unidade_nome'] ?? 'Não informado';
        $cnpjOrgao     = $item['orgao_cnpj']              ?? null;
        $modalidade    = $item['modalidade_licitacao_nome'] ?? null;
        $valorEstimado = $item['valor_global']            ?? null;
        $link          = !empty($item['item_url'])
            ? 'https://pncp.gov.br/app/editais' . str_replace('/compras', '', $item['item_url'])
            : ($item['item_url'] ?? null);

        // Data de publicação: formato ISO "2025-05-13T16:52:37.445808" → "2025-05-13"
        $dataPub = null;
        if (!empty($item['data_publicacao_pncp'])) {
            $dataPub = substr($item['data_publicacao_pncp'], 0, 10);
        }

        $this->modelEdital->inserir([
            'pncp_id'           => $pncpId,
            'orgao'             => $orgao,
            'cnpj_orgao'        => $cnpjOrgao,
            'objeto'            => $objeto,
            'modalidade'        => $modalidade,
            'valor_estimado'    => is_numeric($valorEstimado) ? (float) $valorEstimado : null,
            'data_publicacao'   => $dataPub,
            'data_encerramento' => null,
            'link_edital'       => $link,
        ]);

        return 'novo';
    }

    private function ehEditalRelevante(string $objeto, array $keywords): bool
    {
        if (empty($objeto)) {
            return false;
        }
        $lower = mb_strtolower($objeto, 'UTF-8');
        foreach ($keywords as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Tenta obter o texto do Termo de Referência a partir do link do edital.
     *
     * Usa a API de arquivos do PNCP (/orgaos/{cnpj}/compras/{ano}/{seq}/arquivos)
     * para baixar o melhor documento disponível (TR > Projeto Básico > Edital > etc.)
     * e extrair o texto, convertendo PDF via pdftotext quando possível.
     */
    public function obterTextoTR(string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        // Extrai cnpj/ano/seq do link PNCP: https://pncp.gov.br/app/editais/{cnpj}/{ano}/{seq}
        if (!preg_match('#/editais/(\d+)/(\d{4})/(\d+)#', $url, $m)) {
            error_log("[PNCP] URL não reconhecida para extração de TR: {$url}");
            return null;
        }

        [, $cnpj, $ano, $seq] = $m;

        $arquivos = $this->listarArquivosEdital($cnpj, $ano, $seq);
        if (empty($arquivos)) {
            return null;
        }

        // Seleciona o melhor arquivo por prioridade no título
        $melhor = null;
        $prioridade = 99;

        foreach ($arquivos as $arquivo) {
            $titulo = mb_strtolower($arquivo['titulo'] ?? '', 'UTF-8');

            if (
                str_contains($titulo, 'termo de referencia') ||
                str_contains($titulo, 'termo_de_referencia') ||
                str_contains($titulo, ' tr ')
            ) {
                $p = 1;
            } elseif (
                str_contains($titulo, 'projeto basico') ||
                str_contains($titulo, 'projeto_basico')
            ) {
                $p = 2;
            } elseif (str_contains($titulo, 'edital')) {
                $p = 3;
            } elseif (str_contains($titulo, 'justificativa')) {
                $p = 4;
            } else {
                $p = 5;
            }

            if ($p < $prioridade) {
                $prioridade = $p;
                $melhor     = $arquivo;
            }
        }

        // Fallback: primeiro arquivo
        if ($melhor === null) {
            $melhor = $arquivos[0];
        }

        // Ordena candidatos: melhor primeiro, depois os demais como fallback
        $candidatos = [$melhor];
        foreach ($arquivos as $arq) {
            if (($arq['url'] ?? '') !== ($melhor['url'] ?? '')) {
                $candidatos[] = $arq;
            }
        }

        // Tenta baixar cada candidato até obter texto (máx 3 arquivos)
        foreach (array_slice($candidatos, 0, 3) as $idx => $arquivo) {
            $fileUrl = $arquivo['url'] ?? '';
            if (empty($fileUrl)) continue;

            // Até 2 tentativas por arquivo com timeout crescente
            for ($t = 1; $t <= 2; $t++) {
                $ch = curl_init($fileUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 30 * $t,
                    CURLOPT_USERAGENT      => 'LicitAI/' . APP_VERSION,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);

                $conteudo = curl_exec($ch);
                $tipoMime = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err      = curl_error($ch);
                curl_close($ch);

                if ($err || !$conteudo || $httpCode >= 400) {
                    if ($err) error_log("[PNCP] download tentativa {$t} arquivo #{$idx}: {$err}");
                    if ($t === 1) usleep(1_500_000);
                    continue;
                }

                $texto = null;
                if (str_contains($tipoMime, 'pdf') || str_starts_with($conteudo, '%PDF')) {
                    $texto = $this->extrairTextoPdf($conteudo, $fileUrl);
                } elseif (str_contains($tipoMime, 'text') || str_contains($tipoMime, 'html')) {
                    $texto = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($conteudo))), 0, 50000, 'UTF-8');
                }

                if (!empty($texto) && mb_strlen($texto, 'UTF-8') > 100) {
                    return $texto; // texto útil obtido
                }
                break; // arquivo baixou mas sem texto → tenta próximo candidato
            }
        }

        return null;
    }

    /**
     * Chama a API PNCP de arquivos do edital e retorna o array de arquivos.
     *
     * @return array<int, array{titulo: string, url: string, sequencialDocumento: int, tipoDocumentoNome: string}>
     */
    /**
     * Lista arquivos do edital no PNCP com até 3 tentativas e backoff.
     * Timeout por tentativa aumenta progressivamente: 20s → 30s → 40s.
     *
     * @return array<int, array{titulo: string, url: string, sequencialDocumento: int, tipoDocumentoNome: string}>
     */
    public function listarArquivosEdital(string $cnpj, string $ano, string $seq): array
    {
        $apiUrl      = PNCP_API_URL . "/orgaos/{$cnpj}/compras/{$ano}/{$seq}/arquivos";
        $ultimoErro  = '';

        for ($tentativa = 1; $tentativa <= 3; $tentativa++) {
            $timeout = 20 * $tentativa; // 20s → 40s → 60s

            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_USERAGENT      => 'LicitAI/' . APP_VERSION . ' (' . APP_URL . ')',
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_FOLLOWLOCATION => true,
            ]);

            $resposta  = curl_exec($ch);
            $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                $ultimoErro = "cURL: {$curlError}";
                error_log("[PNCP] listarArquivosEdital tentativa {$tentativa}/3 — {$ultimoErro}");
                if ($tentativa < 3) usleep(2_000_000 * $tentativa);
                continue;
            }

            // 404/410: edital sem arquivos — não adianta retentar
            if ($httpCode === 404 || $httpCode === 410) return [];

            // 5xx ou outros: retenta
            if ($httpCode !== 200) {
                $ultimoErro = "HTTP {$httpCode}";
                error_log("[PNCP] listarArquivosEdital tentativa {$tentativa}/3 — {$ultimoErro}");
                if ($tentativa < 3) usleep(2_000_000 * $tentativa);
                continue;
            }

            $dados = json_decode($resposta, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($dados)) {
                $ultimoErro = 'JSON inválido: ' . json_last_error_msg();
                error_log("[PNCP] listarArquivosEdital tentativa {$tentativa}/3 — {$ultimoErro}");
                if ($tentativa < 3) usleep(1_000_000);
                continue;
            }

            return $dados; // sucesso
        }

        error_log("[PNCP] listarArquivosEdital falhou após 3 tentativas: {$ultimoErro}");
        return [];
    }

    private function extrairTextoPdf(string $pdfContent, string $url): ?string
    {
        if (function_exists('shell_exec') && !in_array('shell_exec', explode(',', ini_get('disable_functions')))) {
            $tmpFile = sys_get_temp_dir() . '/licita_' . md5($url) . '.pdf';
            file_put_contents($tmpFile, $pdfContent);
            $null  = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
            $texto = shell_exec('pdftotext -enc UTF-8 ' . escapeshellarg($tmpFile) . ' - 2>' . $null);
            @unlink($tmpFile);
            if ($texto && strlen(trim($texto)) > 100) {
                return mb_substr(trim($texto), 0, 50000, 'UTF-8');
            }
        }

        $texto = $this->extrairStringsPdf($pdfContent);
        return $texto ?: "[PDF não processável — URL: {$url}]";
    }

    private function extrairStringsPdf(string $pdfContent): string
    {
        preg_match_all('/\(([^)]{4,})\)/', $pdfContent, $matches);
        $textos = array_filter($matches[1] ?? [], fn($t) => preg_match('/[a-zA-ZÀ-ÿ]{3,}/', $t));
        $resultado = implode(' ', array_map(
            fn($t) => preg_replace('/\\\\[0-7]{3}|\\\\[nrtbf\\\\]/', ' ', $t),
            $textos
        ));
        return mb_substr(trim(preg_replace('/\s+/', ' ', $resultado)), 0, 30000, 'UTF-8');
    }
}
