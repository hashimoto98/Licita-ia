<?php
/**
 * LicitAI - Controller de Integração com OpenRouter (IA)
 *
 * Envia o Termo de Referência para a LLM e extrai os requisitos
 * de software/módulos exigidos no edital.
 */
class OpenRouterController
{
    private const PROMPT_SISTEMA = <<<'PROMPT'
Você é especialista em licitações públicas de TI no Brasil.
Analise o Termo de Referência e extraia SISTEMAS e FUNCIONALIDADES exigidos.

REGRAS:
- "modulos": nomes de sistemas/módulos FUNCIONAIS (ex: "sistema de gestão tributária", "folha de pagamento", "portal da transparência")
- "funcionalidades": capacidades funcionais do sistema (ex: "emissão de nota fiscal", "controle de estoque", "gestão de contratos")
- "tecnologias": SOMENTE plataformas/categorias relevantes como "cloud", "web", "mobile", "erp", "saas" — NÃO inclua linguagens de programação (java, python, angular, etc.)
- "integracoes": sistemas externos com que deve se integrar (ex: "sefaz", "pncp", "siconfi", "banco do brasil")
- "resumo": o que está sendo contratado em 1 frase objetiva

Responda SOMENTE com JSON válido, sem markdown. Arrays vazios = [].
Normalize: minúsculas, sem abreviações desnecessárias, sem nomes de linguagens de programação.
PROMPT;

    private const PROMPT_OBJETO = <<<'PROMPT'
Você é especialista em licitações públicas de TI no Brasil.
Analise o OBJETO DA LICITAÇÃO (descrição curta) e deduza os sistemas funcionais e funcionalidades necessárias.

REGRAS IGUAIS: módulos = sistemas funcionais; tecnologias = plataformas (cloud, web, mobile, erp) — SEM linguagens de programação; integrações = sistemas externos.

Responda SOMENTE com JSON válido, sem markdown:
{"modulos":["..."],"funcionalidades":["..."],"tecnologias":["..."],"integracoes":["..."],"resumo":"..."}
Se algum array ficaria vazio, use []. Normalize: minúsculas.
PROMPT;

    private LogAuditoria $log;

    public function __construct()
    {
        $this->log = new LogAuditoria();
    }

    /**
     * Analisa o texto do TR e retorna os requisitos extraídos pela IA.
     *
     * @param  string $textoTr Texto bruto do Termo de Referência
     * @return array{modulos: string[], funcionalidades: string[], tecnologias: string[], integracoes: string[], resumo: string}|null
     */
    public function extrairRequisitos(string $textoTr): ?array
    {
        if (empty(trim($textoTr))) {
            return null;
        }

        // Limita o texto para não exceder o contexto do modelo (~100k tokens)
        $textoTrucado = mb_substr($textoTr, 0, 40000, 'UTF-8');

        $payload = [
            'model'       => OPENROUTER_MODEL,
            'max_tokens'  => 2000,
            'temperature' => 0.1,
            'messages'    => [
                ['role' => 'system', 'content' => self::PROMPT_SISTEMA],
                ['role' => 'user',   'content' => "Analise este Termo de Referência:\n\n{$textoTrucado}"],
            ],
        ];

        // Tenta até 3 vezes — se JSON inválido, retenta com temperatura levemente maior
        for ($i = 1; $i <= 3; $i++) {
            $resposta = $this->chamarApi($payload);
            if ($resposta === null) return null; // erro de rede/auth → sem sentido retentar aqui

            $resultado = $this->parseResposta($resposta);
            if ($resultado !== null) return $resultado;

            // JSON inválido: aumenta temperatura e tenta novamente
            error_log("[OpenRouter] extrairRequisitos JSON inválido tentativa {$i}/3 — retentando");
            $payload['temperature'] = min(0.3, 0.1 + $i * 0.1);
            if ($i < 3) usleep(1_500_000);
        }

        return null;
    }

