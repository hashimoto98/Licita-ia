# LicitAI — Sistema Inteligente de Análise de Licitações de TI

> Sistema autônomo que varre o Portal Nacional de Contratações Públicas (PNCP), analisa editais de TI com Inteligência Artificial e entrega um funil de vendas ordenado pela chance real de vitória.

---

## Sumário

1. [Visão Geral](#visão-geral)
2. [Arquitetura](#arquitetura)
3. [Requisitos](#requisitos)
4. [Instalação](#instalação)
5. [Configuração](#configuração)
6. [Uso do Sistema](#uso-do-sistema)
7. [Automação (Cron)](#automação-cron)
8. [Segurança e LGPD](#segurança-e-lgpd)
9. [API PNCP](#api-pncp)
10. [Motor de Match](#motor-de-match)
11. [PWA](#pwa)
12. [Justificativas Arquiteturais](#justificativas-arquiteturais)

---

## Visão Geral

O LicitAI resolve um problema crítico de empresas de tecnologia: **identificar, dentro de centenas de editais publicados diariamente, exatamente quais têm maior probabilidade de vitória** com base no portfólio de softwares da empresa.

## Link para teste
licita-ia.free.nf

### Fluxo em 4 etapas

```
PNCP API → [Filtra TI] → Banco → [IA extrai requisitos] → [Motor de Match] → Dashboard rankeado
```

1. **Captura**: Robô cURL consome a API pública do PNCP, filtra por palavras-chave de TI.
2. **Leitura**: LLM (via OpenRouter) lê o Termo de Referência e extrai módulos/funcionalidades.
3. **Match**: Algoritmo cruza requisitos do edital com `modulos_empresa` (catálogo da empresa).
4. **Ranking**: Dashboard exibe editais do maior ao menor % de compatibilidade.

---

## Funcionalidades

### Essenciais
| Funcionalidade | Onde encontrar |
|---|---|
| Login e logout | `views/auth/login.php` · `controllers/AuthController.php` |
| CRUD de Módulos | Menu "Catálogo de Módulos" · `controllers/ModulosController.php` |
| CRUD de Editais | Menu "Cadastrar Edital" · `controllers/EditaisController.php` |
| Filtros e pesquisa | Dashboard — barra superior com busca, modalidade, status e match mínimo |
| Relatórios e gráficos | Menu "Relatórios" — gráfico de barras e linhas + exportação CSV |

### Avançadas
| Funcionalidade | Detalhes |
|---|---|
| **Upload de Termo de Referência** | PDF, DOC, DOCX, TXT · até 10 MB · drag-and-drop na tela de detalhes |
| **Integração API PNCP** | Captura automática de editais via REST API pública do governo |
| **Análise IA (OpenRouter/Claude)** | LLM extrai requisitos técnicos do TR e classifica por módulo |
| **Motor de Match Semântico** | Cruza requisitos do edital com catálogo de módulos da empresa |
| **Notificações em tempo real** | Badge no header com editais pendentes e oportunidades de alta compatibilidade |
| **PWA (Progressive Web App)** | Instalável como app no celular/desktop, funciona parcialmente offline |
| **Automação via Cron** | Captura diária automática configurável em qualquer SO |
| **Painel Admin** | Gestão de usuários, logs de auditoria e health check das APIs |
| **Exportação CSV** | Editais e módulos exportáveis para análise em Excel |

---

## Arquitetura

### Padrão MVC (PHP Vanilla, sem framework)

```
licita-ia/
├── config/
│   ├── config.php          # Constantes globais e configurações
│   └── Database.php        # Singleton PDO
├── controllers/
│   ├── AuthController.php          # Login/logout, CSRF, rate limiting
│   ├── ApiPncpController.php       # Integração API PNCP
│   ├── OpenRouterController.php    # Integração IA (OpenRouter)
│   ├── MatchController.php         # Algoritmo de compatibilidade
│   ├── EditaisController.php       # CRUD e análise de editais
│   └── ModulosController.php       # CRUD do catálogo de módulos
├── models/
│   ├── Usuario.php         # Acesso à tabela usuarios
│   ├── Edital.php          # Acesso à tabela editais
│   ├── Modulo.php          # Acesso à tabela modulos_empresa
│   └── LogAuditoria.php    # Trilha de auditoria
├── views/
│   ├── layout/             # header.php e footer.php compartilhados
│   ├── auth/login.php      # Tela de login
│   ├── dashboard.php       # Funil de oportunidades + gráficos
│   ├── detalhes.php        # Checklist ✅/❌ por edital
│   ├── modulos/            # CRUD de módulos
│   └── errors/403.php      # Página de acesso negado
├── public/
│   ├── manifest.json       # PWA manifest
│   ├── sw.js               # Service Worker
│   └── icons/              # Ícones PWA (192x192 e 512x512)
├── logs/                   # Logs de erro PHP
├── index.php               # Front Controller (router único)
├── install.php             # Setup do banco de dados
├── cron.php                # Automação diária
└── .htaccess               # Segurança Apache
```

### Banco de Dados (SQLite por padrão · MySQL opcional)

O sistema usa **SQLite** por padrão — arquivo em `storage/licita_ia.sqlite`, sem servidor de banco de dados necessário. Para produção em servidor compartilhado, é possível configurar MySQL via variável de ambiente `DB_DRIVER=mysql`.

| Tabela | Propósito |
|---|---|
| `usuarios` | Autenticação com bcrypt + controle de acesso por perfil |
| `logs_auditoria` | Trilha completa de ações (Lei Carolina Dieckmann) |
| `modulos_empresa` | Catálogo semântico com palavras-chave para match |
| `editais` | Editais capturados + resultado IA + % de match |

---

## Requisitos

- **PHP** 8.1+ com extensões: PDO, PDO_SQLite, cURL, JSON, mbstring
- **Servidor Web**: Apache 2.4+ com `mod_rewrite` e `mod_headers` — ou XAMPP/Laragon no Windows
- **Conexão com internet** para APIs externas (PNCP e OpenRouter)
- *(Opcional)* `poppler-utils` (comando `pdftotext`) para extração de texto de PDFs nativos
- *(Opcional)* MySQL 5.7+ / MariaDB 10.4+ se quiser banco relacional em produção

---

## Instalação

### Instalação Rápida (SQLite — recomendada para avaliação)

> Sem necessidade de MySQL. Basta PHP + Apache/XAMPP.

```bash
# 1. Copie a pasta para o servidor web
cp -r licita-ia/ /var/www/html/licita-ia/
# Windows XAMPP: coloque em C:\xampp\htdocs\licita-ia\

# 2. Configure a chave da IA no arquivo .env (copie de .env.example)
OPENROUTER_API_KEY=sua-chave-openrouter

# 3. Execute o instalador SQLite
php install.php
```

Acesse `http://localhost/licita-ia/` — credenciais padrão:
- **E-mail**: `admin@licita.ia`
- **Senha**: `Admin@2025!` ← **Altere após o primeiro login!**

O instalador cria o arquivo `storage/licita_ia.sqlite` com todas as tabelas e módulos de demonstração.

---

### Instalação com MySQL (produção)

```bash
# 1. Crie o banco de dados
CREATE DATABASE licita_ia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'licita_user'@'localhost' IDENTIFIED BY 'senha_forte_aqui';
GRANT ALL PRIVILEGES ON licita_ia.* TO 'licita_user'@'localhost';

# 2. Configure no .env
DB_DRIVER=mysql
DB_HOST=localhost
DB_NAME=licita_ia
DB_USER=licita_user
DB_PASS=senha_forte_aqui

# 3. Execute o instalador MySQL
php install.php
```

### Configurar ícones PWA (opcional)

Adicione imagens PNG em `public/icons/`:
- `icon-192.png` (192×192 px)
- `icon-512.png` (512×512 px)

---

## Configuração

Crie um arquivo `.env` na raiz (copie de `.env.example`) com as variáveis abaixo.

| Variável | Descrição | Padrão |
|---|---|---|
| `DB_DRIVER` | Driver do banco: `sqlite` ou `mysql` | `sqlite` |
| `DB_SQLITE_PATH` | Caminho do arquivo SQLite | `storage/licita_ia.sqlite` |
| `DB_HOST` | Host do MySQL (se `DB_DRIVER=mysql`) | `localhost` |
| `DB_NAME` | Nome do banco MySQL | `licita_ia` |
| `DB_USER` | Usuário MySQL | `root` |
| `DB_PASS` | Senha MySQL | *(vazio)* |
| `OPENROUTER_API_KEY` | Chave da API OpenRouter (**obrigatória**) | — |
| `APP_URL` | URL base da aplicação | `http://localhost/licita-ia` |
| `APP_SECRET` | Secret para tokens de segurança | *(gerar aleatório)* |
| `CRON_SECRET` | Token de autenticação do cron | *(definir manualmente)* |

### Obtendo chave do OpenRouter

1. Acesse [openrouter.ai](https://openrouter.ai)
2. Crie uma conta e gere uma API Key
3. O sistema usa por padrão o modelo `anthropic/claude-haiku-4-5-20251001` (rápido e econômico)

---

## Uso do Sistema

### Login
Acesse `http://localhost/licita-ia/` e entre com as credenciais do admin.

### Dashboard — Funil de Oportunidades
- Exibe editais **ordenados do maior para o menor** percentual de compatibilidade
- Cards de estatísticas: total, analisados, alta compatibilidade (≥70%), valor total
- Gráfico de barras: editais capturados vs compatíveis por semana
- Gráfico de rosca: distribuição por modalidade
- Filtros: busca por texto, modalidade, status, match mínimo

### Capturar editais do PNCP (Admin)
No dashboard, clique em **"Capturar PNCP Hoje"** — o sistema buscará editais publicados hoje e filtrará os de TI.

### Analisar com IA
Em cada edital, clique no ícone de IA (relâmpago) ou no botão **"Analisar com IA"** na tela de detalhes. O processo:
1. Baixa o Termo de Referência
2. Envia para o LLM extrair requisitos
3. Cruza com o catálogo da empresa
4. Exibe o resultado imediatamente

### Tela de Detalhes
- Gauge circular com o % de compatibilidade
- **✅ Módulos com Cobertura**: o que a empresa já tem (AUTO)
- **❌ Módulos não Cobertos**: lacunas do portfólio (PENDENTE)
- Todos os requisitos extraídos pela IA (módulos, funcionalidades, tecnologias, integrações)

### Catálogo de Módulos
Em **"Catálogo de Módulos"**, adicione/edite os softwares da empresa com suas palavras-chave. Quanto mais específicas as palavras-chave, melhor o match.

**Exemplo de boas palavras-chave:**
```
folha de pagamento, esocial, gfip, sefip, inss, fgts, irrf, holerite, contracheque, rais
```

---

## Automação (Cron)

### Linux/Mac (crontab)

```bash
crontab -e

# Captura diária às 7h + análise dos editais pendentes
0 7 * * * /usr/bin/php /var/www/html/licita-ia/cron.php all >> /var/log/licita-ia.log 2>&1

# Só captura (sem análise IA)
0 7 * * * /usr/bin/php /var/www/html/licita-ia/cron.php fetch
```

### Windows (Agendador de Tarefas)

1. Abrir **Agendador de Tarefas** → Nova Tarefa
2. Gatilho: Diariamente às 07:00
3. Ação: `C:\php\php.exe C:\xampp\htdocs\licita-ia\cron.php all`

### Parâmetros CLI

```bash
php cron.php <ação> [data_inicial] [data_final]

# Exemplos:
php cron.php all                      # Captura + análise (hoje)
php cron.php fetch 2025-06-01 2025-06-19  # Captura período específico
php cron.php analyze                  # Apenas análise IA dos pendentes
php cron.php cleanup                  # Limpeza de logs antigos
```

---

## Segurança e LGPD

### Implementações de Segurança

| Camada | Implementação |
|---|---|
| **Injeção SQL** | PDO com prepared statements em 100% das queries |
| **Senhas** | bcrypt via `password_hash()` com custo 12 |
| **CSRF** | Token duplo (session + form) validado com `hash_equals()` |
| **XSS** | `htmlspecialchars()` em todas as saídas de variáveis nas views |
| **Session Fixation** | `session_regenerate_id(true)` no login |
| **Rate Limiting** | Bloqueio de IP após 5 tentativas de login falhas (15 min) |
| **Headers HTTP** | X-Frame-Options, X-Content-Type-Options, Content-Security-Policy |
| **Acesso a arquivos** | `.htaccess` bloqueia config/, models/, controllers/, views/ |

### Conformidade LGPD / Lei Carolina Dieckmann

- **`logs_auditoria`**: Registra IP, user agent, ação e usuário em toda ação relevante (Art. 3º da Lei nº 12.737/2012)
- **Retenção de logs**: 365 dias (configurável via `LOG_RETENTION_DAYS`)
- **Anonimização**: `Usuario::anonimizar()` implementa o direito de exclusão (Art. 18 LGPD) sem violar integridade referencial
- **Senhas não reversíveis**: bcrypt garante que dados pessoais não sejam recuperáveis mesmo com acesso ao banco

---

## API PNCP

O sistema consome a API REST pública do Portal Nacional de Contratações Públicas (PNCP):

### Endpoints utilizados

| Endpoint | Finalidade |
|---|---|
| `GET https://pncp.gov.br/api/search/` | Busca textual de editais por setor (TI, Tributário, Educação) |
| `GET /api/pncp/v1/orgaos/{cnpj}/compras/{ano}/{seq}/arquivos` | Lista documentos de um edital específico |
| Download via URL do campo `url` | Baixa PDF do Termo de Referência |

### Parâmetros de busca

```
GET https://pncp.gov.br/api/search/?tipos_documento=edital&q="tecnologia da informacao"&pagina=1&tam_pagina=50
```

- **`q`**: Aceita frases entre aspas (`"tecnologia da informacao"`) e operador `OR` (`tributario OR SEFAZ`)
- **Paginação**: 50 itens/página, 1 segundo de intervalo entre páginas (rate limit)
- **Sem autenticação**: API pública, não requer chave

### Adaptabilidade

O `ApiPncpController` usa constante `TERMOS_BUSCA` configurável por setor. Novos setores são adicionados criando uma entrada no array sem alterar o algoritmo central.

---

## Motor de Match

### Algoritmo de Compatibilidade

1. **Entrada**: Array de termos extraídos pela IA (`modulos` + `funcionalidades` + `tecnologias` + `integracoes`)
2. **Normalização**: Converte para minúsculas, remove underscores, normaliza espaços
3. **Para cada módulo da empresa**: verifica se alguma `palavra_chave` faz intersecção com os termos do edital
4. **Critério de match** (em ordem de prioridade):
   - Correspondência exata (cobre siglas curtas: `iss`, `erp`, `ead`)
   - Substring bidirecional com comprimento mínimo de 5 chars em ambos os lados
   - Coeficiente de Dice > 0.75 usando `similar_text()` (exige strings ≥ 8 chars)
5. **Porcentagem**: `(módulos da empresa cobertos / total de módulos ativos) × 100`

### Interpretação

| % Match | Significado | Cor no dashboard |
|---|---|---|
| ≥ 70% | Alta compatibilidade — alto potencial | 🟢 Verde |
| 40–69% | Média compatibilidade — avaliar gaps | 🟡 Amarelo |
| < 40% | Baixa compatibilidade — risco elevado | 🔴 Vermelho |

### Extração de Texto por Prioridade

O sistema tenta obter o texto do Termo de Referência na seguinte ordem:
1. Texto já salvo no banco (cache de análises anteriores)
2. API de arquivos do PNCP → download do PDF prioritizando TR > Projeto Básico > Edital
3. Fallback: análise direta sobre o campo `objeto` do edital (descrição curta)

---

## PWA

O LicitAI pode ser instalado como aplicativo no celular (Android/iOS) e desktop:

1. Acesse o sistema pelo browser mobile
2. Aparecerá o prompt "Adicionar à tela inicial"
3. O app funciona offline para as telas já visitadas

**Estratégia de cache**: Network First com fallback offline — o usuário sempre recebe dados frescos quando online.

---

## Tecnologias Utilizadas e Justificativas

| Tecnologia | Versão | Papel no sistema | Por que foi escolhida |
|---|---|---|---|
| **PHP** | 8.1+ | Back-end / lógica de negócio | Linguagem nativa do curso, roda em qualquer hospedagem compartilhada sem dependências externas |
| **SQLite** | 3 | Banco de dados padrão | Sem instalação de servidor, arquivo único (`storage/*.sqlite`), ideal para avaliação e desenvolvimento local |
| **MySQL** | 5.7+ | Banco de dados opcional (produção) | Compatível via PDO; mesmo código roda com ambos os drivers |
| **Tailwind CSS** | CDN | Estilização e responsividade | Utility-first permite design consistente sem escrever CSS custom; carregamento via CDN sem build step |
| **Chart.js** | 4.4 | Gráficos e visualizações | Biblioteca leve e bem documentada para gráficos interativos sem dependências |
| **OpenRouter / Claude Haiku** | — | Análise de TRs com IA | API unificada para múltiplos LLMs; modelo Haiku equilibra velocidade, custo e qualidade de extração |
| **PNCP API** | REST pública | Fonte de editais governamentais | API oficial do governo federal, gratuita, sem autenticação, 100% dos editais públicos do Brasil |
| **cURL (PHP)** | — | Consumo de APIs externas | Extensão nativa PHP; sem necessidade de Guzzle ou Composer |
| **bcrypt** | — | Hash de senhas | Algoritmo de hashing adaptativo com custo configurável; resistente a ataques de força bruta |
| **Service Worker** | — | PWA e cache offline | Permite instalação como app e uso parcial sem internet |

## Justificativas Arquiteturais

### Por que PHP Vanilla (sem framework)?

Simplicidade de deploy: roda em qualquer hospedagem compartilhada com PHP 8.1+ sem Composer, sem CLI, sem build step. Reduz a superfície de ataque e dependências externas.

### Por que PDO Singleton?

Uma única instância de conexão por request evita overhead de múltiplas conexões ao banco (SQLite ou MySQL), mantendo o código testável e a conexão sob controle centralizado. O mesmo padrão funciona com ambos os drivers sem alterar os models.

### Por que OpenRouter em vez de API direta da Anthropic/OpenAI?

OpenRouter fornece acesso unificado a múltiplos modelos com um único contrato. Permite trocar de modelo (Claude Haiku → GPT-4o → Llama) sem alterar código, apenas mudando `OPENROUTER_MODEL` na config.

### Por que Match Semântico e não keyword exact?

Editais usam variações: "folha salarial", "processamento de folha", "sistema de folha". O matching bidirecional (`str_contains` + coeficiente de Dice) captura essas variações sem exigir dicionário completo.

---

*LicitAI v1.0.0 — Desenvolvido com PHP 8.1, SQLite/MySQL, Tailwind CSS, Chart.js e OpenRouter*
