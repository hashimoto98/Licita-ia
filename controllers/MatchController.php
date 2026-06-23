<?php
/**
 * LicitAI - Motor de Match Semântico
 *
 * Cruza os requisitos extraídos pela IA com o catálogo de módulos da empresa
 * e calcula a porcentagem de compatibilidade do edital.
 */
class MatchController
{
    private Modulo $modelModulo;
    private Edital $modelEdital;
    private ApiPncpController $pncpCtrl;
    private OpenRouterController $iaCtrl;
    private LogAuditoria $log;

    public function __construct()
    {
        $this->modelModulo = new Modulo();
        $this->modelEdital = new Edital();
        $this->pncpCtrl    = new ApiPncpController();
        $this->iaCtrl      = new OpenRouterController();
        $this->log         = new LogAuditoria();
    }

    /**
     * Pipeline completo: busca TR → IA → Match → salva no banco.
     *
     * @return array{sucesso: bool, porcentagem: float, mensagem: string}
     */
    public function analisarEdital(int $editalId, ?int $usuarioId = null): array
    {
        $edital = $this->modelEdital->findById($editalId);
        if (!$edital) {
            return ['sucesso' => false, 'porcentagem' => 0.0, 'mensagem' => 'Edital não encontrado.'];
        }

        if ($edital['status'] === 'analisando') {
            return ['sucesso' => false, 'porcentagem' => 0.0, 'mensagem' => 'Análise já em andamento.'];
        }

        $this->modelEdital->marcarAnalisando($editalId);

        try {
            // ── Etapa 1: Obter texto do TR (com cadeia de fallback) ───────────

            // Etapa 1a: texto_tr já salvo no banco
            $textoTr = $edital['texto_tr'] ?? '';

            // Etapa 1b: busca via API de arquivos do PNCP
            if (empty($textoTr) && !empty($edital['link_edital'])) {
                $textoTr = $this->pncpCtrl->obterTextoTR($edital['link_edital']) ?? '';
            }

            // Etapa 1c: fallback — analisa apenas pelo objeto (descrição curta)
            $usouObjeto = false;
            if (empty($textoTr) && !empty($edital['objeto'])) {
                $usouObjeto = true;
            }

            // Etapa 1d: sem texto e sem objeto → encerra
            if (empty($textoTr) && empty($edital['objeto'])) {
                $this->modelEdital->marcarErro($editalId, 'Não foi possível obter o texto do Termo de Referência.');
                return ['sucesso' => false, 'porcentagem' => 0.0, 'mensagem' => 'TR não disponível.'];
            }

            // ── Etapa 2: Extração de requisitos via IA ────────────────────────
            if ($usouObjeto) {
                // Análise baseada apenas no objeto (sem TR)
                $requisitos = $this->iaCtrl->analisarObjeto($edital['objeto']);
                // Registra no campo texto_tr que foi análise por fallback
                $textoTr = '[Análise baseada no objeto: ' . $edital['objeto'] . ']';
            } else {
                $requisitos = $this->iaCtrl->extrairRequisitos($textoTr);
            }

            // Etapa 2b: se IA falhou, tenta uma última vez via objeto (se ainda não tentou)
            if ($requisitos === null && !$usouObjeto && !empty($edital['objeto'])) {
                error_log("[Match] IA falhou com TR — retentando via objeto do edital #{$editalId}");
                $requisitos  = $this->iaCtrl->analisarObjeto($edital['objeto']);
                $textoTr     = '[Análise baseada no objeto: ' . $edital['objeto'] . ']';
            }

            // Etapa 2c: fallback sem IA — matching direto de keywords contra o objeto
            if ($requisitos === null) {
                error_log("[Match] IA completamente indisponível — usando keyword match direto para #{$editalId}");
                $requisitos = $this->keywordMatchSemIa($edital['objeto'] ?? '');
                $textoTr    = '[Match direto por keywords — IA indisponível]';
            }

            if ($requisitos === null) {
                $this->modelEdital->marcarErro($editalId, 'Falha na comunicação com a IA e sem objeto para análise.');
                return ['sucesso' => false, 'porcentagem' => 0.0, 'mensagem' => 'Erro na IA.'];
            }

            // ── Etapa 3: Algoritmo de Match ──────────────────────────────────
            $modulos = $this->modelModulo->todos(apenasAtivos: true);
            $itensMatch = $this->calcularMatch($requisitos, $modulos);
            $porcentagem = $itensMatch['porcentagem'];

            // ── Etapa 4: Persistir resultado ─────────────────────────────────
            $this->modelEdital->salvarAnalise($editalId, $textoTr, $requisitos, $porcentagem, $itensMatch);

            $this->log->registrar(
                'EDITAL_ANALISE',
                "Edital #{$editalId} | Match: {$porcentagem}% | Requisitos IA: " . count($requisitos['modulos'] ?? []),
                $usuarioId
            );

            return [
                'sucesso'      => true,
                'porcentagem'  => $porcentagem,
                'itens_match'  => $itensMatch,
                'mensagem'     => "Análise concluída. Compatibilidade: {$porcentagem}%",
            ];

        } catch (\Exception $e) {
            $msg = 'Erro inesperado: ' . $e->getMessage();
            $this->modelEdital->marcarErro($editalId, $msg);
            error_log("[Match] {$msg}");
            return ['sucesso' => false, 'porcentagem' => 0.0, 'mensagem' => $msg];
        }
    }

