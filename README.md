<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400"></a></p>

</p>

## Sobre o projeto

API para controle de cadastro de empresas

## Métodos:

-   **[Lista de Empresas]('http://127.0.0.1:8080/api/company?page=1&limit=10')**
    || GET http://127.0.0.1:8080/api/company?page=1&limit=10
-   **[Busca única de um registro]('http://127.0.0.1:8080/api/company/:id')**
    || GET http://127.0.0.1:8080/api/company/:id
-   **[Cadastro de uma empresa]('http://127.0.0.1:8080/api/company/:id')**
    || POST http://127.0.0.1:8080/api/company/:id
-   **[Atualização de uma empresa]('http://127.0.0.1:8080/api/company/:id')**
    || PUT http://127.0.0.1:8080/api/company/:id
-   **[Deleção de uma empresa]('http://127.0.0.1:8080/api/company/:id')**
    || DELETE http://127.0.0.1:8080/api/company/:id

### Retorno da listagem de empresas

```json
{
    "page": "1",
    "total_pages": 1,
    "curren_view": "1 à 0 de 0",
    "items": [
       {
        "document_number": "",
        "social_name": "Nove fantasia",
        "legal_name": "Rasão social",
        "creation_date": "9999-99-99",
        "responsible_email": "email@example.com",
        "responsible_name": "responsável"
        },...
    ]
}
```

---

### Envio nos métodos de criação e edição

```json
{
    "document_number": "",
    "social_name": "Nove fantasia",
    "legal_name": "Rasão social",
    "creation_date": "9999-99-99",
    "responsible_email": "email@example.com",
    "responsible_name": "responsável"
}
```

Todos os campos da requisição são obrigatórios.\
O número de documento é único.\
Existe uma validação de email.

## Configuração

Esse projeto pode ser iniciado com o uso ou não do docker.\
Caso opte por não usar, será necessário ter instalado na máquina tanto php8.1^ quanto Mysql

É necessário também possuir o [composer](https://getcomposer.org) instalado.

Para a primeira configuração é necessário rodar os seguintes comandos no terminal:

`cp .env.example .env` para copiar o arquivo de configurações de variáveis de ambiente .

Caso não use o docker, altere as variáveis de conexão do banco de dados para seu ambiente.local.

`composer install` para fazer a instalação das dependências do projeto.

Com docker: \
`docker compose up -d(opcional para liberar o terminal)` esse comando vai iniciar o projeto\
`docker exec laravel php artisan migrate` esse comando irá criar a tabela de empresas
`docker exec laravel php artisan key:generate` esse comando vai gerar uma chave de criptografia para o projeto

Sem docker:
`php artisan serve` esse comando vai iniciar o projeto\
`php artisan migrate` esse comando irá criar a tabela de empresas
`php artisan key:generate` esse comando vai gerar uma chave de criptografia para o projeto
