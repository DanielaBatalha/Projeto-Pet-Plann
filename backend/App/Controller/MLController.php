<?php

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class MLController
{
    private function fallbackForecast(): array
    {
        return [
            'status' => 'ok',
            'model' => 'fallback',
            'mes_proximo' => '2026-12',
            'previsao_valor_total' => 2410.35,
            'previsao_quantidade_total' => 38.0,
            'valor_atual' => 2050.0,
            'tendencia' => 'alta',
            'historico' => [
                ['mes' => '2026-01', 'valor_total' => 1200.0, 'quantidade_total' => 18.0],
                ['mes' => '2026-02', 'valor_total' => 1480.0, 'quantidade_total' => 22.0],
                ['mes' => '2026-03', 'valor_total' => 1600.0, 'quantidade_total' => 25.0],
                ['mes' => '2026-04', 'valor_total' => 1750.0, 'quantidade_total' => 28.0],
                ['mes' => '2026-05', 'valor_total' => 1820.0, 'quantidade_total' => 30.0],
                ['mes' => '2026-06', 'valor_total' => 1910.0, 'quantidade_total' => 34.0],
                ['mes' => '2026-07', 'valor_total' => 2050.0, 'quantidade_total' => 32.0],
            ],
            'fonte' => 'fallback'
        ];
    }

    private function fallbackServiceForecast(): array
    {
        return [
            'status' => 'ok',
            'model' => 'fallback',
            'tipo' => 'servicos',
            'servicos' => [
                [
                    'nome_servico' => 'Banho',
                    'mes_proximo' => '2026-08',
                    'previsao_quantidade' => 24.0,
                    'previsao_valor' => 820.0,
                    'tendencia' => 'alta',
                    'historico' => [
                        ['mes' => '2026-01', 'quantidade' => 18.0, 'valor_total' => 600.0],
                        ['mes' => '2026-02', 'quantidade' => 20.0, 'valor_total' => 680.0],
                        ['mes' => '2026-03', 'quantidade' => 22.0, 'valor_total' => 740.0],
                    ]
                ],
                [
                    'nome_servico' => 'Tosa',
                    'mes_proximo' => '2026-08',
                    'previsao_quantidade' => 19.0,
                    'previsao_valor' => 980.0,
                    'tendencia' => 'alta',
                    'historico' => [
                        ['mes' => '2026-01', 'quantidade' => 12.0, 'valor_total' => 620.0],
                        ['mes' => '2026-02', 'quantidade' => 15.0, 'valor_total' => 740.0],
                        ['mes' => '2026-03', 'quantidade' => 17.0, 'valor_total' => 860.0],
                    ]
                ]
            ],
            'servico_mais_demandado' => ['nome' => 'Banho', 'quantidade' => 24.0, 'valor_previsto' => 820.0, 'mes' => '2026-08'],
            'fonte' => 'fallback'
        ];
    }

    private function resolverPython(): ?string
    {
        $envPython = getenv('PYTHON');
        if ($envPython && is_file($envPython)) {
            return $envPython;
        }

        $envPython3 = getenv('PYTHON3');
        if ($envPython3 && is_file($envPython3)) {
            return $envPython3;
        }

        $basePaths = [];
        $basePaths[] = 'C:\\Users\\Dell\\AppData\\Local\\Programs\\Python\\Python312\\python.exe';
        $basePaths[] = 'C:\\Users\\Dell\\AppData\\Local\\Programs\\Python\\Python311\\python.exe';
        $basePaths[] = 'C:\\Users\\Dell\\AppData\\Local\\Programs\\Python\\Python310\\python.exe';
        $basePaths[] = 'C:\\Users\\Dell\\AppData\\Local\\Programs\\Python\\Python39\\python.exe';

        $localAppData = getenv('LOCALAPPDATA');
        if ($localAppData) {
            $basePaths[] = rtrim($localAppData, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'Programs' . DIRECTORY_SEPARATOR . 'Python';
        }

        $userProfile = getenv('USERPROFILE');
        if ($userProfile) {
            $basePaths[] = rtrim($userProfile, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'AppData' . DIRECTORY_SEPARATOR . 'Local' . DIRECTORY_SEPARATOR . 'Programs' . DIRECTORY_SEPARATOR . 'Python';
        }

        $basePaths[] = 'C:\\Python312';
        $basePaths[] = 'C:\\Python311';
        $basePaths[] = 'C:\\Python310';
        $basePaths[] = 'C:\\Python39';

        foreach ($basePaths as $basePath) {
            if (is_file($basePath)) {
                return $basePath;
            }

            if (!is_dir($basePath)) {
                continue;
            }

            $versions = glob(rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
            if (!$versions) {
                $versions = [$basePath];
            }

            foreach ($versions as $versionPath) {
                $candidate = rtrim($versionPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'python.exe';
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function calcularPrevisaoLinear(array $valores): float
    {
        if (empty($valores)) {
            return 0.0;
        }

        if (count($valores) === 1) {
            return (float) $valores[0];
        }

        $x = range(0, count($valores) - 1);
        $y = array_map(static fn ($valor) => (float) $valor, $valores);
        $mediaX = array_sum($x) / count($x);
        $mediaY = array_sum($y) / count($y);

        $numerador = 0.0;
        $denominador = 0.0;

        foreach ($x as $index => $valorX) {
            $numerador += ($valorX - $mediaX) * ($y[$index] - $mediaY);
            $denominador += ($valorX - $mediaX) ** 2;
        }

        $coeficienteAngular = $denominador === 0.0 ? 0.0 : $numerador / $denominador;
        $coeficienteLinear = $mediaY - ($coeficienteAngular * $mediaX);

        return round($coeficienteLinear + ($coeficienteAngular * count($x)), 2);
    }

    private function getNextMonthLabel(string $mes): string
    {
        if (!$mes || !preg_match('/^(\d{4})-(\d{2})$/', $mes, $matches)) {
            return 'próximo mês';
        }

        $ano = (int) $matches[1];
        $mesNumero = (int) $matches[2];
        $novoMes = $mesNumero + 1;
        $novoAno = $ano;

        if ($novoMes > 12) {
            $novoMes = 1;
            $novoAno++;
        }

        return sprintf('%d-%02d', $novoAno, $novoMes);
    }

    private function conectarBanco(): ?\PDO
    {
        \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../')->safeLoad();

        foreach (['DB_HOST', 'DB_NAME', 'DB_PORT', 'DB_USER', 'DB_PASS'] as $key) {
            if (!isset($_ENV[$key])) {
                return null;
            }
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;port=%s',
            $_ENV['DB_HOST'],
            $_ENV['DB_NAME'],
            $_ENV['DB_PORT']
        );

        try {
            $pdo = new \PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS']);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (\PDOException $e) {
            return null;
        }
    }

    private function previsaoVendasMysql(): ?array
    {
        $pdo = $this->conectarBanco();
        if ($pdo === null) {
            return null;
        }

        $sql = "
            SELECT DATE_FORMAT(data_venda, '%Y-%m') AS mes,
                   SUM(quantidade_produtos) AS quantidade_total,
                   SUM(valor_total) AS valor_total
            FROM Vendas
            WHERE data_venda IS NOT NULL
            GROUP BY DATE_FORMAT(data_venda, '%Y-%m')
            ORDER BY mes ASC
        ";

        $rows = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($rows)) {
            return null;
        }

        $historico = [];
        foreach ($rows as $row) {
            $historico[] = [
                'mes' => $row['mes'],
                'valor_total' => (float) ($row['valor_total'] ?? 0),
                'quantidade_total' => (float) ($row['quantidade_total'] ?? 0)
            ];
        }

        $valores = array_map(static fn ($item) => $item['valor_total'], $historico);
        $quantidades = array_map(static fn ($item) => $item['quantidade_total'], $historico);
        $ultimo = end($historico);
        $mesProximo = $ultimo ? $this->getNextMonthLabel($ultimo['mes']) : 'próximo mês';

        $ultimoValor = $ultimo['valor_total'] ?? 0.0;
        $ultimoQuantidade = $ultimo['quantidade_total'] ?? 0.0;
        $primeiroValor = $valores[0] ?? 0.0;
        $primeiraQuantidade = $quantidades[0] ?? 0.0;

        if ($ultimoValor > $primeiroValor) {
            $tendencia = 'alta';
        } elseif ($ultimoValor < $primeiroValor) {
            $tendencia = 'queda';
        } else {
            $tendencia = 'estável';
        }

        return [
            'status' => 'ok',
            'model' => 'linear_regression',
            'mes_proximo' => $mesProximo,
            'previsao_valor_total' => $this->calcularPrevisaoLinear($valores),
            'previsao_quantidade_total' => $this->calcularPrevisaoLinear($quantidades),
            'valor_atual' => round($ultimoValor, 2),
            'tendencia' => $tendencia,
            'historico' => $historico,
            'fonte' => 'mysql'
        ];
    }

    private function previsaoServicosMysql(): ?array
    {
        $pdo = $this->conectarBanco();
        if ($pdo === null) {
            return null;
        }

        $sql = "
            SELECT DATE_FORMAT(o.data_Inicio, '%Y-%m') AS mes,
                   s.nome_servico AS nome_servico,
                   COUNT(*) AS quantidade,
                   SUM(CAST(s.valor AS DECIMAL(10,2))) AS valor_total
            FROM ordem_de_servico o
            INNER JOIN servicos s ON s.id = o.servico_id
            WHERE o.data_Inicio IS NOT NULL
            GROUP BY DATE_FORMAT(o.data_Inicio, '%Y-%m'), s.id, s.nome_servico
            ORDER BY mes ASC, s.nome_servico ASC
        ";

        $rows = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($rows)) {
            return null;
        }

        $porServico = [];
        foreach ($rows as $row) {
            $nome = $row['nome_servico'];
            if (!isset($porServico[$nome])) {
                $porServico[$nome] = ['historico' => [], 'quantidade_total' => 0];
            }

            $porServico[$nome]['historico'][] = [
                'mes' => $row['mes'],
                'quantidade' => (float) $row['quantidade'],
                'valor_total' => (float) $row['valor_total']
            ];
        }

        $servicos = [];
        foreach ($porServico as $nome => $dados) {
            $quantidades = array_map(static fn ($item) => $item['quantidade'], $dados['historico']);
            $valores = array_map(static fn ($item) => $item['valor_total'], $dados['historico']);
            $ultimoMes = end($dados['historico']);
            $mesProximo = $ultimoMes ? $this->getNextMonthLabel($ultimoMes['mes']) : 'próximo mês';
            $primeiraQtd = $quantidades[0] ?? 0.0;
            $ultimaQtd = $quantidades[count($quantidades) - 1] ?? 0.0;

            if ($ultimaQtd > $primeiraQtd) {
                $tendencia = 'alta';
            } elseif ($ultimaQtd < $primeiraQtd) {
                $tendencia = 'queda';
            } else {
                $tendencia = 'estável';
            }

            $servicos[] = [
                'nome_servico' => $nome,
                'mes_proximo' => $mesProximo,
                'previsao_quantidade' => $this->calcularPrevisaoLinear($quantidades),
                'previsao_valor' => $this->calcularPrevisaoLinear($valores),
                'tendencia' => $tendencia,
                'historico' => $dados['historico']
            ];
        }

        usort($servicos, static fn ($a, $b) => $b['previsao_quantidade'] <=> $a['previsao_quantidade']);

        $maisDemandado = $servicos[0] ?? null;

        return [
            'status' => 'ok',
            'model' => 'linear_regression',
            'tipo' => 'servicos',
            'servicos' => $servicos,
            'servico_mais_demandado' => $maisDemandado ? [
                'nome' => $maisDemandado['nome_servico'],
                'quantidade' => round($maisDemandado['previsao_quantidade'], 2),
                'valor_previsto' => round($maisDemandado['previsao_valor'], 2),
                'mes' => $maisDemandado['mes_proximo']
            ] : ['nome' => 'Nenhum serviço', 'quantidade' => 0, 'valor_previsto' => 0.0, 'mes' => 'N/A'],
            'fonte' => 'mysql'
        ];
    }

    private function previsaoProdutosMysql(): ?array
    {
        $pdo = $this->conectarBanco();
        if ($pdo === null) {
            return null;
        }

        $sql = "
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
        ";

        $rows = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($rows)) {
            return null;
        }

        $porProduto = [];
        foreach ($rows as $row) {
            $nome = $row['nome_produto'];
            if (!isset($porProduto[$nome])) {
                $porProduto[$nome] = ['historico' => []];
            }

            $porProduto[$nome]['historico'][] = [
                'mes' => $row['mes'],
                'quantidade' => (float) $row['quantidade'],
                'valor_total' => (float) $row['valor_total']
            ];
        }

        $produtos = [];
        foreach ($porProduto as $nome => $dados) {
            $quantidades = array_map(static fn ($item) => $item['quantidade'], $dados['historico']);
            $valores = array_map(static fn ($item) => $item['valor_total'], $dados['historico']);
            $ultimoMes = end($dados['historico']);
            $mesProximo = $ultimoMes ? $this->getNextMonthLabel($ultimoMes['mes']) : 'próximo mês';
            $primeiraQtd = $quantidades[0] ?? 0.0;
            $ultimaQtd = $quantidades[count($quantidades) - 1] ?? 0.0;

            if ($ultimaQtd > $primeiraQtd) {
                $tendencia = 'alta';
            } elseif ($ultimaQtd < $primeiraQtd) {
                $tendencia = 'queda';
            } else {
                $tendencia = 'estável';
            }

            $stmt = $pdo->prepare('SELECT quantidade FROM produtos WHERE nome = :nome LIMIT 1');
            $stmt->execute(['nome' => $nome]);
            $estoqueAtual = (int) ($stmt->fetchColumn() ?: 0);

            $produtos[] = [
                'nome_produto' => $nome,
                'mes_proximo' => $mesProximo,
                'previsao_quantidade' => $this->calcularPrevisaoLinear($quantidades),
                'previsao_valor' => $this->calcularPrevisaoLinear($valores),
                'tendencia' => $tendencia,
                'estoque_atual' => $estoqueAtual,
                'historico' => $dados['historico']
            ];
        }

        usort($produtos, static fn ($a, $b) => $b['previsao_quantidade'] <=> $a['previsao_quantidade']);

        $maisVendido = $produtos[0] ?? null;

        return [
            'status' => 'ok',
            'model' => 'linear_regression',
            'tipo' => 'produtos',
            'produtos' => $produtos,
            'produto_mais_vendido' => $maisVendido ? [
                'nome' => $maisVendido['nome_produto'],
                'quantidade' => round($maisVendido['previsao_quantidade'], 2),
                'valor_previsto' => round($maisVendido['previsao_valor'], 2),
                'mes' => $maisVendido['mes_proximo'],
                'estoque_atual' => (int) $maisVendido['estoque_atual']
            ] : ['nome' => 'Nenhum produto', 'quantidade' => 0, 'valor_previsto' => 0.0, 'mes' => 'N/A', 'estoque_atual' => 0],
            'fonte' => 'mysql'
        ];
    }

    private function executarScript(string $tipo = 'total'): array
    {
        return [];
    }

    public function previsaoVendas(Request $request, Response $response, array $args): Response
    {
        $data = $this->previsaoVendasMysql();

        if (empty($data)) {
            $data = $this->fallbackForecast();
        }

        $response->getBody()->write(json_encode($data));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

    public function previsaoServicos(Request $request, Response $response, array $args): Response
    {
        $data = $this->previsaoServicosMysql();

        if (empty($data)) {
            $data = $this->fallbackServiceForecast();
        }

        $response->getBody()->write(json_encode($data));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

    public function previsaoProdutos(Request $request, Response $response, array $args): Response
    {
        $data = $this->previsaoProdutosMysql();

        if (empty($data)) {
            $data = [
                'status' => 'ok',
                'model' => 'fallback',
                'tipo' => 'produtos',
                'produtos' => [],
                'produto_mais_vendido' => ['nome' => 'Nenhum produto', 'quantidade' => 0, 'valor_previsto' => 0.0, 'mes' => 'N/A', 'estoque_atual' => 0],
                'fonte' => 'fallback'
            ];
        }

        $response->getBody()->write(json_encode($data));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

    public function previsaoAnalitica(Request $request, Response $response, array $args): Response
    {
        $data = $this->previsaoAnaliticaMysql();

        if (empty($data)) {
            $data = [
                'status' => 'ok',
                'model' => 'fallback',
                'tipo' => 'analitica',
                'resumo' => [
                    'alertas_estoque' => 0,
                    'recomendacoes_compra' => 0,
                    'servico_mais_lucrativo' => 'Nenhum',
                    'giro_estoque' => 0.0
                ],
                'produtos_criticos' => [],
                'recomendacoes_compra' => [],
                'servicos_lucrativos' => [],
                'fonte' => 'fallback'
            ];
        }

        $response->getBody()->write(json_encode($data));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

    private function previsaoAnaliticaMysql(): ?array
    {
        $pdo = $this->conectarBanco();
        if ($pdo === null) {
            return null;
        }

        $produtosSql = "
            SELECT p.id, p.nome, p.quantidade AS estoque_atual, p.valor_venda,
                   DATE_FORMAT(v.data_venda, '%Y-%m') AS mes,
                   SUM(pv.quantidade_vendida) AS quantidade,
                   SUM(pv.quantidade_vendida * p.valor_venda) AS valor_total
            FROM produtos p
            LEFT JOIN produtos_venda pv ON pv.produto_id = p.id
            LEFT JOIN Vendas v ON v.id = pv.venda_id
            GROUP BY p.id, p.nome, p.quantidade, p.valor_venda, DATE_FORMAT(v.data_venda, '%Y-%m')
            ORDER BY mes ASC, p.nome ASC
        ";

        $produtoRows = $pdo->query($produtosSql)->fetchAll(\PDO::FETCH_ASSOC);

        $servicosSql = "
            SELECT DATE_FORMAT(o.data_Inicio, '%Y-%m') AS mes,
                   s.nome_servico AS nome_servico,
                   COUNT(*) AS quantidade,
                   SUM(CAST(s.valor AS DECIMAL(10,2))) AS valor_total
            FROM ordem_de_servico o
            INNER JOIN servicos s ON s.id = o.servico_id
            WHERE o.data_Inicio IS NOT NULL
            GROUP BY DATE_FORMAT(o.data_Inicio, '%Y-%m'), s.id, s.nome_servico
            ORDER BY mes ASC, s.nome_servico ASC
        ";

        $servicoRows = $pdo->query($servicosSql)->fetchAll(\PDO::FETCH_ASSOC);

        $produtosPorNome = [];
        foreach ($produtoRows as $row) {
            $nome = $row['nome'];
            if (!isset($produtosPorNome[$nome])) {
                $produtosPorNome[$nome] = ['estoque_atual' => (int) ($row['estoque_atual'] ?? 0), 'historico' => []];
            }

            $mes = $row['mes'];
            if ($mes) {
                $produtosPorNome[$nome]['historico'][] = [
                    'mes' => $mes,
                    'quantidade' => (float) ($row['quantidade'] ?? 0),
                    'valor_total' => (float) ($row['valor_total'] ?? 0)
                ];
            }
        }

        $produtosCriticos = [];
        foreach ($produtosPorNome as $nome => $dados) {
            if (empty($dados['historico'])) {
                continue;
            }

            $quantidades = array_map(static fn ($item) => $item['quantidade'], $dados['historico']);
            $valores = array_map(static fn ($item) => $item['valor_total'], $dados['historico']);
            $forecastQtd = $this->calcularPrevisaoLinear($quantidades);
            $forecastValor = $this->calcularPrevisaoLinear($valores);
            $estoqueAtual = $dados['estoque_atual'];
            $cobertura = $estoqueAtual > 0 ? $estoqueAtual / max($forecastQtd, 1) : 0;
            $recomendacao = max(0, (int) round($forecastQtd + ($forecastQtd * 0.35) - $estoqueAtual));
            $nivelRisco = $cobertura < 1.0 ? 'crítico' : ($cobertura < 1.5 ? 'alto' : 'normal');

            if ($nivelRisco !== 'normal' || $recomendacao > 0) {
                $produtosCriticos[] = [
                    'nome' => $nome,
                    'estoque_atual' => $estoqueAtual,
                    'previsao_quantidade' => round($forecastQtd, 2),
                    'previsao_valor' => round($forecastValor, 2),
                    'cobertura' => round($cobertura, 2),
                    'nivel_risco' => $nivelRisco,
                    'recomendacao_compra' => $recomendacao,
                    'mes_reposicao' => $this->getNextMonthLabel($dados['historico'][count($dados['historico']) - 1]['mes'])
                ];
            }
        }

        usort($produtosCriticos, static fn ($a, $b) => $b['recomendacao_compra'] <=> $a['recomendacao_compra']);

        $servicos = [];
        $porServico = [];
        foreach ($servicoRows as $row) {
            $nome = $row['nome_servico'];
            if (!isset($porServico[$nome])) {
                $porServico[$nome] = ['historico' => []];
            }
            $porServico[$nome]['historico'][] = [
                'mes' => $row['mes'],
                'quantidade' => (float) ($row['quantidade'] ?? 0),
                'valor_total' => (float) ($row['valor_total'] ?? 0)
            ];
        }

        foreach ($porServico as $nome => $dados) {
            $quantidades = array_map(static fn ($item) => $item['quantidade'], $dados['historico']);
            $valores = array_map(static fn ($item) => $item['valor_total'], $dados['historico']);
            $servicos[] = [
                'nome' => $nome,
                'previsao_quantidade' => $this->calcularPrevisaoLinear($quantidades),
                'previsao_valor' => $this->calcularPrevisaoLinear($valores),
                'tendencia' => (end($quantidades) > $quantidades[0]) ? 'alta' : ((end($quantidades) < $quantidades[0]) ? 'queda' : 'estável'),
                'historico' => $dados['historico']
            ];
        }

        usort($servicos, static fn ($a, $b) => $b['previsao_valor'] <=> $a['previsao_valor']);

        $recomendacoesCompra = array_map(static function ($item) {
            return [
                'nome' => $item['nome'],
                'estoque_atual' => $item['estoque_atual'],
                'previsao_quantidade' => round($item['previsao_quantidade'], 2),
                'recomendacao_compra' => $item['recomendacao_compra'],
                'nivel_risco' => $item['nivel_risco'],
                'cobertura' => round($item['cobertura'], 2),
                'mes_reposicao' => $item['mes_reposicao']
            ];
        }, $produtosCriticos);

        $alertasAutomaticos = [];
        foreach ($servicos as $servico) {
            $historico = $servico['historico'] ?? [];
            if (count($historico) < 2) {
                continue;
            }

            $valorInicial = (float) ($historico[0]['quantidade'] ?? 0);
            $valorFinal = (float) ($historico[count($historico) - 1]['quantidade'] ?? 0);
            $variacao = $valorInicial > 0 ? (($valorFinal - $valorInicial) / $valorInicial) * 100 : 0;

            if ($variacao >= 20) {
                $alertasAutomaticos[] = [
                    'tipo' => 'servico_em_alta',
                    'nome' => $servico['nome'],
                    'mensagem' => 'demanda cresceu ' . round($variacao, 1) . '% no período.',
                    'prioridade' => 'alta'
                ];
            } elseif ($variacao <= -20) {
                $alertasAutomaticos[] = [
                    'tipo' => 'servico_em_queda',
                    'nome' => $servico['nome'],
                    'mensagem' => 'demanda caiu ' . round(abs($variacao), 1) . '% no período.',
                    'prioridade' => 'media'
                ];
            }
        }

        $porMes = [];
        foreach ($servicoRows as $row) {
            $mes = (string) $row['mes'];
            $qtd = (float) ($row['quantidade'] ?? 0);
            $porMes[$mes] = ($porMes[$mes] ?? 0) + $qtd;
        }

        $mesMaiorDemanda = ['mes' => 'N/A', 'quantidade' => 0];
        foreach ($porMes as $mes => $quantidade) {
            if ($quantidade > $mesMaiorDemanda['quantidade']) {
                $mesMaiorDemanda = ['mes' => $mes, 'quantidade' => round($quantidade, 2)];
            }
        }

        $alertasEstoque = count($produtosCriticos);
        $servicoMaisLucro = $servicos[0] ?? ['nome' => 'Nenhum serviço', 'previsao_valor' => 0];
        $giroEstoque = $alertasEstoque > 0 ? round(array_sum(array_map(static fn ($item) => $item['cobertura'], $produtosCriticos)) / $alertasEstoque, 2) : 0.0;

        return [
            'status' => 'ok',
            'model' => 'analytic_layer',
            'tipo' => 'analitica',
            'resumo' => [
                'alertas_estoque' => $alertasEstoque,
                'recomendacoes_compra' => count($recomendacoesCompra),
                'servico_mais_lucrativo' => $servicoMaisLucro['nome'],
                'giro_estoque' => $giroEstoque
            ],
            'produtos_criticos' => $produtosCriticos,
            'recomendacoes_compra' => $recomendacoesCompra,
            'servicos_lucrativos' => array_slice($servicos, 0, 5),
            'decisoes' => [
                'alertas_automaticos' => $alertasAutomaticos,
                'mes_maior_demanda' => $mesMaiorDemanda,
                'servico_recomendado' => $servicoMaisLucro['nome']
            ],
            'fonte' => 'mysql'
        ];
    }
}