    /**
     * Fallback sem IA: extrai termos diretamente do texto do objeto por correspondência
     * de keywords dos módulos. Sem LLM — funciona mesmo com API indisponível.
     * Retorna estrutura compatível com o retorno da IA (mesmas chaves).
     */
    private function keywordMatchSemIa(string $objeto): ?array
    {
        if (empty(trim($objeto))) return null;

        $textoNorm = mb_strtolower(preg_replace('/\s+/', ' ', $objeto), 'UTF-8');
        $modulos   = $this->modelModulo->todos(apenasAtivos: true);
        $termos    = [];

        foreach ($modulos as $mod) {
            $kws = json_decode($mod['palavras_chave'] ?? '[]', true) ?: [];
            foreach ($kws as $kw) {
                $kwNorm = mb_strtolower(trim($kw), 'UTF-8');
                if (mb_strlen($kwNorm, 'UTF-8') >= 4 && str_contains($textoNorm, $kwNorm)) {
                    $termos[] = $kwNorm;
                }
            }
        }

        $termos = array_values(array_unique($termos));

        return [
            'modulos'         => $termos,
            'funcionalidades' => [],
            'tecnologias'     => [],
            'integracoes'     => [],
            'resumo'          => 'Análise automática por keywords (IA indisponível): ' . mb_substr($objeto, 0, 120, 'UTF-8'),
        ];
    }

