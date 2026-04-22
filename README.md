# Controle de Processos

Aplicacao interna em PHP/MySQL para substituir o preenchimento manual de uma planilha compartilhada por uma interface profissional de cadastro, acompanhamento, auditoria, dashboards e sincronizacao com Excel Online/SharePoint via Microsoft Graph.

## O que a aplicacao entrega

- Login por sessao com perfis `servidor`, `coordenador` e `admin`.
- Dashboard pessoal do usuario logado.
- Dashboard gerencial com indicadores, ranking e graficos.
- Lista de processos com busca global, filtros, paginacao e acoes rapidas.
- Formulario organizado nas mesmas colunas da planilha.
- Tela de detalhes do processo com fluxo, prazos, status e historico.
- Auditoria campo a campo: quem alterou, quando, antes, depois e origem.
- Estado de sincronizacao por processo.
- Logs de sincronizacao com payload e resposta.
- Configuracao da integracao Microsoft Graph pela interface.
- Exportacao CSV no formato da planilha.

## Telas

- `login.php`: acesso ao sistema.
- `dashboard.php`: painel pessoal.
- `management.php`: painel gerencial da coordenacao.
- `processes.php`: lista e filtros de processos.
- `process_form.php`: cadastro/edicao.
- `process_detail.php`: detalhes do processo.
- `audit.php`: trilha de auditoria.
- `sync.php`: status e reenvio de sincronizacao.
- `settings.php`: configuracao SharePoint/Graph.
- `users.php`: administracao de usuarios.

## Estrutura

```text
app/
  Integrations/
    ExcelGraphClient.php
    GraphAuthClient.php
  Repositories/
    AuditLogRepository.php
    IntegrationSettingsRepository.php
    ProcessRepository.php
    SyncRepository.php
    UserRepository.php
  Services/
    AuthService.php
    DashboardService.php
    ProcessService.php
    SyncService.php
database/
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

Se voce ja tinha importado uma versao anterior do banco e recebeu erro dizendo que `process_sync_state`, `audit_logs`, `sync_logs` ou `integration_settings` nao existem, nao reimporte o `schema.sql` se quiser preservar dados.

Nesse caso, importe apenas:

`database/migrations/001_add_audit_sync_graph_tables.sql`

Esse arquivo cria as novas tabelas sem apagar processos e usuarios existentes.

## Acesso inicial

- Email: `admin@local`
- Senha: `admin123`

No primeiro login, o sistema aceita a senha inicial legada e regrava o hash usando `password_hash`.

## Configuracao Microsoft Graph / Excel Online

A sincronizacao usa Microsoft Graph com fluxo OAuth delegado, porque as APIs de linhas de tabela do Excel Online trabalham com permissao delegada para edicao da pasta/arquivo.

No Microsoft Entra ID:

1. Registre um aplicativo.
2. Configure uma Redirect URI web:
   `http://localhost:8080/controle-de-processos/public/graph_callback.php`
3. Adicione permissoes delegadas:
   - `Files.ReadWrite`
   - `offline_access`
4. Gere um `client secret`.
5. Na aplicacao, entre como `admin` em `Integracao`.
6. Preencha:
   - `tenant id`
   - `client id`
   - `client secret`
   - `redirect uri`
   - `drive id`
   - `item id / workbook id`
   - `table name`
7. Salve e clique em `Autorizar conta Microsoft`.

Depois da autorizacao, o sistema armazena um `refresh_token` em `integration_settings` e usa esse token para enviar alteracoes automaticamente para a planilha.

## Como descobrir Drive ID e Item ID

O caminho mais direto e usar o Graph Explorer ou uma chamada Graph autenticada para localizar o arquivo no SharePoint/OneDrive. A planilha precisa estar formatada como tabela no Excel, por exemplo `Tabela1`.

Endpoints uteis:

- Listar drives de um site:
  `GET /sites/{site-id}/drives`
- Localizar item por caminho:
  `GET /drives/{drive-id}/root:/caminho/arquivo.xlsx`
- Listar tabelas do workbook:
  `GET /drives/{drive-id}/items/{item-id}/workbook/tables`

## Sincronizacao

Quando um processo e criado ou atualizado:

1. O sistema salva no MySQL.
2. Registra auditoria campo a campo.
3. Marca o processo como `pending`.
4. Tenta sincronizar com Excel Online.
5. Se der certo, marca `success` e grava log.
6. Se falhar, marca `failed`, grava erro e permite reenvio manual.

Use:

- `Sincronizar agora`: em detalhes do processo.
- `Reenviar falhas`: em `Sincronizacao`.
- `Testar conexao`: em `Sincronizacao`.
- `Ler planilha agora`: importa linhas atuais do Excel Online, usando Numero do Processo como chave para atualizar ou criar registros.

## Colunas preservadas

- Numero do Processo
- DATA DA ATUALIZACAO
- Responsavel pela Resposta
- Prazo (em dias)
- Descricao Geral
- Descricao Detalhada
- Comentarios/anotacoes
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
- Configuracao da integracao exige `admin`.
- Senhas novas usam `password_hash`.
- Acoes destrutivas pedem confirmacao visual.
- Alteracoes ficam registradas em `audit_logs`.
- Em ambiente real, proteja o Apache com HTTPS na rede interna.
