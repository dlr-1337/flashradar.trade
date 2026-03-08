# flashradar.trade

Painel local em PHP para acompanhar odds e manter registros manuais sem instalar PHP no Windows. O projeto ja inclui o runtime em `tools/php/` e foi preparado para subir com clique duplo.

## Visao geral

Este repositorio entrega um painel local com:

- login multiusuario com `admin geral` e usuarios comuns;
- leitura opcional de odds via Odds API;
- funcionamento completo em modo manual quando a API nao esta configurada;
- runtime PHP embutido para rodar em Windows sem instalacao extra.

## Como clonar

Se voce vai usar Git, execute:

```powershell
git clone <URL-DO-REPOSITORIO>
cd flashradar.trade
```

Se o cliente receber uma copia em ZIP, basta extrair a pasta e abrir o projeto.

## Como iniciar com clique duplo

1. Abra a pasta do projeto.
2. Dê duplo clique em `start-local.bat`.
3. Aguarde o terminal abrir e iniciar o servidor local.
4. O navegador deve abrir automaticamente em `http://127.0.0.1:8080/login.php`.

## Primeiro acesso

No primeiro boot, o sistema cria automaticamente o `admin geral` a partir das credenciais em `price_data/config.local.php`.

Se voce estiver usando a configuracao padrao do projeto, o primeiro acesso e:

- usuario: `admin`
- senha: `12345678`

Depois do primeiro login:

1. Entre em `Alterar senha` no painel para trocar a senha do admin.
2. Acesse `Usuarios` para criar logins para outras pessoas.

## Como funciona o login

- O `admin geral` e unico nesta versao.
- O `admin geral` pode criar, editar login, resetar senha e ativar/desativar usuarios comuns.
- Usuarios comuns entram no painel e usam normalmente os registros manuais e as leituras de odds, mas nao administram outros logins.
- As contas ficam em `price_data/storage/users.json`.
- Se esse arquivo for apagado manualmente, o sistema recria o admin inicial a partir de `config.local.php` no proximo acesso.

## Como preencher a chave da API

Se quiser habilitar odds da API:

1. Abra `price_data/config.local.php`.
2. Preencha `api.key` com a sua chave.
3. Confira `api.bookmakers` com a lista de casas desejadas.
4. Salve o arquivo.
5. Reinicie com `start-local.bat`.

Sem chave, o painel continua funcionando em modo manual e mostra um aviso no topo informando que a API nao esta configurada.

### Configuracao padrao da Odds API

O exemplo do projeto usa:

- `api.base_url = https://api.odds-api.io/v3`
- `api.sport = football`
- `api.bookmakers = ['Bet365', 'Betano', '1xbet']`

Voce pode ajustar os bookmakers no `config.local.php`. Quando uma casa prioritaria nao tiver mercado compativel, o backend tenta usar outra casa retornada pela Odds API com mercado `ML`.

Importante: a lista disponivel depende do seu plano e dos bookmakers selecionados no dashboard da Odds API. Se a API responder com erro de acesso, reduza `api.bookmakers` para nomes que a sua conta realmente permite.

## Como parar o servidor

Volte para a janela do terminal aberta pelo `start-local.bat` e pressione `Ctrl+C`.

## Para o cliente

Fluxo recomendado:

1. Extraia a pasta do projeto.
2. Dê duplo clique em `start-local.bat`.
3. Entre com o admin inicial.
4. Troque a senha em `Alterar senha`.
5. Se precisar, crie outros acessos em `Usuarios`.
6. Use o painel normalmente.

Nao precisa instalar PHP, Composer ou qualquer dependencia adicional no Windows.

## Troubleshooting

### Porta 8080 ocupada

Se aparecer erro de porta em uso:

1. Feche outro programa que esteja usando `127.0.0.1:8080`.
2. Ou altere a porta no arquivo `start-local.ps1`.

### PowerShell bloqueado

Abra sempre pelo `start-local.bat`. Ele ja chama o PowerShell com `ExecutionPolicy Bypass`.

### Navegador nao abriu sozinho

Abra manualmente:

```text
http://127.0.0.1:8080/login.php
```
