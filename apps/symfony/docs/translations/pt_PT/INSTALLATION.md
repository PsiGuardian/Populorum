# Populorum — Instalação e atualização

🇵🇹 **Português · pt_PT**

> O Populorum é uma simulação fictícia que complementa as instituições existentes. Os votos não têm efeitos jurídicos. Não introduza dados pessoais reais nem segredos de produção.

## Requisitos

São necessários PHP 8.4 ou superior, Composer e SQLite. No Arch Linux, instale os pacotes com pacman. Verifique as extensões abaixo com `php -m`; ative em `/etc/php/php.ini` apenas os módulos necessários que não estejam já compilados no PHP.

`openssl`, `pdo`, `pdo_sqlite`, `sqlite3`, `tokenizer`, `mbstring`, `ctype`, `iconv`, `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml`, `curl`, `zlib`.

```bash
sudo pacman -S --needed php php-sqlite composer
php -v
php -m
```

O arquivo do GitHub não inclui `vendor/`. `composer install` instala as versões fixadas em `composer.lock` e cria `vendor/autoload.php`. Não utilize `composer update` numa instalação normal.

## Primeira instalação

Extraia o arquivo e abra um terminal na raiz do repositório. Se já estiver em `apps/symfony`, não repita o comando `cd`.

```bash
cd apps/symfony
composer install --no-interaction --prefer-dist &&
php tools/init-demo.php &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo &&
php -S 127.0.0.1:5412 -t public
```

Os operadores `&&` interrompem a sequência se um passo falhar: corrija o erro antes de continuar. Abra http://127.0.0.1:5412/; Ctrl+C para o servidor local. A inicialização cria `.env.local` apenas se o ficheiro não existir. A base ativa é `var/populorum.sqlite`. Os cookies do profiler exigem HTTPS, mesmo num teste manual local.

## Atualização

Pare o servidor e faça uma cópia de segurança da base, dos ficheiros e das chaves antes de substituir o código-fonte. Preserve `.env.local`, `.guardian-shield/` e `var/`. Nunca substitua uma base ativa por `data/populorum-demo.sqlite`. Em `apps/symfony`, execute:

```bash
composer install --no-interaction --prefer-dist &&
php tools/init-demo.php &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo
```

Após a conclusão de todos os passos, reinicie o servidor local:

```bash
php -S 127.0.0.1:5412 -t public
```

## Resolução de problemas

Se faltar `vendor/autoload.php`, pare o servidor e execute `composer install --no-interaction --prefer-dist` em `apps/symfony`. Em caso de erro, corrija a versão do PHP ou as extensões indicadas antes de retomar os comandos Symfony. Não utilize `--ignore-platform-reqs`. Para alojamento, o DocumentRoot deve ser `apps/symfony/public/`, com PHP-FPM, Apache ou Nginx e HTTPS; o servidor PHP integrado destina-se apenas a testes locais.

## Documentação de referência (francês)

- [Symfony — fr_FR](../../../README.md)
- [Installation — fr_FR](../../../../../LIRE_AVANT_INSTALLATION.md)
- [Profiler Shield — fr_FR](../../../../../profiler-shield.md)
