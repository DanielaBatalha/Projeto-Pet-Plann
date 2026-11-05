#!/usr/bin/env python3
import json
import sys
from datetime import datetime
from pathlib import Path

try:
    import MySQLdb  # type: ignore
except Exception:  # pragma: no cover
    try:
        import pymysql as MySQLdb  # type: ignore
    except Exception:  # pragma: no cover
        MySQLdb = None

ROOT_DIR = Path(__file__).resolve().parent.parent
ENV_PATH = ROOT_DIR / '.env'


def load_env(path: Path):
    env = {}
    if not path.exists():
        return env

    for line in path.read_text(encoding='utf-8').splitlines():
        line = line.strip()
        if not line or line.startswith('#') or '=' not in line:
            continue
        key, value = [part.strip() for part in line.split('=', 1)]
        env[key] = value.strip("\"'")
    return env


def parse_month_label(value):
    if value is None:
        return None
    if isinstance(value, datetime):
        return value.strftime('%Y-%m')
    text = str(value)
    if len(text) >= 7 and text[4] == '-':
        return text[:7]
    return text


def calculate_linear_forecast(values):
    if not values:
        return 0.0

    if len(values) == 1:
        return float(values[0])

    xs = list(range(len(values)))
    ys = [float(v) for v in values]
    mean_x = sum(xs) / len(xs)
    mean_y = sum(ys) / len(ys)

    numerator = sum((x - mean_x) * (y - mean_y) for x, y in zip(xs, ys))
    denominator = sum((x - mean_x) ** 2 for x in xs)

    slope = numerator / denominator if denominator else 0.0
    intercept = mean_y - slope * mean_x
    prediction = intercept + slope * len(xs)

    return round(float(prediction), 2)


def get_next_month_label(month_label):
    try:
        year, month = month_label.split('-')
        next_year = int(year)
        next_month = int(month) + 1
        if next_month > 12:
            next_month = 1
            next_year += 1
        return f'{next_year}-{next_month:02d}'
    except Exception:
        return 'próximo mês'


def get_db_connection():
    env = load_env(ENV_PATH)
    host = env.get('DB_HOST', '127.0.0.1')
    port = int(env.get('DB_PORT', '3306'))
    user = env.get('DB_USER', 'root')
    password = env.get('DB_PASS', '')
    database = env.get('DB_NAME', 'petshop')

    if MySQLdb is None:
        raise RuntimeError('MySQLdb/PyMySQL not installed')

    conn = MySQLdb.connect(host=host, port=port, user=user, passwd=password, db=database)
    return conn


def get_monthly_sales():
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute(
        """
        SELECT DATE_FORMAT(data_venda, '%Y-%m') AS mes,
               SUM(CAST(valor_total AS DECIMAL(10,2))) AS valor_total,
               SUM(CAST(quantidade_produtos AS SIGNED)) AS quantidade_total
        FROM Vendas
        GROUP BY DATE_FORMAT(data_venda, '%Y-%m')
        ORDER BY mes ASC
        """
    )
    rows = cursor.fetchall()
    cursor.close()
    conn.close()

    months = []
    valores = []
    quantidades = []

    for mes, valor_total, quantidade_total in rows:
        months.append(parse_month_label(mes))
        valores.append(float(valor_total or 0))
        quantidades.append(float(quantidade_total or 0))

    return months, valores, quantidades


