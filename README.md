# Painéis DGBA

Sistema web desenvolvido em PHP para centralizar painéis e controles internos da DGBA que antes eram mantidos principalmente em planilhas.

Este repositorio foi preparado para portifolio. Ele mostra a estrutura, o codigo e a proposta da solucao, mas nao deve conter dados reais, planilhas internas, credenciais ou informacoes sensiveis.

## Como surgiu a demanda

A rotina de acompanhamento de auditorias dependia de planilhas e controles manuais. Isso tornava dificil saber rapidamente:

- quais auditorias estavam em andamento;
- qual era a fase e o responsavel atual;
- quais prazos estavam proximos;
- quais recomendacoes, determinacoes e ciencias estavam sendo acompanhadas;
- como consultar os pontos de controle de forma centralizada.

A proposta foi transformar esse fluxo em uma aplicacao web simples de acessar pela rede interna, com login, telas de consulta, dashboards e historico de alteracoes.

## Como a solucao foi construida

O projeto foi desenvolvido como uma aplicacao PHP tradicional, usando MySQL para armazenar as informacoes e Apache/XAMPP para execucao local ou em rede interna.

Em vez de manter tudo em uma unica planilha, o sistema organiza os dados em tabelas e telas:

- painel gerencial de auditorias;
- cadastro e edicao de auditorias;
- busca e filtros interativos;
- importacao de bases tratadas;
- acompanhamento de prazos em linha do tempo;
- controle de usuarios e permissoes;
- acompanhamento de recomendacoes, determinacoes e ciencias.
- banco de estudos com pesquisa em titulos, autores, temas, palavras-chave, resumos e evidencias.
- monitoramento das respostas do INSS aos oficios enviados pela DGBA.

## Tecnologias utilizadas

- **PHP**: linguagem principal do sistema. E responsavel pelas telas, regras de negocio, login, importacao e comunicacao com o banco.
- **MySQL**: banco de dados relacional usado para guardar usuarios, auditorias e seus itens.
- **Apache**: servidor web usado para publicar a aplicacao.
- **XAMPP**: pacote que facilita rodar Apache, PHP e MySQL no Windows durante desenvolvimento ou uso local.
- **HTML, CSS e JavaScript**: tecnologias usadas para montar a interface visual, estilos, botoes, formularios e interacoes da pagina.
- **Python**: usado apenas em scripts auxiliares para tratar bases CSV de auditoria antes da importacao.
- **Git e GitHub**: controle de versao e publicacao do projeto para portifolio.

## Principais funcionalidades

- Login por sessao.
- Perfis de acesso: `servidor`, `coordenador` e `admin`.
- Administracao de usuarios.
- Painel de auditorias com:
  - importacao de bases tratadas;
  - cadastro manual;
  - edicao;
  - tela de detalhes;
  - itens de auditoria;
  - indicadores;
  - linha do tempo de prazos.
- Banco de estudos com:
  - pesquisa textual em todos os campos da matriz de evidencias;
  - exibicao de autores, ano, tipo, local, resumo e palavras-chave;
  - consulta das evidencias registradas;
  - abertura do link original da publicacao;
  - importacao de arquivos XLSX ou CSV pelos usuarios com acesso ao modulo;
  - anexacao e abertura protegida de PDFs.
- Monitoramento INSS com:
  - dashboard de respostas, prazos, cobrancas e prioridades;
  - filtros suspensos para os campos de acompanhamento;
  - cadastro e edicao das demandas;
  - calculo automatico dos dias decorridos;
  - importacao da planilha XLSX ou CSV sem apagar atualizacoes ja realizadas na aplicacao.

## Estrutura do projeto

```text
app/
  Repositories/       Acesso ao banco de dados
  Services/           Regras de negocio
  bootstrap.php       Inicializacao da aplicacao
  config.php          Configuracao local do sistema

database/
  migrations/         Alteracoes incrementais do banco
  schema.sql          Estrutura inicial do banco

public/
  assets/             CSS e JavaScript
  *.php               Telas acessadas pelo navegador

views/
  *.php               Partes reutilizaveis da interface

apache/
  controle-alias.conf Configuracao opcional de alias no Apache

scripts/
  *.py                Scripts auxiliares para tratamento de CSV
```

## O que precisa para rodar

Para executar em um computador Windows, o caminho mais simples e usar o XAMPP.

Voce precisa instalar:

- XAMPP com Apache, PHP e MySQL;
- Git, caso queira clonar o repositorio;
- um navegador, como Chrome, Edge ou Firefox;
- Python, apenas se for usar os scripts de tratamento de CSV.

## Como inicializar no XAMPP

