<?php
/**
 * LicitAI - Controller de Editais
 *
 * Gerencia o ciclo completo do edital:
 * listagem, criação manual, edição, exclusão, análise IA e exportação.
 */
class EditaisController
{
    private Edital $model;
    private LogAuditoria $log;

    public function __construct()
    {
        $this->model = new Edital();
        $this->log   = new LogAuditoria();
    }

    // ─── Listagem / Dashboard ─────────────────────────────────────────────────

    public function listar(): void
    {
        AuthController::requerAutenticacao();

        $pagina  = max(1, (int) ($_GET['p'] ?? 1));
        $filtros = [
            'busca'      => htmlspecialchars($_GET['busca']      ?? '', ENT_QUOTES),
            'modalidade' => htmlspecialchars($_GET['modalidade'] ?? '', ENT_QUOTES),
            'status'     => htmlspecialchars($_GET['status']     ?? '', ENT_QUOTES),
            'match_min'  => $_GET['match_min'] ?? '',
        ];

        $editais      = $this->model->listar($pagina, $filtros);
        $total        = $this->model->total($filtros);
        $totalPaginas = max(1, (int) ceil($total / ITEMS_PER_PAGE));
        $modalidades  = $this->model->modalidades();

        $stats = $this->montarStats();
        $dadosGrafico = [
            'semanas'     => $this->model->capturadosPorSemana(8),
            'modalidades' => $this->model->distribuicaoPorModalidade(),
        ];

        include __DIR__ . '/../views/dashboard.php';
    }

    // ─── Detalhes ─────────────────────────────────────────────────────────────

    public function detalhe(int $id): void
    {
        AuthController::requerAutenticacao();

        $edital = $this->model->findById($id);
        if (!$edital) {
            header('Location: ?page=dashboard&erro=edital_nao_encontrado');
            exit;
        }

        $requisitosIa = json_decode($edital['requisitos_ia'] ?? 'null', true);
        $itensMatch   = json_decode($edital['itens_match']   ?? 'null', true);

        include __DIR__ . '/../views/detalhes.php';
    }

    // ─── CRUD Manual ──────────────────────────────────────────────────────────

    /**
     * Exibe o formulário de criação ou edição de edital.
     */
    public function formulario(?int $id = null): void
    {
        AuthController::requerAutenticacao();
        $edital = $id ? $this->model->findById($id) : null;
        include __DIR__ . '/../views/editais/form.php';
    }

    /**
     * Persiste um edital criado ou editado manualmente.
     */
    public function salvarManual(): void
    {
        AuthController::requerAutenticacao();
        $this->verificarCsrf();

        $id     = !empty($_POST['id']) ? (int) $_POST['id'] : null;
        $objeto = trim($_POST['objeto'] ?? '');
        $orgao  = trim($_POST['orgao']  ?? '');

        if (empty($objeto) || empty($orgao)) {
            $this->redirecionar('?page=editais&action=novo&erro=campos_obrigatorios');
        }

        $dados = [
            'pncp_id'           => trim($_POST['pncp_id']           ?? '') ?: null,
            'orgao'             => $orgao,
            'cnpj_orgao'        => preg_replace('/\D/', '', $_POST['cnpj_orgao'] ?? '') ?: null,
            'objeto'            => $objeto,
            'modalidade'        => trim($_POST['modalidade']         ?? '') ?: null,
            'valor_estimado'    => !empty($_POST['valor_estimado']) ? (float) str_replace(',', '.', $_POST['valor_estimado']) : null,
            'data_publicacao'   => !empty($_POST['data_publicacao'])  ? $_POST['data_publicacao']  : null,
            'data_encerramento' => !empty($_POST['data_encerramento'])? $_POST['data_encerramento']: null,
            'link_edital'       => filter_var(trim($_POST['link_edital'] ?? ''), FILTER_VALIDATE_URL) ?: null,
        ];

        $textoTr = trim($_POST['texto_tr'] ?? '');
        $usuarioId = $_SESSION['usuario_id'] ?? null;

        if ($id) {
            // Atualização
            $this->atualizarEdital($id, $dados, $textoTr);
            $this->log->registrar('EDITAL_ATUALIZADO', "ID: {$id} | Órgão: {$orgao}", $usuarioId);
            $this->redirecionar("?page=detalhes&id={$id}&ok=atualizado");
        } else {
            // Criação
            $novoId = $this->model->inserir($dados);

            // Se veio texto do TR, dispara análise imediatamente
            if ($textoTr && $novoId) {
                $db = Database::getInstance()->getConnection();
                $db->prepare("UPDATE editais SET texto_tr = ? WHERE id = ?")->execute([$textoTr, $novoId]);
                $matchCtrl = new MatchController();
                $matchCtrl->analisarEdital($novoId, $usuarioId);
            }

            $this->log->registrar('EDITAL_CRIADO', "ID: {$novoId} | Órgão: {$orgao}", $usuarioId);
            $this->redirecionar("?page=detalhes&id={$novoId}&ok=criado");
        }
    }

