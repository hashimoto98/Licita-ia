-- ============================================================
--  LicitAI — Script de criação do banco de dados
--  Execute no MySQL Workbench ou via linha de comando:
--    mysql -u root -p < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `licita_ia`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `licita_ia`;

-- ─── Usuários ────────────────────────────────────────────────
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
  COMMENT='Usuários do sistema — senhas em bcrypt (LGPD)';

-- ─── Logs de Auditoria (Lei Carolina Dieckmann) ─────────────
CREATE TABLE IF NOT EXISTS `logs_auditoria` (
    `id`          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `usuario_id`  INT UNSIGNED         NULL COMMENT 'NULL = ação anônima/sistema',
    `acao`        VARCHAR(80)      NOT NULL,
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
  COMMENT='Trilha de auditoria — retenção mínima 1 ano';

-- ─── Módulos da Empresa ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `modulos_empresa` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nome`           VARCHAR(120) NOT NULL,
    `descricao`      TEXT             NULL,
    `palavras_chave` JSON         NOT NULL COMMENT 'Array de strings para matching semântico',
    `categoria`      VARCHAR(80)      NULL,
    `versao`         VARCHAR(30)      NULL,
    `ativo`          TINYINT(1)   NOT NULL DEFAULT 1,
    `criado_em`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ativo`     (`ativo`),
    INDEX `idx_categoria` (`categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catálogo de softwares da empresa para o algoritmo de match';

-- ─── Editais ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `editais` (
    `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `pncp_id`             VARCHAR(120)      NULL UNIQUE,
    `orgao`               VARCHAR(255)  NOT NULL,
    `cnpj_orgao`          VARCHAR(18)       NULL,
    `objeto`              TEXT          NOT NULL,
    `modalidade`          VARCHAR(80)       NULL,
    `valor_estimado`      DECIMAL(15,2)     NULL,
    `data_publicacao`     DATE              NULL,
    `data_encerramento`   DATE              NULL,
    `link_edital`         VARCHAR(512)      NULL,
    `texto_tr`            LONGTEXT          NULL,
    `requisitos_ia`       JSON              NULL,
    `porcentagem_match`   DECIMAL(5,2)      NULL,
    `itens_match`         JSON              NULL,
    `status`              ENUM('novo','analisando','analisado','erro') NOT NULL DEFAULT 'novo',
    `erro_analise`        TEXT              NULL,
    `criado_em`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_pncp_id`           (`pncp_id`),
    INDEX `idx_status`            (`status`),
    INDEX `idx_porcentagem_match` (`porcentagem_match`),
    INDEX `idx_data_publicacao`   (`data_publicacao`),
    INDEX `idx_data_encerramento` (`data_encerramento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Editais do PNCP com análise IA e percentual de aderência';

-- ─── Usuário Admin Padrão ─────────────────────────────────────
-- Senha: Admin@2025!  (bcrypt cost 12) — ALTERE APÓS O PRIMEIRO LOGIN
INSERT IGNORE INTO `usuarios` (`nome`, `email`, `senha`, `perfil`)
VALUES (
    'Administrador',
    'admin@licita.ia',
    '$2y$12$C.BmXL2/OoHiHHLU9cKY.efJDpkptPHoQRuXh78oesYJthvfPLG6m',
    'admin'
);

-- ─── Módulos de Demonstração ─────────────────────────────────
INSERT IGNORE INTO `modulos_empresa` (`nome`, `descricao`, `palavras_chave`, `categoria`) VALUES
(
    'Portal do Servidor',
    'Portal de autoatendimento para servidores públicos',
    '["portal do servidor","autoatendimento","holerite","contracheque","férias","benefícios","rh"]',
    'Recursos Humanos'
),
(
    'Sistema de Folha de Pagamento',
    'Processamento de folha, encargos e SEFIP',
    '["folha de pagamento","folha","sefip","gfip","esocial","encargos","rfb","inss","fgts","irrf"]',
    'Recursos Humanos'
),
(
    'Sistema de Gestão Contábil',
    'Escrituração contábil conforme PCASP e NBCASP',
    '["contabilidade","contábil","pcasp","siconfi","balancete","plano de contas","lançamento contábil","demonstrações contábeis"]',
    'Financeiro'
),
(
    'Módulo de Almoxarifado',
    'Gestão de estoque, movimentação e inventário',
    '["almoxarifado","estoque","inventário","requisição de material","movimentação de estoque","entrada de material","saída de material"]',
    'Patrimônio'
),
(
    'Sistema de Patrimônio',
    'Cadastro, depreciação e inventário de bens patrimoniais',
    '["patrimônio","bens patrimoniais","depreciação","tombamento","inventário patrimonial","bem permanente"]',
    'Patrimônio'
),
(
    'Portal da Transparência',
    'Publicação de dados conforme Lei de Acesso à Informação',
    '["transparência","lai","portal da transparência","acesso à informação","dados abertos","despesas públicas","receitas públicas"]',
    'Transparência'
),
(
    'Sistema de Compras e Licitações',
    'Gestão do processo licitatório e contratos',
    '["licitação","pregão","compras","contratos","ata de registro de preços","pncp","fornecedor","edital","homologação"]',
    'Licitações'
),
(
    'Sistema de Ouvidoria',
    'Gestão de manifestações, reclamações e SIC',
    '["ouvidoria","sic","manifestação","reclamação","denúncia","e-sic","pedido de informação"]',
    'Atendimento'
);