def get_service_forecast():
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute(
        """
        SELECT DATE_FORMAT(o.data_Inicio, '%Y-%m') AS mes,
               s.nome_servico AS nome_servico,
               COUNT(*) AS quantidade,
               SUM(CAST(s.valor AS DECIMAL(10,2))) AS valor_total
        FROM ordem_de_servico o
        INNER JOIN servicos s ON s.id = o.servico_id
        WHERE o.data_Inicio IS NOT NULL
        GROUP BY DATE_FORMAT(o.data_Inicio, '%Y-%m'), s.id, s.nome_servico
        ORDER BY mes ASC, s.nome_servico ASC
        """
    )
    rows = cursor.fetchall()
    cursor.close()
    conn.close()

    services = {}
    for mes, nome_servico, quantidade, valor_total in rows:
        if nome_servico not in services:
            services[nome_servico] = {'meses': [], 'quantidades': [], 'valores': []}
        services[nome_servico]['meses'].append(parse_month_label(mes))
        services[nome_servico]['quantidades'].append(float(quantidade or 0))
        services[nome_servico]['valores'].append(float(valor_total or 0))

    if not services:
        return {
            'status': 'ok',
            'model': 'linear_regression',
            'tipo': 'servicos',
            'servicos': [],
            'servico_mais_demandado': {'nome': 'Nenhum serviço', 'quantidade': 0, 'valor_previsto': 0.0, 'mes': 'N/A'},
            'fonte': 'fallback'
        }

    previsoes = []
    for nome_servico, dados in services.items():
        meses = dados['meses']
        quantidades = dados['quantidades']
        valores = dados['valores']
        mes_proximo = get_next_month_label(meses[-1]) if meses else 'próximo mês'
        quantidade_prevista = calculate_linear_forecast(quantidades)
        valor_previsto = calculate_linear_forecast(valores)
        tendencia = 'alta' if quantidades[-1] > quantidades[0] else 'queda' if quantidades[-1] < quantidades[0] else 'estável'
        previsoes.append({
            'nome_servico': nome_servico,
            'mes_proximo': mes_proximo,
            'previsao_quantidade': round(quantidade_prevista, 2),
            'previsao_valor': round(valor_previsto, 2),
            'tendencia': tendencia,
            'historico': [
                {'mes': mes, 'quantidade': round(float(qtd), 2), 'valor_total': round(float(valor), 2)}
                for mes, qtd, valor in zip(meses, quantidades, valores)
            ]
        })

    previsoes.sort(key=lambda item: item['previsao_quantidade'], reverse=True)
    servico_mais_demandado = previsoes[0]

    return {
        'status': 'ok',
        'model': 'linear_regression',
        'tipo': 'servicos',
        'servicos': previsoes,
        'servico_mais_demandado': {
            'nome': servico_mais_demandado['nome_servico'],
            'quantidade': round(servico_mais_demandado['previsao_quantidade'], 2),
            'valor_previsto': round(servico_mais_demandado['previsao_valor'], 2),
            'mes': servico_mais_demandado['mes_proximo']
        },
        'fonte': 'mysql'
    }


def get_product_forecast():
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute(
        """
        SELECT DATE_FORMAT(v.data_venda, '%Y-%m') AS mes,
               p.nome AS nome_produto,
               SUM(pv.quantidade_vendida) AS quantidade,
               SUM(pv.quantidade_vendida * p.valor_venda) AS valor_total
        FROM produtos_venda pv
        INNER JOIN produtos p ON p.id = pv.produto_id
        INNER JOIN Vendas v ON v.id = pv.venda_id
        WHERE v.data_venda IS NOT NULL
        GROUP BY DATE_FORMAT(v.data_venda, '%Y-%m'), p.id, p.nome
        ORDER BY mes ASC, p.nome ASC
        """
    )
    rows = cursor.fetchall()
    cursor.close()
    conn.close()

    products = {}
    for mes, nome_produto, quantidade, valor_total in rows:
        if nome_produto not in products:
            products[nome_produto] = {'meses': [], 'quantidades': [], 'valores': [], 'estoque_atual': 0}
        products[nome_produto]['meses'].append(parse_month_label(mes))
        products[nome_produto]['quantidades'].append(float(quantidade or 0))
        products[nome_produto]['valores'].append(float(valor_total or 0))

    if not products:
        return {
            'status': 'ok',
            'model': 'linear_regression',
            'tipo': 'produtos',
            'produtos': [],
            'produto_mais_vendido': {'nome': 'Nenhum produto', 'quantidade': 0, 'valor_previsto': 0.0, 'mes': 'N/A', 'estoque_atual': 0},
            'fonte': 'fallback'
        }

    previsoes = []
    for nome_produto, dados in products.items():
        meses = dados['meses']
        quantidades = dados['quantidades']
        valores = dados['valores']
        mes_proximo = get_next_month_label(meses[-1]) if meses else 'próximo mês'
        quantidade_prevista = calculate_linear_forecast(quantidades)
        valor_previsto = calculate_linear_forecast(valores)
        tendencia = 'alta' if quantidades[-1] > quantidades[0] else 'queda' if quantidades[-1] < quantidades[0] else 'estável'
        previsoes.append({
            'nome_produto': nome_produto,
            'mes_proximo': mes_proximo,
            'previsao_quantidade': round(quantidade_prevista, 2),
            'previsao_valor': round(valor_previsto, 2),
            'tendencia': tendencia,
            'estoque_atual': 0,
            'historico': [
                {'mes': mes, 'quantidade': round(float(qtd), 2), 'valor_total': round(float(valor), 2)}
                for mes, qtd, valor in zip(meses, quantidades, valores)
            ]
        })

    previsoes.sort(key=lambda item: item['previsao_quantidade'], reverse=True)
    produto_mais_vendido = previsoes[0]

    return {
        'status': 'ok',
        'model': 'linear_regression',
        'tipo': 'produtos',
        'produtos': previsoes,
        'produto_mais_vendido': {
            'nome': produto_mais_vendido['nome_produto'],
            'quantidade': round(produto_mais_vendido['previsao_quantidade'], 2),
            'valor_previsto': round(produto_mais_vendido['previsao_valor'], 2),
            'mes': produto_mais_vendido['mes_proximo'],
            'estoque_atual': produto_mais_vendido['estoque_atual']
        },
        'fonte': 'mysql'
    }