    /**
     * Exclui um edital (apenas admin).
     */
    public function excluir(int $id): void
    {
        AuthController::requerAdmin();
        $this->verificarCsrf();

        $edital = $this->model->findById($id);
        if ($edital) {
            $this->model->deletar($id);
            $this->log->registrar('EDITAL_EXCLUIDO', "ID: {$id} | Órgão: {$edital['orgao']}", $_SESSION['usuario_id'] ?? null);
        }

        $this->redirecionar('?page=dashboard&ok=excluido');
    }

    // ─── Análise IA ───────────────────────────────────────────────────────────

    public function analisar(int $id): void
    {
        AuthController::requerAutenticacao();
        $this->verificarCsrf();

        $matchCtrl = new MatchController();
        $resultado = $matchCtrl->analisarEdital($id, $_SESSION['usuario_id'] ?? null);

        header('Content-Type: application/json');
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ─── Captura PNCP ─────────────────────────────────────────────────────────

    public function capturarPncp(): void
    {
        AuthController::requerAdmin();
        $this->verificarCsrf();

        $dataInicial = $_POST['data_inicial'] ?? date('Y-m-d');
        $dataFinal   = $_POST['data_final']   ?? date('Y-m-d');
        $setor       = $_POST['setor']        ?? 'ti';
        $maxPaginas  = min((int) ($_POST['max_paginas'] ?? 10), 20);

        $pncpCtrl  = new ApiPncpController();
        $resultado = $pncpCtrl->capturar($dataInicial, $dataFinal, $setor, $maxPaginas);

        header('Content-Type: application/json');
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ─── Relatórios ───────────────────────────────────────────────────────────

    /**
     * Exibe a página de relatórios com KPIs, gráficos e tabelas consolidadas.
     */
    public function relatorios(): void
    {
        AuthController::requerAutenticacao();

        $stats     = $this->montarStats();
        $topEditais= $this->model->topPorMatch(20);
        $modulos   = (new Modulo())->todos(apenasAtivos: false);
        $faixas    = $this->model->distribuicaoPorFaixaMatch();
        $mensal    = $this->model->capturadosPorMes(6);

        include __DIR__ . '/../views/relatorios.php';
    }

    /**
     * Exporta dados em CSV (editais ou módulos).
     */
    public function exportarCsv(string $tipo): void
    {
        AuthController::requerAutenticacao();

        if ($tipo === 'editais') {
            $linhas  = $this->model->todosCsv();
            $cabecalho = ['ID','PNCP ID','Órgão','CNPJ','Objeto','Modalidade','Valor Estimado','Data Publicação','Data Encerramento','Match %','Status','Criado em'];
            $arquivo = 'editais_licita_ai_' . date('Ymd_His') . '.csv';

            $mapear = fn(array $r) => [
                $r['id'], $r['pncp_id'] ?? '', $r['orgao'], $r['cnpj_orgao'] ?? '',
                $r['objeto'], $r['modalidade'] ?? '', $r['valor_estimado'] ?? '',
                $r['data_publicacao'] ?? '', $r['data_encerramento'] ?? '',
                $r['porcentagem_match'] ?? '', $r['status'], $r['criado_em'],
            ];
        } else {
            $modelModulo = new Modulo();
            $linhas      = $modelModulo->todos(apenasAtivos: false);
            $cabecalho   = ['ID','Nome','Categoria','Versão','Palavras-chave','Ativo','Criado em'];
            $arquivo     = 'modulos_licita_ai_' . date('Ymd_His') . '.csv';

            $mapear = fn(array $r) => [
                $r['id'], $r['nome'], $r['categoria'] ?? '', $r['versao'] ?? '',
                implode('; ', json_decode($r['palavras_chave'] ?? '[]', true) ?: []),
                $r['ativo'] ? 'Sim' : 'Não',
                $r['criado_em'],
            ];
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $arquivo . '"');
        header('Cache-Control: no-cache');

        $fp = fopen('php://output', 'w');
        // BOM UTF-8 para compatibilidade com Excel
        fputs($fp, "\xEF\xBB\xBF");
        fputcsv($fp, $cabecalho, ';');
        foreach ($linhas as $linha) {
            fputcsv($fp, $mapear($linha), ';');
        }
        fclose($fp);

        $this->log->registrar('EXPORTAR_CSV', "Tipo: {$tipo}", $_SESSION['usuario_id'] ?? null);
        exit;
    }

    // ─── Consulta CNPJ (ReceitaWS) ────────────────────────────────────────────

    /**
     * Proxy seguro para a API da ReceitaWS — evita expor requisições externas no front-end.
     */
    public function consultarCnpj(string $cnpj): void
    {
        AuthController::requerAutenticacao();
        header('Content-Type: application/json; charset=utf-8');

        $cnpj = preg_replace('/\D/', '', $cnpj);
        if (strlen($cnpj) !== 14) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'CNPJ inválido.']);
            exit;
        }

