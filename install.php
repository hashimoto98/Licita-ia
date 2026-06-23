<?php
/**
 * LicitAI - Script de Instalação do Banco de Dados
 *
 * Execute UMA única vez: php install.php  (ou acesse via browser com senha)
 * Cria todas as tabelas necessárias para o funcionamento do sistema.
 *
 * @security Remova ou proteja este arquivo após a instalação.
 */

// Proteção mínima: requer confirmação via argumento CLI ou parâmetro web
$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    $senhaInstall = $_GET['token'] ?? '';
    if ($senhaInstall !== 'INSTALAR_LICITA_IA_2025') {
        http_response_code(403);
        exit("Acesso negado. Passe ?token=INSTALAR_LICITA_IA_2025 para prosseguir.\n");
    }
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/Database.php';

$pdo = Database::getInstance()->getConnection();

echo $isCli ? "\n=== LicitAI - Instalação do Banco ===\n\n" : '<pre>=== LicitAI - Instalação ===</pre>';

$tabelas = [];

// ─── Usuários ─────────────────────────────────────────────────────────────────
$tabelas['usuarios'] = "
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id`               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `nome`             VARCHAR(120)     NOT NULL,
    `email`            VARCHAR(180)     NOT NULL UNIQUE,
    `senha`            VARCHAR(255)     NOT NULL COMMENT 'bcrypt hash',
    `perfil`           ENUM('admin','analista','visualizador') NOT NULL DEFAULT 'analista',
    `ativo`            TINYINT(1)       NOT NULL DEFAULT 1,
    `ultimo_acesso`    DATETIME             NULL,
    `ip_ultimo_acesso` VARCHAR(45)          NULL COMMENT 'IPv4 ou IPv6',
    `criado_em`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_email` (`email`),
    INDEX `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Usuários do sistema — conformidade LGPD: senhas não reversíveis';
";

// ─── Logs de Auditoria (Lei Carolina Dieckmann) ───────────────────────────────
$tabelas['logs_auditoria'] = "
CREATE TABLE IF NOT EXISTS `logs_auditoria` (
    `id`          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `usuario_id`  INT UNSIGNED         NULL COMMENT 'NULL = ação anônima/sistema',
    `acao`        VARCHAR(80)      NOT NULL COMMENT 'Ex: LOGIN, LOGOUT, EDITAL_ANALISE',
    `descricao`   TEXT                 NULL,
    `ip`          VARCHAR(45)      NOT NULL,
    `user_agent`  VARCHAR(255)         NULL,
    `criado_em`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_usuario_id` (`usuario_id`),
    INDEX `idx_acao`       (`acao`),
    INDEX `idx_criado_em`  (`criado_em`),
    CONSTRAINT `fk_log_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Trilha de auditoria — retenção mínima 1 ano conforme boas práticas de segurança';
";

// ─── Módulos da Empresa (Catálogo Semântico) ─────────────────────────────────
$tabelas['modulos_empresa'] = "
CREATE TABLE IF NOT EXISTS `modulos_empresa` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nome`           VARCHAR(120) NOT NULL,
    `descricao`      TEXT             NULL,
    `palavras_chave` JSON         NOT NULL COMMENT 'Array de strings para matching semântico',
    `categoria`      VARCHAR(80)      NULL COMMENT 'Ex: Financeiro, RH, Saúde, Fiscal',
    `versao`         VARCHAR(30)      NULL,
    `ativo`          TINYINT(1)   NOT NULL DEFAULT 1,
    `criado_em`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ativo`     (`ativo`),
    INDEX `idx_categoria` (`categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catálogo de softwares e módulos próprios da empresa para o algoritmo de match';
";