    /**
     * Calcula a aderência entre os requisitos da IA e os módulos da empresa.
     *
     * O algoritmo:
     * 1. Une todos os termos extraídos (módulos + funcionalidades + tecnologias + integrações).
     * 2. Para cada termo, verifica se algum módulo da empresa tem uma palavra-chave
     *    que seja substring do termo ou vice-versa (matching bidirecional).
     * 3. Classifica cada módulo da empresa como MATCH ou PENDENTE.
     * 4. Calcula: (módulos da empresa com pelo menos 1 hit) / (total de módulos ativos).
     *    Ponderação: módulos com mais keywords atendidas pesam mais.
     *
     * @return array{porcentagem: float, modulos_match: array, modulos_pendente: array, termos_atendidos: array, termos_nao_atendidos: array}
     */
    public function calcularMatch(array $requisitos, array $modulosEmpresa): array
    {
        // Agrega todos os termos de requisito em uma lista flat
        $termosRequisito = array_merge(
            $requisitos['modulos']         ?? [],
            $requisitos['funcionalidades'] ?? [],
            $requisitos['tecnologias']     ?? [],
            $requisitos['integracoes']     ?? [],
        );

        $termosRequisito = array_values(array_unique(
            array_filter(array_map(
                fn($t) => preg_replace('/\s+/', ' ', trim(str_replace('_', ' ', mb_strtolower($t, 'UTF-8')))),
                $termosRequisito
            ))
        ));

        if (empty($termosRequisito) || empty($modulosEmpresa)) {
            return $this->resultadoVazio($termosRequisito, $modulosEmpresa);
        }

        $modulosMatch    = [];
        $modulosPendente = [];
        $termosAtendidos = [];

        foreach ($modulosEmpresa as $modulo) {
            $keywords = json_decode($modulo['palavras_chave'] ?? '[]', true) ?: [];
            $keywords = array_map(fn($k) => mb_strtolower(trim($k), 'UTF-8'), $keywords);

            $hitsModulo = [];

            foreach ($termosRequisito as $termo) {
                foreach ($keywords as $kw) {
                    $tLen = mb_strlen($termo, 'UTF-8');
                    $kLen = mb_strlen($kw, 'UTF-8');

                    $match =
                        // Correspondência exata: lida com abreviações curtas (ead, lms, dte)
                        $termo === $kw
                        // Substring bidirecional: ambos >= 5 chars evita "ava" em "java"
                        || ($tLen >= 5 && $kLen >= 5 && str_contains($termo, $kw))
                        || ($tLen >= 5 && $kLen >= 5 && str_contains($kw, $termo))
                        // Similaridade textual: só para strings longas, threshold mais exigente
                        || ($tLen >= 8 && $kLen >= 8
                            && (similar_text($termo, $kw) * 2 / ($tLen + $kLen)) > 0.75);

                    if ($match) {
                        $hitsModulo[] = $termo;
                        break;
                    }
                }
            }

            $hitsModulo = array_unique($hitsModulo);

            if (!empty($hitsModulo)) {
                $modulosMatch[]  = [
                    'id'       => $modulo['id'],
                    'nome'     => $modulo['nome'],
                    'categoria'=> $modulo['categoria'] ?? '',
                    'hits'     => array_values($hitsModulo),
                ];
                $termosAtendidos = array_unique(array_merge($termosAtendidos, $hitsModulo));
            } else {
                $modulosPendente[] = [
                    'id'       => $modulo['id'],
                    'nome'     => $modulo['nome'],
                    'categoria'=> $modulo['categoria'] ?? '',
                ];
            }
        }

        $termosNaoAtendidos = array_values(array_diff($termosRequisito, $termosAtendidos));
        $termosAtendidos    = array_values($termosAtendidos);

        // Porcentagem baseada em módulos da empresa cobertos pelo edital
        $totalModulos = count($modulosEmpresa);
        $matched      = count($modulosMatch);
        $porcentagem  = $totalModulos > 0
            ? round(($matched / $totalModulos) * 100, 2)
            : 0.0;

        return [
            'porcentagem'         => $porcentagem,
            'modulos_match'       => $modulosMatch,
            'modulos_pendente'    => $modulosPendente,
            'termos_atendidos'    => $termosAtendidos,
            'termos_nao_atendidos'=> $termosNaoAtendidos,
            'total_modulos'       => $totalModulos,
            'total_matched'       => $matched,
            'total_termos'        => count($termosRequisito),
        ];
    }

    private function resultadoVazio(array $termos, array $modulosEmpresa = []): array
    {
        $modulosPendente = array_map(fn($m) => [
            'id'       => $m['id'],
            'nome'     => $m['nome'],
            'categoria'=> $m['categoria'] ?? '',
        ], $modulosEmpresa);

        return [
            'porcentagem'         => 0.0,
            'modulos_match'       => [],
            'modulos_pendente'    => $modulosPendente,
            'termos_atendidos'    => [],
            'termos_nao_atendidos'=> $termos,
            'total_modulos'       => count($modulosEmpresa),
            'total_matched'       => 0,
            'total_termos'        => count($termos),
        ];
    }
}