def build_sample_data():
    return [
        '2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06', '2026-07'
    ], [1200.0, 1480.0, 1600.0, 1750.0, 1820.0, 1910.0, 2050.0], [18, 22, 25, 28, 30, 34, 32]


def build_sample_service_data():
    return {
        'status': 'ok',
        'model': 'linear_regression',
        'tipo': 'servicos',
        'servicos': [
            {
                'nome_servico': 'Banho',
                'mes_proximo': '2026-08',
                'previsao_quantidade': 24.0,
                'previsao_valor': 820.0,
                'tendencia': 'alta',
                'historico': [
                    {'mes': '2026-01', 'quantidade': 18.0, 'valor_total': 600.0},
                    {'mes': '2026-02', 'quantidade': 20.0, 'valor_total': 680.0},
                    {'mes': '2026-03', 'quantidade': 22.0, 'valor_total': 740.0},
                ]
            },
            {
                'nome_servico': 'Tosa',
                'mes_proximo': '2026-08',
                'previsao_quantidade': 19.0,
                'previsao_valor': 980.0,
                'tendencia': 'alta',
                'historico': [
                    {'mes': '2026-01', 'quantidade': 12.0, 'valor_total': 620.0},
                    {'mes': '2026-02', 'quantidade': 15.0, 'valor_total': 740.0},
                    {'mes': '2026-03', 'quantidade': 17.0, 'valor_total': 860.0},
                ]
            }
        ],
        'servico_mais_demandado': {'nome': 'Banho', 'quantidade': 24.0, 'valor_previsto': 820.0, 'mes': '2026-08'},
        'fonte': 'fallback'
    }


def generate_forecast(source='auto'):
    months = []
    valores = []
    quantidades = []
    source_used = 'fallback'

    try:
        if source in ('auto', 'mysql'):
            months, valores, quantidades = get_monthly_sales()
            if months:
                source_used = 'mysql'
    except Exception:
        months, valores, quantidades = build_sample_data()

    if not valores:
        months, valores, quantidades = build_sample_data()

    month_label = months[-1] if months else '2026-01'
    next_month_label = get_next_month_label(month_label)

    forecast_value = calculate_linear_forecast(valores)
    forecast_quantity = calculate_linear_forecast(quantidades)
    last_value = valores[-1] if valores else 0.0
    first_value = valores[0] if valores else 0.0
    tendencia = 'alta' if last_value > first_value else 'queda' if last_value < first_value else 'estável'

    payload = {
        'status': 'ok',
        'model': 'linear_regression',
        'mes_proximo': next_month_label,
        'previsao_valor_total': round(forecast_value, 2),
        'previsao_quantidade_total': round(forecast_quantity, 2),
        'valor_atual': round(last_value, 2),
        'tendencia': tendencia,
        'historico': [
            {
                'mes': mes,
                'valor_total': round(float(valor), 2),
                'quantidade_total': round(float(qtd), 2)
            }
            for mes, valor, qtd in zip(months, valores, quantidades)
        ],
        'fonte': source_used
    }

    return payload


if __name__ == '__main__':
    mode = 'total'
    source = 'auto'
    args = sys.argv[1:]

    if '--type' in args:
        index = args.index('--type')
        if index + 1 < len(args):
            mode = args[index + 1]
    elif '-t' in args:
        index = args.index('-t')
        if index + 1 < len(args):
            mode = args[index + 1]

    if '--source' in args:
        index = args.index('--source')
        if index + 1 < len(args):
            source = args[index + 1]
    elif '-s' in args:
        index = args.index('-s')
        if index + 1 < len(args):
            source = args[index + 1]

    if mode in {'servicos', 'service', 'services'}:
        try:
            print(json.dumps(get_service_forecast(), ensure_ascii=False))
        except Exception:
            print(json.dumps(build_sample_service_data(), ensure_ascii=False))
    elif mode in {'produtos', 'product', 'products'}:
        try:
            print(json.dumps(get_product_forecast(), ensure_ascii=False))
        except Exception:
            print(json.dumps({'status': 'ok', 'model': 'fallback', 'tipo': 'produtos', 'produtos': [], 'produto_mais_vendido': {'nome': 'Nenhum produto', 'quantidade': 0, 'valor_previsto': 0.0, 'mes': 'N/A', 'estoque_atual': 0}, 'fonte': 'fallback'}, ensure_ascii=False))
    else:
        print(json.dumps(generate_forecast(source=source), ensure_ascii=False))