1. Copie ou clone o projeto para a pasta do XAMPP:

```powershell
C:\xampp\htdocs\controle-de-processos
```

2. Abra o painel do XAMPP.

3. Inicie os servicos:

- Apache;
- MySQL.

4. Abra o phpMyAdmin:

```text
http://localhost/phpmyadmin
```

5. Importe o arquivo:

```text
database/schema.sql
```

6. Confira os dados de conexao em:

```text
app/config.php
```

Por padrao, o projeto usa:

```text
host: 127.0.0.1
banco: controle_processos
usuario: root
senha: vazia
```

7. Acesse no navegador:

```text
http://localhost/controle-de-processos/public/
```

Dependendo da configuracao do Apache, tambem pode ser usado:

```text
http://localhost:8080/controle-de-processos/public/
```

## Acesso inicial

O banco de exemplo cria um usuario administrador inicial:

```text
Email: admin@local
Senha: admin123
```

Depois do primeiro acesso, a recomendacao e trocar a senha imediatamente.

## URL amigavel no Apache

O projeto inclui uma configuracao opcional para acessar a aplicacao por uma URL mais curta, como:

```text
http://localhost/controle
```

Para isso, inclua no arquivo de configuracao do Apache/XAMPP:

```apache
Include "C:/xampp/htdocs/controle-de-processos/apache/controle-alias.conf"
```

Depois reinicie o Apache.

Se o projeto estiver em outro caminho, ajuste os caminhos dentro de `apache/controle-alias.conf`.

## Sobre importacao de dados

O sistema aceita importacao de CSV para alimentar auditorias e seus itens. Para uso publico no GitHub, esses arquivos nao devem ser enviados ao repositorio.

O Monitoramento INSS aceita a planilha `Base para monitoramento respostas ao INSS` em XLSX ou CSV. Os registros sao identificados pelo processo SEI e pelo oficio enviado. Campos vazios de uma nova importacao nao apagam informacoes de acompanhamento preenchidas anteriormente no sistema.

O Banco de estudos aceita cadastro e edicao manual, alem da importacao direta da planilha XLSX da matriz de evidencias ou de uma versao CSV com os mesmos cabecalhos. Qualquer usuario com acesso ao Banco de estudos pode adicionar, editar e importar registros. Estudos existentes sao atualizados pela identificacao do link ou pela combinacao de titulo, autor e ano.

No cadastro manual, o estudo pode ter um link externo, um PDF de ate 30 MB ou ambos. Os PDFs ficam em `storage/studies`, fora da pasta publica, e sao abertos por uma rota autenticada. Essa pasta nao deve ser versionada no Git e precisa ter permissao de escrita para o Apache no servidor.

Em instalacoes antigas, a tabela tambem pode ser criada manualmente com:

```text
database/migrations/008_add_studies_module.sql
database/migrations/009_add_study_pdf.sql
database/migrations/010_add_inss_monitoring.sql
```

A propria pagina cria a tabela automaticamente quando o usuario do banco possui permissao para isso.

Arquivos de planilhas, bases tratadas, exportacoes e bancos locais devem ficar apenas no computador ou servidor onde o sistema roda.

## Cuidados antes de publicar no GitHub

Antes de tornar o repositorio publico, confira:

- nao incluir arquivos `.env`;
- nao incluir planilhas reais em `.csv`, `.xlsx` ou `.xls`;
- nao incluir dumps de banco em `.sql`, `.dump`, `.bak` ou `.backup`;
- nao incluir bancos locais em `.db`, `.sqlite` ou `.sqlite3`;
- nao incluir logs com nomes, numeros de processo, CPF, e-mail ou outros dados pessoais;
- revisar `app/config.php` e trocar qualquer senha real por valor de exemplo;
- manter dados reais fora de `database/local_imports/`;
- usar somente dados ficticios em prints, exemplos ou demonstracoes.

## O que este projeto demonstra

Este projeto demonstra a criacao de uma solucao interna a partir de uma necessidade administrativa real:

- substituicao de planilhas por sistema web;
- modelagem de banco relacional;
- controle de acesso;
- organizacao de telas por perfil;
- importacao de dados;
- acompanhamento de prazos;
- evolucao incremental por migrations;
- preocupacao com seguranca e publicacao responsavel.

## Modulo legado de processos

As telas de painel pessoal e controle de processos foram desativadas. As tabelas e os registros existentes permanecem preservados no banco de dados; nenhuma informacao foi excluida durante essa mudanca.

## Observacao

Este projeto foi adaptado para fins de portifolio. Qualquer dado institucional, pessoal ou operacional deve ser removido antes da publicacao publica.
