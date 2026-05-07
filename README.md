# ferramentas-erp-implantacao
importacao e conferencia de planilhas
# ERP Migration Tools - PHP

Ferramenta desenvolvida em PHP + PostgreSQL para automação de processos de migração, importação e conferência de dados ERP via Excel.

## Objetivo

O projeto foi criado para auxiliar processos de:

- Virada de ERP
- Migração de dados
- Conferência operacional
- Validação de estoque
- Validação de preços
- Controle de lote e número de série

A ferramenta reduz processos manuais e auxilia na identificação de divergências após importações em massa.

---

## Instalação

Instalar dependências do projeto:

```bash
composer install
```


# Funcionalidades

## Importações ERP

Permite importar planilhas Excel para:

- Controle de número de série
- Controle de lotes
- Histórico de movimentações
- Entradas fiscais
- Inventário de produtos

---

## Ajustes de Controle

Permite ajustar produtos que controlam:

- Número de série
- Controle de lote

---

## Conferências Pós Migração

Após importar as planilhas no ERP, a ferramenta realiza conferências automáticas e informa divergências entre:

- Planilha x Banco de Dados

---

# Validações Disponíveis

## Estoque

Conferência de:

- Quantidade em estoque
- Estoque por setor
- Estoque assistência
- Estoque avaliação
- Estoque depósito
- Estoque perda
- Estoque terceiro
- Estoque FIFO

---

## Preços

Conferência de:

- Preço de compra
- Preço de venda
- Condições de venda
- Tabelas de preço

---

## Marketplace

Conferência de:

- SKU de plataformas
- Produtos não encontrados
- Divergência de SKU

---

# Recursos da Ferramenta

- Upload de planilhas Excel
- Exportação Excel
- Processamento em lote
- Validação automática
- Tratamento de divergências
- Rollback em caso de erro
- Ajuste automático de sequência
- Delete e insert controlado

---

# Tecnologias Utilizadas

- PHP
- PostgreSQL
- HTML
- CSS
- PhpSpreadsheet

---

# Configuração

Criar o arquivo:

config.php

baseado em:

config.example.php

e configurar:

- host
- banco de dados
- usuário
- senha
- porta PostgreSQL

---

# Utilização

1. Escolha o banco de dados
2. Baixe o manual de importação
3. Utilize os modelos Excel disponíveis
4. Importe os dados no ERP
5. Execute as conferências automáticas
6. Analise as divergências encontradas

---

# Estrutura do Projeto

```text
/processos
/conferencias
/exportacoes
/modelos
/temp
```

---

# Observações

Projeto desenvolvido para automatizar processos operacionais relacionados à migração e validação de dados ERP.