        $url = RECEITA_WS_URL . '/' . $cnpj;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERAGENT      => 'LicitAI/' . APP_VERSION,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resposta = curl_exec($ch);
        $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$resposta || $code !== 200) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'CNPJ não encontrado ou serviço indisponível.']);
            exit;
        }

        $dados = json_decode($resposta, true);
        if (($dados['status'] ?? '') === 'ERROR') {
            echo json_encode(['sucesso' => false, 'mensagem' => $dados['message'] ?? 'CNPJ inválido.']);
            exit;
        }

        echo json_encode([
            'sucesso'      => true,
            'razao_social' => $dados['nome']      ?? '',
            'municipio'    => $dados['municipio']  ?? '',
            'uf'           => $dados['uf']         ?? '',
            'situacao'     => $dados['situacao']   ?? '',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function montarStats(): array
    {
        $stats = [
            'total'      => $this->model->total(),
            'analisados' => 0,
            'alta_comp'  => $this->model->totalAltaCompatibilidade(),
            'valor_total'=> $this->model->valorTotalEstimado(),
            'pendentes'  => 0,
        ];
        foreach ($this->model->totalPorStatus() as $row) {
            if ($row['status'] === 'analisado') $stats['analisados'] = $row['total'];
            if ($row['status'] === 'novo')      $stats['pendentes']  = $row['total'];
        }
        return $stats;
    }

    private function atualizarEdital(int $id, array $dados, string $textoTr): void
    {
        $db = Database::getInstance()->getConnection();
        $db->prepare("
            UPDATE editais SET
                pncp_id = ?, orgao = ?, cnpj_orgao = ?, objeto = ?, modalidade = ?,
                valor_estimado = ?, data_publicacao = ?, data_encerramento = ?,
                link_edital = ?, atualizado_em = CURRENT_TIMESTAMP
            WHERE id = ?
        ")->execute([
            $dados['pncp_id'], $dados['orgao'], $dados['cnpj_orgao'], $dados['objeto'],
            $dados['modalidade'], $dados['valor_estimado'], $dados['data_publicacao'],
            $dados['data_encerramento'], $dados['link_edital'], $id,
        ]);

        if ($textoTr) {
            $db->prepare("UPDATE editais SET texto_tr = ?, status = 'novo', atualizado_em = CURRENT_TIMESTAMP WHERE id = ?")
               ->execute([$textoTr, $id]);
        }
    }

    private function verificarCsrf(): void
    {
        $token    = $_POST['csrf_token'] ?? '';
        $esperado = $_SESSION['csrf_token'] ?? '';
        if (!$esperado || !hash_equals($esperado, $token)) {
            http_response_code(403);
            die('Token CSRF inválido.');
        }
    }

    private function redirecionar(string $url): never
    {
        header("Location: {$url}");
        exit;
    }
}
