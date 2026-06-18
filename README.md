# Controle de Processos

Sistema web desenvolvido em PHP para organizar, acompanhar e auditar processos internos que antes eram controlados principalmente por planilhas.

Este repositorio foi preparado para portifolio. Ele mostra a estrutura, o codigo e a proposta da solucao, mas nao deve conter dados reais, planilhas internas, credenciais ou informacoes sensiveis.

## Como surgiu a demanda

A rotina de acompanhamento de processos dependia de planilhas e controles manuais. Isso tornava dificil saber rapidamente:

- quais processos estavam em andamento;
- quem era o responsavel por cada resposta;
- quais prazos estavam proximos;
- quais etapas ja tinham sido concluidas;
- quais alteracoes tinham sido feitas em cada registro;
- como acompanhar auditorias e seus itens de forma centralizada.

A proposta foi transformar esse fluxo em uma aplicacao web simples de acessar pela rede interna, com login, telas de consulta, dashboards e historico de alteracoes.

## Como a solucao foi construida

O projeto foi desenvolvido como uma aplicacao PHP tradicional, usando MySQL para armazenar as informacoes e Apache/XAMPP para execucao local ou em rede interna.

Em vez de manter tudo em uma unica planilha, o sistema separa os dados em tabelas e telas:

- cadastro e edicao de processos;
- lista com busca, filtros e paginacao;
- dashboard pessoal;
- painel gerencial;
- importacao de CSV;
- controle de usuarios e permissoes;
- trilha de auditoria das alteracoes;
- modulo especifico para auditorias CGU/TCU.

## Tecnologias utilizadas

- **PHP**: linguagem principal do sistema. E responsavel pelas telas, regras de negocio, login, importacao e comunicacao com o banco.
- **MySQL**: banco de dados relacional usado para guardar usuarios, processos, auditorias e historico de alteracoes.
- **Apache**: servidor web usado para publicar a aplicacao.
- **XAMPP**: pacote que facilita rodar Apache, PHP e MySQL no Windows durante desenvolvimento ou uso local.
- **HTML, CSS e JavaScript**: tecnologias usadas para montar a interface visual, estilos, botoes, formularios e interacoes da pagina.
- **Python**: usado apenas em scripts auxiliares para tratar bases CSV de auditoria antes da importacao.
- **Git e GitHub**: controle de versao e publicacao do projeto para portifolio.

## Principais funcionalidades

- Login por sessao.
- Perfis de acesso: `servidor`, `coordenador` e `admin`.
- Dashboard do usuario logado.
- Painel gerencial de processos.
- Cadastro, edicao, exclusao e detalhamento de processos.
- Busca global, filtros e paginacao.
- Importacao de processos por CSV.
- Exportacao de dados.
- Registro de alteracoes campo a campo.
- Administracao de usuarios.
- Modulo de auditorias com:
  - importacao de bases tratadas;
  - cadastro manual;
  - edicao;
  - tela de detalhes;
  - itens de auditoria;
  - indicadores;
  - linha do tempo de prazos.

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

O sistema aceita importacao de CSV para alimentar processos e auditorias. Para uso publico no GitHub, esses arquivos nao devem ser enviados ao repositorio.

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

Este projeto demonstra a criacao de uma solucao interna completa a partir de uma necessidade administrativa real:

- substituicao de planilhas por sistema web;
- modelagem de banco relacional;
- controle de acesso;
- organizacao de telas por perfil;
- importacao de dados;
- acompanhamento de prazos;
- historico de alteracoes;
- evolucao incremental por migrations;
- preocupacao com seguranca e publicacao responsavel.

## Observacao

Este projeto foi adaptado para fins de portifolio. Qualquer dado institucional, pessoal ou operacional deve ser removido antes da publicacao publica.
