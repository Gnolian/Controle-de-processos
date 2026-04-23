# Controle de Processos

Aplicacao interna em PHP/MySQL para substituir o preenchimento manual de uma planilha compartilhada por uma interface profissional de cadastro, acompanhamento, auditoria, importacao CSV e dashboards.

## O que a aplicacao entrega

- Login por sessao com perfis `servidor`, `coordenador` e `admin`.
- Dashboard pessoal do usuario logado.
- Dashboard gerencial com indicadores e graficos.
- Area restrita de auditorias CGU/TCU com paineis interativos e detalhe da auditoria.
- Lista de processos com busca global, filtros, paginacao e acoes rapidas.
- Formulario organizado nas mesmas colunas da planilha.
- Tela de detalhes do processo com fluxo, prazos, status e historico.
- Auditoria campo a campo: quem alterou, quando, antes, depois e origem.
- Exportacao CSV no formato da planilha.
- Importacao CSV da planilha de processos.

## Telas

- `login.php`: acesso ao sistema.
- `dashboard.php`: painel pessoal.
- `management.php`: painel gerencial da coordenacao.
- `processes.php`: lista e filtros de processos.
- `process_form.php`: cadastro e edicao.
- `process_detail.php`: detalhes do processo.
- `import.php`: importacao CSV da planilha de processos.
- `audit.php`: trilha de auditoria.
- `audits.php`: painel de auditorias.
- `audit_detail.php`: identificacao e itens da auditoria.
- `audit_import.php`: importacao da base tratada de auditorias.
- `users.php`: administracao de usuarios.

## Estrutura

```text
app/
  Repositories/
    AuditRepository.php
    AuditLogRepository.php
    ProcessRepository.php
    UserRepository.php
  Services/
    AuditCsvImportService.php
    AuthService.php
    CsvImportService.php
    DashboardService.php
    ProcessService.php
database/
  migrations/
  schema.sql
public/
  assets/
  *.php
views/
  components.php
  header.php
  nav.php
  footer.php
```

## Instalacao no XAMPP

1. Copie a pasta do projeto para `C:\xampp\htdocs\controle-de-processos`.
2. Inicie `Apache` e `MySQL` no painel do XAMPP.
3. Abra `http://localhost/phpmyadmin`.
4. Importe `database/schema.sql`.
5. Se necessario, ajuste banco, usuario e senha em `app/config.php`.
6. Acesse `http://localhost:8080/controle-de-processos/public/`.

> O `schema.sql` recria as tabelas. Se ja houver dados reais, faca backup antes de importar.

## Atualizando uma instalacao antiga

Se voce ja tinha importado uma versao anterior do banco, nao reimporte o `schema.sql` se quiser preservar dados.

Nesse caso, importe as migrations em ordem:

- `database/migrations/001_add_audit_tables.sql`
- `database/migrations/002_add_deadline_type.sql`
- `database/migrations/003_remove_legacy_integration_tables.sql`
- `database/migrations/004_add_audits_module.sql`

Esses arquivos atualizam auditoria, adicionam o tipo de prazo `Tempo Habil`, removem as antigas tabelas de sincronizacao e criam o modulo de auditorias.

## Acesso inicial

- Email: `admin@local`
- Senha: `admin123`

No primeiro login, o sistema aceita a senha inicial legada e regrava o hash usando `password_hash`.

## Importacao CSV de processos

- `Importar CSV`: importa a planilha exportada do SharePoint/Excel para alimentar o banco no servidor.
- O numero do processo e usado como chave para atualizar registros existentes e inserir novos.
- Processos sem prazo interno ou externo sao marcados como `Tempo Habil`.

## Modulo de auditorias

- O acesso e permitido para `admin`, `coordenador` e usuarios com a chave `audit_access`.
- A liberacao e feita na tela `Usuarios`.
- Antes de usar a area, importe a migration `database/migrations/004_add_audits_module.sql`.
- O importador de auditorias usa diretamente os arquivos tratados:
  - `auditorias_tratadas.csv`
  - `auditorias_itens_tratados.csv`
- O CSV bruto exportado da planilha nao deve mais ser usado na tela de importacao.
- Os paineis de auditorias sao clicaveis: ao clicar em uma categoria, a lista e filtrada; ao clicar em uma auditoria, a identificacao completa e aberta.

## Como carregar auditorias tratadas

1. Gere ou copie os arquivos tratados `auditorias_tratadas.csv` e `auditorias_itens_tratados.csv`.
2. Abra `Usuarios` e habilite `audit_access` para quem vai usar o modulo.
3. Acesse `http://localhost:8080/controle-de-processos/public/audit_import.php`.
4. Envie os dois arquivos tratados.
5. Abra `Auditorias` para consultar os paineis e clicar nas auditorias.

## Colunas preservadas

- Numero do Processo
- DATA DA ATUALIZACAO
- Responsavel pela Resposta
- Prazo (em dias)
- Tipo de prazo
- Descricao Geral
- Descricao Detalhada
- Comentarios e anotacoes
- Orgao Solicitante
- Data de assinatura (Oficio GAB)
- Prazo Interno (OFICIO GAB/SNBA)
- Prazo Interno AJUSTADO
- Prazo Externo/MDS
- Responsavel pela Revisao
- Resposta
- Revisao Andrea
- Assinado
- Enviado Gab
- Data envio GAB
- STATUS
- Bloco interno

As listas controladas continuam em `app/config.php`.

## Observacoes de seguranca

- As rotas internas exigem login.
- Telas gerenciais exigem perfil `coordenador` ou `admin`.
- Senhas novas usam `password_hash`.
- Acoes destrutivas pedem confirmacao visual.
- Alteracoes ficam registradas em `audit_logs`.
- Em ambiente real, proteja o Apache com HTTPS na rede interna.
