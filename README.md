# PetPlann 🐾

## Descrição 📄

O PetPlann é um sistema de gerenciamento completo e intuitivo desenvolvido como projeto integrador para o curso de Engenharia da Computação na Univesp. O sistema tem como objetivo auxiliar os petshops no gerenciamento eficiente de suas atividades diárias, oferecendo uma solução abrangente para agendamento de serviços, controle de estoque, registro de clientes, animais, funcionários, produtos, serviços e fornecedores, além de facilitar a realização de vendas e geração de relatórios.

## Características ✨

- **Controle de Estoque:** Gerencie todo o estoque de produtos disponíveis no petshop, permitindo a adição, edição e remoção de itens. Saiba sempre o que está disponível e evite a falta de produtos essenciais.

- **Agendamento de Serviços:** Organize os serviços oferecidos pelo petshop, como banho, tosa, consultas veterinárias e outros, através de um sistema de agendamento prático e eficiente.

- **Registro Integrado:** Armazene informações detalhadas sobre os clientes e seus animais de estimação, incluindo nome, raça, data de nascimento e muito mais, além de serviço de registro de funcionários, produtos, serviços e fornecedores. Tenha um banco de dados completo e seguro.

- **Vendas e Pagamentos:** Realize vendas de produtos e serviços diretamente pelo sistema controlando pagamentos. Ofereça opções de pagamento flexíveis aos seus clientes, como dinheiro, cartão de crédito, débito ou pix.

- **Relatórios Gerenciais:** Tenha acesso a relatórios completos e personalizáveis, que fornecem informações importantes sobre vendas, estoque, clientes e serviços. Tome decisões estratégicas baseadas em dados confiáveis e atualizados.

## Integrantes do Projeto 👥
Mauricio Santos dos Anjos: https://github.com/Mauricio0901

Daniela Batalha da Silva: https://github.com/DanielaBatalha

## Tecnologias Utilizadas 🛠️

- Framework Web: Vue.js
- Framework API: Slim Framework
- Banco de Dados: MySQL
- Linguagens Web: HTML, CSS, JavaScript, Python
- Ferramentas de Controle de Versão: Git, GitHub

## Instalação e Configuração ⚙️

1. Clone este repositório: `git clone https://github.com/DanielaBatalha/Projeto-Pet-Plann.git`
2. Em um terminal, instale e inicie o front-end:
   ```bash
   cd frontend
   npm i
   npm run serve
   ```
3. Configure as variáveis do banco de dados em `backend/.env`.
4. Em outro terminal, inicie o back-end com o roteador Slim:
   ```bash
   cd backend
   php -S localhost:84 router.php
   ```
5. Acesse o sistema em `http://localhost:8080`.
