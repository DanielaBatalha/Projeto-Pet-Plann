<template>
  <section class="ml-page">
    <div class="ml-container">
      <div class="ml-header">
        <div>
          <p class="ml-eyebrow">PetPlann IA</p>
          <h2>Previsão inteligente de vendas e serviços</h2>
        </div>
        <button class="ml-button" @click="carregarPrevisao">
          <i class="fa-solid fa-rotate"></i>
          Atualizar previsão
        </button>
      </div>

      <div v-if="loading" class="ml-loading">
        <i class="fa-solid fa-spinner fa-spin"></i>
        <span>Gerando previsão...</span>
      </div>

      <div v-else class="ml-content">
        <div v-if="forecast" class="ml-grid">
          <article class="ml-card highlight">
            <span class="ml-label">Próximo mês</span>
            <h3>{{ forecast.mes_proximo }}</h3>
            <p class="ml-value">R$ {{ formatMoney(forecast.previsao_valor_total) }}</p>
            <small>Tendência: {{ forecast.tendencia }}</small>
          </article>

          <article class="ml-card">
            <span class="ml-label">Atendimentos estimados</span>
            <h3>{{ formatNumber(forecast.previsao_quantidade_total) }}</h3>
            <p>para o próximo mês</p>
          </article>

          <article class="ml-card">
            <span class="ml-label">Receita atual</span>
            <h3>R$ {{ formatMoney(forecast.valor_atual) }}</h3>
            <p>baseado no último mês registrado</p>
          </article>
        </div>

        <div v-if="serviceForecast && serviceForecast.servicos && serviceForecast.servicos.length" class="ml-services-panel">
          <div class="ml-section-header">
            <h3>Previsão por serviço</h3>
            <span>Serviço mais demandado: <strong>{{ serviceForecast.servico_mais_demandado.nome }}</strong></span>
          </div>

          <div class="ml-service-grid">
            <article v-for="servico in serviceForecast.servicos.slice(0, 4)" :key="servico.nome_servico" class="ml-service-card">
              <div class="ml-service-top">
                <span class="ml-badge">{{ servico.nome_servico }}</span>
                <small>{{ servico.tendencia }}</small>
              </div>
              <h4>{{ servico.mes_proximo }}</h4>
              <p class="ml-service-value">{{ formatNumber(servico.previsao_quantidade) }} atendimentos</p>
              <small>Faturamento estimado: R$ {{ formatMoney(servico.previsao_valor) }}</small>
            </article>
          </div>
        </div>

        <div v-if="productForecast && productForecast.produtos && productForecast.produtos.length" class="ml-services-panel">
          <div class="ml-section-header">
            <h3>Previsão por produto</h3>
            <span>Produto mais vendido: <strong>{{ productForecast.produto_mais_vendido.nome }}</strong></span>
          </div>

          <div class="ml-service-grid">
            <article v-for="produto in productForecast.produtos.slice(0, 4)" :key="produto.nome_produto" class="ml-service-card">
              <div class="ml-service-top">
                <span class="ml-badge">{{ produto.nome_produto }}</span>
                <small>{{ produto.tendencia }}</small>
              </div>
              <h4>{{ produto.mes_proximo }}</h4>
              <p class="ml-service-value">{{ formatNumber(produto.previsao_quantidade) }} unidades</p>
              <small>Estoque atual: {{ formatNumber(produto.estoque_atual) }} · Receita estimada: R$ {{ formatMoney(produto.previsao_valor) }}</small>
            </article>
          </div>
        </div>

        <div v-if="analytics && analytics.resumo" class="ml-services-panel">
          <div class="ml-section-header">
            <h3>Análise operacional</h3>
            <span>Serviço mais lucrativo: <strong>{{ analytics.resumo.servico_mais_lucrativo }}</strong></span>
          </div>

          <div class="ml-grid">
            <article class="ml-card">
              <span class="ml-label">Alertas de estoque</span>
              <h3>{{ analytics.resumo.alertas_estoque }}</h3>
              <p>itens em risco de ruptura</p>
            </article>

            <article class="ml-card">
              <span class="ml-label">Recomendações</span>
              <h3>{{ analytics.resumo.recomendacoes_compra }}</h3>
              <p>compras sugeridas</p>
            </article>

            <article class="ml-card">
              <span class="ml-label">Cobertura</span>
              <h3>{{ formatNumber(analytics.resumo.giro_estoque) }}</h3>
              <p>média de cobertura</p>
            </article>
          </div>

          <div v-if="analytics.decisoes && analytics.decisoes.alertas_automaticos && analytics.decisoes.alertas_automaticos.length" class="ml-decision-box">
            <h4>Decisões automáticas</h4>
            <ul>
              <li v-for="alerta in analytics.decisoes.alertas_automaticos.slice(0, 4)" :key="alerta.nome + alerta.tipo">
                <span :class="['alert-dot', alerta.tipo === 'servico_em_alta' ? 'alta' : 'queda']"></span>
                <strong>{{ alerta.nome }}</strong>: {{ alerta.mensagem }}
              </li>
            </ul>
          </div>

          <div v-if="analytics.decisoes && analytics.decisoes.mes_maior_demanda" class="ml-decision-box compact">
            <h4>Maior demanda estimada</h4>
            <p>{{ analytics.decisoes.mes_maior_demanda.mes }} · {{ formatNumber(analytics.decisoes.mes_maior_demanda.quantidade) }} atendimentos</p>
          </div>

          <div v-if="analytics.produtos_criticos && analytics.produtos_criticos.length" class="ml-analytics-list">
            <h4>Produtos em atenção</h4>
            <table>
              <thead>
                <tr>
                  <th>Produto</th>
                  <th>Estoque</th>
                  <th>Previsão</th>
                  <th>Compra</th>
                  <th>Risco</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in analytics.produtos_criticos.slice(0, 5)" :key="item.nome">
                  <td>{{ item.nome }}</td>
                  <td>{{ formatNumber(item.estoque_atual) }}</td>
                  <td>{{ formatNumber(item.previsao_quantidade) }}</td>
                  <td>{{ formatNumber(item.recomendacao_compra) }}</td>
                  <td>{{ item.nivel_risco }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-if="forecast && forecast.historico && forecast.historico.length" class="ml-table-card">
          <h3>Histórico mensal</h3>
          <table>
            <thead>
              <tr>
                <th>Mês</th>
                <th>Valor</th>
                <th>Quantidade</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in forecast.historico.slice().reverse()" :key="item.mes">
                <td>{{ item.mes }}</td>
                <td>R$ {{ formatMoney(item.valor_total) }}</td>
                <td>{{ formatNumber(item.quantidade_total) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</template>

<script>
import ApiController from '@/ApiController';

export default {
  name: 'MLView',
  data() {
    return {
      forecast: null,
      serviceForecast: null,
      productForecast: null,
      analytics: null,
      loading: true,
    };
  },
  mounted() {
    this.carregarPrevisao();
  },
  methods: {
    async carregarPrevisao() {
      this.loading = true;

      try {
        const [vendas, servicos, produtos, analitica] = await Promise.all([
          ApiController.getPrevisaoVendas(),
          ApiController.getPrevisaoServicos(),
          ApiController.getPrevisaoProdutos(),
          ApiController.getAnalitica()
        ]);

        this.forecast = vendas;
        this.serviceForecast = servicos;
        this.productForecast = produtos;
        this.analytics = analitica;
      } catch (error) {
        this.forecast = {
          mes_proximo: 'Indisponível',
          previsao_valor_total: 0,
          previsao_quantidade_total: 0,
          valor_atual: 0,
          tendencia: 'indefinida',
          historico: []
        };

        this.serviceForecast = {
          servico_mais_demandado: { nome: 'Nenhum serviço' },
          servicos: []
        };

        this.productForecast = {
          produto_mais_vendido: { nome: 'Nenhum produto' },
          produtos: []
        };

        this.analytics = {
          resumo: {
            alertas_estoque: 0,
            recomendacoes_compra: 0,
            servico_mais_lucrativo: 'Nenhum',
            giro_estoque: 0
          },
          produtos_criticos: [],
          recomendacoes_compra: []
        };
      } finally {
        this.loading = false;
      }
    },
    formatMoney(value) {
      return Number(value || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    },
    formatNumber(value) {
      return Number(value || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
      });
    }
  }
};
</script>

<style scoped>
.ml-page {
  padding: 2rem;
}

.ml-container {
  max-width: 1200px;
  margin: 0 auto;
}

.ml-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.5rem;
}

.ml-eyebrow {
  margin: 0;
  color: #5b7cff;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.08rem;
  text-transform: uppercase;
}

.ml-header h2 {
  margin: 0.25rem 0 0;
  color: #1f2937;
}

.ml-button {
  border: none;
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  color: #fff;
  padding: 0.85rem 1.2rem;
  border-radius: 12px;
  cursor: pointer;
  font-weight: 600;
  transition: 0.2s ease;
}

.ml-button:hover {
  transform: translateY(-1px);
}

.ml-loading {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 2rem;
  border-radius: 16px;
  background: #f8fafc;
  color: #374151;
  font-weight: 600;
}

.ml-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.ml-card {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  padding: 1.3rem;
  box-shadow: 0 8px 18px rgba(15, 23, 42, 0.05);
}

.ml-card.highlight {
  background: linear-gradient(135deg, #eff6ff, #dbeafe);
}

.ml-label {
  display: block;
  color: #64748b;
  font-size: 0.8rem;
  margin-bottom: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.06rem;
}

.ml-card h3 {
  margin: 0;
  font-size: 1.6rem;
  color: #0f172a;
}

.ml-value {
  margin: 0.5rem 0;
  font-size: 2rem;
  font-weight: 700;
  color: #0f172a;
}

.ml-card p,
.ml-card small {
  margin: 0;
  color: #475569;
}

.ml-services-panel {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  padding: 1.3rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 8px 18px rgba(15, 23, 42, 0.05);
}

.ml-decision-box {
  margin-top: 1rem;
  padding: 1rem 1.1rem;
  border-radius: 12px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}

.ml-decision-box.compact {
  margin-top: 0.75rem;
}

.ml-decision-box h4 {
  margin: 0 0 0.65rem;
  color: #0f172a;
}

.ml-decision-box ul {
  margin: 0;
  padding-left: 0;
  color: #334155;
  line-height: 1.8;
}

.ml-decision-box li {
  list-style: none;
  display: flex;
  align-items: center;
  gap: 0.55rem;
  margin-bottom: 0.3rem;
}

.alert-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
}

.alert-dot.alta {
  background: #16a34a;
}

.alert-dot.queda {
  background: #ef4444;
}

.ml-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.ml-section-header h3 {
  margin: 0;
  color: #1f2937;
}

.ml-section-header span {
  color: #475569;
}

.ml-service-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
}

.ml-service-card {
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
  padding: 1rem;
}

.ml-service-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.7rem;
}

.ml-badge {
  background: #dbeafe;
  color: #1d4ed8;
  border-radius: 999px;
  padding: 0.35rem 0.75rem;
  font-size: 0.75rem;
  font-weight: 700;
}

.ml-service-card h4 {
  margin: 0 0 0.5rem;
  color: #0f172a;
  font-size: 1.1rem;
}

.ml-service-value {
  margin: 0 0 0.35rem;
  font-size: 1.3rem;
  font-weight: 700;
  color: #0f172a;
}

.ml-table-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  padding: 1.3rem;
  box-shadow: 0 8px 18px rgba(15, 23, 42, 0.05);
}

.ml-table-card h3 {
  margin-top: 0;
  margin-bottom: 1rem;
  color: #1f2937;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th, td {
  padding: 0.75rem 0.5rem;
  text-align: left;
  border-bottom: 1px solid #e5e7eb;
}

th {
  color: #475569;
  font-size: 0.8rem;
  text-transform: uppercase;
}

@media (max-width: 640px) {
  .ml-page {
    padding: 1rem;
  }

  .ml-header,
  .ml-section-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }
}
</style>
