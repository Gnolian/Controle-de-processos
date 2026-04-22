# Controle de Processos

Aplicacao PHP/MySQL para substituir o preenchimento manual de uma planilha de controle de processos por uma interface interna com login, paineis e exportacao no mesmo formato da planilha.

## Recursos

- Login com usuarios e perfis.
- Cadastro e edicao de processos com as mesmas colunas da planilha.
- Listas suspensas para `Resposta`, `Revisao Andrea`, `Assinado`, `Enviado Gab` e `STATUS`.
- Painel individual com processos abertos, prazos e busca por palavra-chave.
- Painel gerencial com totais por status, responsavel e responsavel pela resposta.
- Exportacao CSV compativel com Excel, respeitando a ordem das colunas informada.
- Projeto sem dependencias externas, pensado para rodar no XAMPP/Apache.

## Instalacao no XAMPP

1. Copie a pasta `controle_processos` para `C:\xampp\htdocs\controle_processos`.
2. Inicie `Apache` e `MySQL` no painel do XAMPP.
3. Acesse `http://localhost/phpmyadmin`.
4. Importe o arquivo `database/schema.sql`.
5. Ajuste usuario e senha do banco em `app/config.php`, se necessario.
6. Acesse `http://localhost/controle_processos/public/`.

## Acesso inicial

- Email: `admin@local`
- Senha: `admin123`

Troque a senha depois do primeiro acesso.

## Sobre a conexao com a planilha online

A aplicacao ja exporta um CSV na ordem exata das colunas da planilha, usando `;` como separador para abrir bem no Excel em pt-BR. Para escrever diretamente no arquivo do SharePoint/Excel Online, o caminho recomendado e integrar com Microsoft Graph usando credenciais do tenant. Essa parte depende de permissao institucional da conta Microsoft 365 e de registro de aplicativo no Entra ID.

Quando essas credenciais estiverem disponiveis, o ponto natural de integracao e criar um servico em `app/services/SpreadsheetSync.php` para enviar as linhas cadastradas para a planilha.

## Estrutura

- `app/`: configuracao, conexao com banco, helpers e repositorio dos processos.
- `database/schema.sql`: cria o banco, tabelas e usuario inicial.
- `public/`: paginas acessadas pelo Apache.
- `views/`: partes reutilizadas da interface.