// ─── Editais Capturados ───────────────────────────────────────────────────────
$tabelas['editais'] = "
CREATE TABLE IF NOT EXISTS `editais` (
    `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `pncp_id`             VARCHAR(120)      NULL UNIQUE COMMENT 'Chave única da API PNCP',
    `orgao`               VARCHAR(255)  NOT NULL,
    `cnpj_orgao`          VARCHAR(18)       NULL,
    `objeto`              TEXT          NOT NULL,
    `modalidade`          VARCHAR(80)       NULL,
    `valor_estimado`      DECIMAL(15,2)     NULL,
    `data_publicacao`     DATE              NULL,
    `data_encerramento`   DATE              NULL,
    `link_edital`         VARCHAR(512)      NULL,
    `texto_tr`            LONGTEXT          NULL COMMENT 'Texto bruto do Termo de Referência',
    `requisitos_ia`       JSON              NULL COMMENT 'JSON retornado pela IA com módulos e funcionalidades extraídas',
    `porcentagem_match`   DECIMAL(5,2)      NULL COMMENT '0.00 a 100.00',
    `itens_match`         JSON              NULL COMMENT 'Detalhamento: quais módulos deram match e quais não',
    `status`              ENUM('novo','analisando','analisado','erro') NOT NULL DEFAULT 'novo',
    `erro_analise`        TEXT              NULL,
    `criado_em`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_pncp_id`          (`pncp_id`),
    INDEX `idx_status`           (`status`),
    INDEX `idx_porcentagem_match` (`porcentagem_match`),
    INDEX `idx_data_publicacao`  (`data_publicacao`),
    INDEX `idx_data_encerramento`(`data_encerramento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Editais capturados da API PNCP com resultado de análise IA e percentual de aderência';
";

// ─── Execução ─────────────────────────────────────────────────────────────────
foreach ($tabelas as $nome => $sql) {
    try {
        $pdo->exec($sql);
        echo "  [OK] Tabela `{$nome}` criada/verificada.\n";
    } catch (PDOException $e) {
        echo "  [ERRO] Tabela `{$nome}`: " . $e->getMessage() . "\n";
    }
}

// ─── Usuário Admin Padrão ────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ?");
$stmt->execute(['admin@licita.ia']);
if ($stmt->fetchColumn() == 0) {
    $hash = password_hash('Admin@2025!', PASSWORD_BCRYPT, ['cost' => 12]);
    $pdo->prepare(
        "INSERT INTO usuarios (nome, email, senha, perfil) VALUES (?, ?, ?, 'admin')"
    )->execute(['Administrador', 'admin@licita.ia', $hash]);
    echo "\n  [OK] Usuário admin criado:\n";
    echo "       E-mail: admin@licita.ia\n";
    echo "       Senha:  Admin@2025!  ← ALTERE IMEDIATAMENTE!\n";
} else {
    echo "\n  [INFO] Usuário admin já existe.\n";
}

// ─── Módulos de Demonstração ─────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT COUNT(*) FROM modulos_empresa");
$stmt->execute();
if ($stmt->fetchColumn() == 0) {
    $modulosDemo = [
        ['nome' => 'Portal do Servidor', 'descricao' => 'Portal de autoatendimento para servidores públicos',
         'palavras_chave' => json_encode(['portal do servidor', 'autoatendimento', 'holerite', 'contracheque', 'férias', 'benefícios', 'rh']),
         'categoria' => 'Recursos Humanos'],
        ['nome' => 'Sistema de Folha de Pagamento', 'descricao' => 'Processamento de folha, encargos e SEFIP',
         'palavras_chave' => json_encode(['folha de pagamento', 'folha', 'sefip', 'gfip', 'esocial', 'encargos', 'rfb', 'inss', 'fgts', 'irrf']),
         'categoria' => 'Recursos Humanos'],
        ['nome' => 'Sistema de Gestão Contábil', 'descricao' => 'Escrituração contábil conforme PCASP e NBCASP',
         'palavras_chave' => json_encode(['contabilidade', 'contábil', 'pcasp', 'siconfi', 'balancete', 'plano de contas', 'lançamento contábil', 'demonstrações contábeis']),
         'categoria' => 'Financeiro'],
        ['nome' => 'Módulo de Almoxarifado', 'descricao' => 'Gestão de estoque, movimentação e inventário',
         'palavras_chave' => json_encode(['almoxarifado', 'estoque', 'inventário', 'requisição de material', 'movimentação de estoque', 'entrada de material', 'saída de material']),
         'categoria' => 'Patrimônio'],
        ['nome' => 'Sistema de Patrimônio', 'descricao' => 'Cadastro, depreciação e inventário de bens patrimoniais',
         'palavras_chave' => json_encode(['patrimônio', 'bens patrimoniais', 'depreciação', 'tombamento', 'inventário patrimonial', 'bem permanente']),
         'categoria' => 'Patrimônio'],
        ['nome' => 'Portal da Transparência', 'descricao' => 'Publicação de dados conforme Lei de Acesso à Informação',
         'palavras_chave' => json_encode(['transparência', 'lai', 'portal da transparência', 'acesso à informação', 'dados abertos', 'despesas públicas', 'receitas públicas']),
         'categoria' => 'Transparência'],
        ['nome' => 'Sistema de Compras e Licitações', 'descricao' => 'Gestão do processo licitatório e contratos',
         'palavras_chave' => json_encode(['licitação', 'pregão', 'compras', 'contratos', 'ata de registro de preços', 'pncp', 'fornecedor', 'edital', 'homologação']),
         'categoria' => 'Licitações'],
        ['nome' => 'Sistema de Ouvidoria', 'descricao' => 'Gestão de manifestações, reclamações e SIC',
         'palavras_chave' => json_encode(['ouvidoria', 'sic', 'manifestação', 'reclamação', 'denúncia', 'sic', 'cgr', 'e-sic', 'pedido de informação']),
         'categoria' => 'Atendimento'],
    ];

    $insert = $pdo->prepare(
        "INSERT INTO modulos_empresa (nome, descricao, palavras_chave, categoria) VALUES (?, ?, ?, ?)"
    );
    foreach ($modulosDemo as $m) {
        $insert->execute([$m['nome'], $m['descricao'], $m['palavras_chave'], $m['categoria']]);
    }
    echo "  [OK] " . count($modulosDemo) . " módulos de demonstração inseridos.\n";
}

echo "\n=== Instalação concluída. Remova ou proteja este arquivo! ===\n";
