# Controle de Processos

Aplicacao interna em PHP/MySQL para substituir o preenchimento manual de planilhas por uma interface profissional de cadastro, acompanhamento, auditoria, importacao CSV e dashboards.

## O que a aplicacao entrega

- Login por sessao com perfis `servidor`, `coordenador` e `admin`.
- Dashboard pessoal do usuario logado.
- Dashboard gerencial para processos.
- Area restrita de auditorias CGU/TCU com:
  - importacao de CSV tratado;
  - cadastro manual de auditoria;
  - edicao manual de auditoria;
  - detalhe da auditoria com itens;
  - paineis interativos;
  - linha do tempo de prazos de 2026.
- Lista de processos com busca global, filtros, paginacao e acoes rapidas.
- Auditoria campo a campo nos processos.

## Telas

- `login.php`: acesso ao sistema.
- `dashboard.php`: painel pessoal.
- `management.php`: painel gerencial de processos.
- `processes.php`: lista e filtros de processos.
- `process_form.php`: cadastro e edicao de processo.
- `process_detail.php`: detalhes do processo.
- `import.php`: importacao CSV da planilha de processos.
- `audit.php`: trilha de auditoria dos processos.
- `audits.php`: painel de auditorias.
- `audit_form.php`: cadastro e edicao manual de auditoria.
- `audit_detail.php`: identificacao completa e itens da auditoria.
- `audit_import.php`: importacao da base tratada de auditorias.
- `users.php`: administracao de usuarios.

## Estrutura

```text
app/
  Repositories/
  Services/
database/
  migrations/
  schema.sql
public/
  assets/
  *.php
views/
  *.php
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

Importe as migrations em ordem:

- `database/migrations/001_add_audit_tables.sql`
- `database/migrations/002_add_deadline_type.sql`
- `database/migrations/003_remove_legacy_integration_tables.sql`
- `database/migrations/004_add_audits_module.sql`
- `database/migrations/005_expand_audits_for_timeline.sql`

As duas ultimas sao obrigatorias para o modulo de auditorias atual.

## Acesso inicial

- Email: `admin@local`
- Senha: `admin123`

No primeiro login, o sistema aceita a senha inicial legada e regrava o hash usando `password_hash`.

## Modulo de auditorias

- O acesso e permitido para `admin`, `coordenador` e usuarios com a chave `audit_access`.
- A liberacao e feita na tela `Usuarios`.
- A tela principal `audits.php` traz:
  - cards de quantidade;
  - graficos interativos;
  - situacao de implementacao de itens;
  - cards de ponto de controle;
  - linha do tempo de 2026 baseada na coluna `deadline`.

### Importacao tratada

O importador usa diretamente os arquivos tratados:

- `auditorias_tratadas.csv`
- `auditorias_itens_tratados.csv`

O CSV bruto exportado da planilha nao deve mais ser usado na tela de importacao.

### Como carregar auditorias tratadas

1. Gere ou copie os arquivos tratados `auditorias_tratadas.csv` e `auditorias_itens_tratados.csv`.
2. Abra `Usuarios` e habilite `audit_access` para quem vai usar o modulo.
3. Acesse `http://localhost:8080/controle-de-processos/public/audit_import.php`.
4. Envie os dois arquivos tratados.
5. Abra `Auditorias` para consultar os paineis, editar registros e usar a linha do tempo.

### Cadastro manual de auditoria

1. Acesse `Auditorias`.
2. Clique em `Nova auditoria`.
3. Preencha identificacao, ponto de controle, etapa 2, monitoramentos e itens.
4. Salve para abrir a tela de detalhe.

### Campos novos do modulo

O banco agora suporta, alem dos campos anteriores, os principais dados da planilha tratada:

- `last_date_response`
- `deadline_label`
- `deadline_date`
- `deadline_is_current`
- `flag_estimated`
- `last_response_sent_date_diligence`
- `stage2_start_date`
- `flag_stage2_diligence`
- `stage2_date_last_response_diligence`
- `stage2_preliminary_document`
- `stage2_final_report`
- `stage3_accord_report`
- `stage3_accord_report_date`
- `monitoring1_*`
- `monitoring2_*`
- `monitoring3_*`
- `monitoring4_*`
- `control_summary`
- `status_geral` nos itens

## Importacao CSV de processos

- `Importar CSV`: importa a planilha exportada do SharePoint/Excel para alimentar o banco no servidor.
- O numero do processo e usado como chave para atualizar registros existentes e inserir novos.
- Processos sem prazo interno ou externo sao marcados como `Tempo Habil`.

## Observacoes de seguranca

- As rotas internas exigem login.
- Telas gerenciais exigem perfil `coordenador` ou `admin`.
- Senhas novas usam `password_hash`.
- Acoes destrutivas pedem confirmacao visual.
- Alteracoes dos processos ficam registradas em `audit_logs`.
- Em ambiente real, proteja o Apache com HTTPS na rede interna.