    /**
     * Analisa o objeto da licitação (string curta) quando o TR completo não está disponível.
     *
     * @param  string $objeto Descrição curta do objeto da licitação
     * @return array{modulos: string[], funcionalidades: string[], tecnologias: string[], integracoes: string[], resumo: string}|null
     */
    public function analisarObjeto(string $objeto): ?array
    {
        if (empty(trim($objeto))) {
            return null;
        }

        $objetoTruncado = mb_substr($objeto, 0, 2000, 'UTF-8');

        $payload = [
            'model'       => OPENROUTER_MODEL,
            'max_tokens'  => 2000,
            'temperature' => 0.1,
            'messages'    => [
                ['role' => 'system', 'content' => self::PROMPT_OBJETO],
                ['role' => 'user',   'content' => "Objeto da licitação:\n\n{$objetoTruncado}"],
            ],
        ];

        for ($i = 1; $i <= 3; $i++) {
            $resposta = $this->chamarApi($payload);
            if ($resposta === null) return null;

            $resultado = $this->parseResposta($resposta);
            if ($resultado !== null) return $resultado;

            error_log("[OpenRouter] analisarObjeto JSON inválido tentativa {$i}/3 — retentando");
            $payload['temperature'] = min(0.3, 0.1 + $i * 0.1);
            if ($i < 3) usleep(1_500_000);
        }

        return null;
    }

    // ─── Privados ─────────────────────────────────────────────────────────────

    /**
     * Chama a API OpenRouter com até $maxTentativas tentativas e backoff exponencial.
     * Erros transitórios (timeout, 429, 5xx) são retentados; erros permanentes (401, 400) não.
     */
    private function chamarApi(array $payload, int $maxTentativas = 3): ?string
    {
        if (empty(OPENROUTER_API_KEY) || OPENROUTER_API_KEY === 'sua-chave-aqui') {
            error_log('[OpenRouter] API Key não configurada.');
            return null;
        }

        $ultimoErro = '';

        for ($tentativa = 1; $tentativa <= $maxTentativas; $tentativa++) {
            $ch = curl_init(OPENROUTER_API_URL);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_TIMEOUT        => 90,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . OPENROUTER_API_KEY,
                    'HTTP-Referer: ' . APP_URL,
                    'X-Title: ' . APP_NAME,
                ],
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $resposta  = curl_exec($ch);
            $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // Erro de rede: retenta
            if ($curlError) {
                $ultimoErro = "cURL: {$curlError}";
                error_log("[OpenRouter] tentativa {$tentativa}/{$maxTentativas} — {$ultimoErro}");
                if ($tentativa < $maxTentativas) usleep(2_000_000 * $tentativa);
                continue;
            }

            // Rate-limit (429) ou erro de servidor (5xx): backoff maior
            if ($httpCode === 429 || $httpCode >= 500) {
                $ultimoErro = "HTTP {$httpCode}";
                error_log("[OpenRouter] tentativa {$tentativa}/{$maxTentativas} — {$ultimoErro}");
                if ($tentativa < $maxTentativas) usleep(3_000_000 * $tentativa);
                continue;
            }

            // Erro permanente (401, 400, 403): não adianta retentar
            if ($httpCode !== 200) {
                error_log("[OpenRouter] HTTP {$httpCode} permanente: " . substr($resposta, 0, 300));
                return null;
            }

            $json     = json_decode($resposta, true);
            $conteudo = $json['choices'][0]['message']['content'] ?? null;

            if (!$conteudo) {
                $ultimoErro = 'Resposta sem conteúdo';
                error_log("[OpenRouter] tentativa {$tentativa}/{$maxTentativas} — {$ultimoErro}");
                if ($tentativa < $maxTentativas) usleep(2_000_000);
                continue;
            }

            return $conteudo; // sucesso
        }

        error_log("[OpenRouter] Todas {$maxTentativas} tentativas falharam. Último erro: {$ultimoErro}");
        return null;
    }

    private function parseResposta(string $conteudo): ?array
    {
        // Remove markdown se a LLM insistir em envolver com ```json
        $conteudo = preg_replace('/^```(?:json)?\s*/m', '', $conteudo);
        $conteudo = preg_replace('/\s*```$/m', '', $conteudo);
        $conteudo = trim($conteudo);

        $dados = json_decode($conteudo, true);

        // Tenta extrair JSON do meio do texto caso a LLM tenha adicionado prefácio
        if (json_last_error() !== JSON_ERROR_NONE) {
            if (preg_match('/\{[\s\S]*\}/u', $conteudo, $m)) {
                $dados = json_decode($m[0], true);
            }
        }

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($dados)) {
            error_log('[OpenRouter] JSON inválido na resposta da IA: ' . json_last_error_msg());
            return null;
        }

        return [
            'modulos'        => array_values(array_filter((array) ($dados['modulos']        ?? []))),
            'funcionalidades'=> array_values(array_filter((array) ($dados['funcionalidades'] ?? []))),
            'tecnologias'    => array_values(array_filter((array) ($dados['tecnologias']     ?? []))),
            'integracoes'    => array_values(array_filter((array) ($dados['integracoes']     ?? []))),
            'resumo'         => trim((string) ($dados['resumo'] ?? '')),
        ];
    }
}
