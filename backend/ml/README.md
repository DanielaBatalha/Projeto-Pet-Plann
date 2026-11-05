# Módulo de Machine Learning

Este diretório guarda a lógica de previsão do PetPlann para indicar tendência de vendas futuras.

## Estrutura

- `forecast.py`: script principal de previsão usando regressão linear simples sobre os dados mensais de vendas.

## Como executar

```bash
cd backend
python3 ml/forecast.py
```

## Saída esperada

O script retorna um JSON com os campos:

- `mes_proximo`
- `previsao_valor_total`
- `previsao_quantidade_total`
- `tendencia`
- `historico`
