<?php
session_start();
require_once 'Database.php';

if (!isset($_SESSION['usuario_id']) || (!$_SESSION['is_financeiro'] && !$_SESSION['is_master'])) {
    header("Location: index.php");
    exit;
}

$db = new Database();
$data_hoje = date('Y-m-d');

// ==========================================
// 1. CÁLCULO DOS SALDOS REAIS DOS BANCOS
// ==========================================
$lista_bancos = $db->Query("SELECT ID_BANCO, DESC_BANCO, SALDO_INICIAL FROM BANCOS ORDER BY DESC_BANCO ASC");
$bancos_consolidados = [];
$total_saldos_bancarios = 0.00;

if (!empty($lista_bancos)) {
    foreach ($lista_bancos as $bco) {
        $id_b = intval($bco['ID_BANCO']);
        $saldo_reg = floatval($bco['SALDO_INICIAL']);

        // A. Entradas e Saídas do Financeiro Geral
        $res_fin = $db->Query("SELECT SUM(CASE WHEN PAGAR_RECEBER = 'R' THEN VALOR_BAIXA ELSE -VALOR_BAIXA END) as total FROM FINANCEIRO_MOV WHERE ID_BANCO = $id_b AND DATA_BAIXA IS NOT NULL");
        $saldo_reg += !empty($res_fin) ? floatval($res_fin[0]['total']) : 0.00;

        // B. Entradas de Comissões da Imobiliária (comissao_imob)
        $res_com_ent = $db->Query("SELECT SUM(valor_comissao_imob) as total FROM comissao_imob WHERE id_banco_recebimento = $id_b AND data_recebimento IS NOT NULL");
        $saldo_reg += !empty($res_com_ent) ? floatval($res_com_ent[0]['total']) : 0.00;

        // C. Saídas de Repasses para Corretores (comissao_imob)
        $res_com_sai = $db->Query("SELECT SUM(valor_comissao_corretor) as total FROM comissao_imob WHERE id_banco_pagamento_corretor = $id_b AND data_pagamento_corretor IS NOT NULL");
        $saldo_reg -= !empty($res_com_sai) ? floatval($res_com_sai[0]['total']) : 0.00;

        // D. Transferências Interbancárias (Origem sai, Destino entra)
        $res_tf_s = $db->Query("SELECT SUM(VALOR) as total FROM TRANSFERENCIAS WHERE ID_BANCO_ORIGEM = $id_b");
        $saldo_reg -= !empty($res_tf_s) ? floatval($res_tf_s[0]['total']) : 0.00;

        $res_tf_e = $db->Query("SELECT SUM(VALOR) as total FROM TRANSFERENCIAS WHERE ID_BANCO_DESTINO = $id_b");
        $saldo_reg += !empty($res_tf_e) ? floatval($res_tf_e[0]['total']) : 0.00;

        $bancos_consolidados[] = [
            'NOME' => $bco['DESC_BANCO'],
            'SALDO' => $saldo_reg
        ];
        $total_saldos_bancarios += $saldo_reg;
    }
}

// ==========================================
// 2. INDICADORES: CONTAS EM ABERTO VENCIDAS/ATÉ HOJE
// ==========================================
// Contas a Pagar Operacionais em aberto até a data atual
$res_pagar = $db->Query("SELECT SUM(VALOR) as total FROM FINANCEIRO_MOV WHERE PAGAR_RECEBER = 'P' AND DATA_BAIXA IS NULL AND DATA_VENCIMENTO <= '$data_hoje'");
$total_pagar_aberto = !empty($res_pagar) ? floatval($res_pagar[0]['total']) : 0.00;

// Contas a Receber Operacionais em aberto até a data atual
$res_receber = $db->Query("SELECT SUM(VALOR) as total FROM FINANCEIRO_MOV WHERE PAGAR_RECEBER = 'R' AND DATA_BAIXA IS NULL AND DATA_VENCIMENTO <= '$data_hoje'");
$total_receber_aberto = !empty($res_receber) ? floatval($res_receber[0]['total']) : 0.00;

// ==========================================
// 3. INDICADORES: CARTEIRA DE COMISSÕES (comissao_imob)
// ==========================================
// Comissões da Imobiliária Pendentes de Recebimento pelas Construtoras (Geral)
$res_com_rec = $db->Query("SELECT SUM(valor_comissao_imob) as total FROM comissao_imob WHERE data_recebimento IS NULL");
$comissoes_a_receber = !empty($res_com_rec) ? floatval($res_com_rec[0]['total']) : 0.00;

// Comissões de Corretores Pendentes de Repasse pela Imobiliária (Geral)
$res_com_pag = $db->Query("SELECT SUM(valor_comissao_corretor) as total FROM comissao_imob WHERE data_pagamento_corretor IS NULL");
$comissoes_a_pagar = !empty($res_com_pag) ? floatval($res_com_pag[0]['total']) : 0.00;

// ==========================================
// 4. DADOS DO GRÁFICO: DESPESAS POR CATEGORIA
// ==========================================
$sql_grafico = "SELECT c.DESCRICAO, SUM(f.VALOR) as TOTAL_GAGO 
                FROM FINANCEIRO_MOV f
                INNER JOIN CATEGORIA_DESPESA_RECEITA c ON f.ID_CATEGORIA = c.ID_CATEGORIA
                WHERE f.PAGAR_RECEBER = 'P'
                GROUP BY c.ID_CATEGORIA, c.DESCRICAO";
$dados_grafico = $db->Query($sql_grafico);

$label_categorias = [];
$valor_categorias = [];
if (!empty($dados_grafico)) {
    foreach ($dados_grafico as $val) {
        $label_categorias[] = $val['DESCRICAO'];
        $valor_categorias[] = floatval($val['TOTAL_GAGO']);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fluxo de Caixa Consolidado - PrimeSys Imob</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- ChartJS para renderização limpa do gráfico de Pizza -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .card-3d {
            transition: transform 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }
        .card-3d:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
        }
    </style>
    
    <!-- Favicon padrão para navegadores -->
    <link rel="icon" type="image/x-icon" href="assets/imagens/favicon/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/imagens/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/imagens/favicon/favicon-16x16.png">
    
    <!-- Favicon para dispositivos Apple (iOS) -->
    <link rel="apple-touch-icon" sizes="180x180" href="assets/imagens/favicon/apple-touch-icon.png">
    
    <!-- Web App Manifest (para Android / PWA) -->
    <link rel="manifest" href="assets/imagens/favicon/site.webmanifest">
</head>
<body class="bg-slate-100 min-h-screen font-sans antialiased text-slate-800">

    <div class="max-w-7xl mx-auto py-10 px-4">
        
        <!-- Cabeçalho -->
        <div class="mb-8 select-none">
            <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">
                <i class="fa-solid fa-chart-line text-indigo-600 mr-2"></i>Dashboard de Fluxo de Caixa
            </h1>
            <p class="text-sm text-slate-500 mt-1">Visão contábil consolidada e integrada de contas correntes, títulos operacionais e provisões imobiliárias.</p>
        </div>

        <!-- Layout em Grid Principal -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- COLUNA DA ESQUERDA: SALDO DOS CAIXAS E LISTAGEM DE BANCOS -->
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-gradient-to-br from-slate-800 to-slate-900 text-white p-6 rounded-2xl shadow-xl select-none relative overflow-hidden">
                    <div class="absolute -right-6 -bottom-6 opacity-10 text-9xl">
                        <i class="fa-solid fa-vault"></i>
                    </div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Disponibilidade Total em Caixa</span>
                    <h2 class="text-3xl font-black mt-1 font-mono">
                        R$ <?php echo number_format($total_saldos_bancarios, 2, ',', '.'); ?>
                    </h2>
                    <p class="text-[11px] text-slate-400 mt-2 border-t border-slate-700 pt-2 font-medium">Soma dos saldos iniciais deduzidas todas as baixas e repasses efetuados.</p>
                </div>

                <!-- Detalhamento por Conta Bancária -->
                <div class="bg-white p-5 rounded-2xl shadow-md border border-slate-200">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-4"><i class="fa-solid fa-building-columns text-indigo-500 mr-1"></i>Saldos por Instituição</h3>
                    <div class="divide-y divide-slate-100 max-h-64 overflow-y-auto pr-1">
                        <?php if(!empty($bancos_consolidados)): foreach($bancos_consolidados as $bco_c): ?>
                            <div class="py-3 flex justify-between items-center text-xs">
                                <span class="font-bold text-slate-700 truncate max-w-[160px]"><?php echo htmlspecialchars($bco_c['NOME']); ?></span>
                                <span class="font-mono font-bold <?php echo $bco_c['SALDO'] >= 0 ? 'text-slate-900':'text-red-500'; ?>">
                                    R$ <?php echo number_format($bco_c['SALDO'], 2, ',', '.'); ?>
                                </span>
                            </div>
                        <?php endforeach; else: ?>
                            <div class="text-slate-400 italic py-4 text-center">Nenhuma conta bancária mapeada.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- COLUNA DA DIREITA: CARDS DE INDICADORES E GRÁFICO (O FLUXO) -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Bloco de Cards Indicadores -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    
                    <!-- Card 1: Contas a Pagar Operacionais (Até Hoje) -->
                    <div class="card-3d bg-white p-5 rounded-xl border border-slate-200 flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">A Pagar (Vencidos/Hoje)</span>
                            <span class="text-xl font-black text-red-600 block mt-1 font-mono">R$ <?php echo number_format($total_pagar_aberto, 2, ',', '.'); ?></span>
                            <span class="text-[10px] text-slate-400 font-medium">Apenas títulos operacionais da empresa.</span>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center text-red-500 shadow-inner">
                            <i class="fa-solid fa-arrow-trend-down"></i>
                        </div>
                    </div>

                    <!-- Card 2: Contas a Receber Operacionais (Até Hoje) -->
                    <div class="card-3d bg-white p-5 rounded-xl border border-slate-200 flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">A Receber (Vencidos/Hoje)</span>
                            <span class="text-xl font-black text-emerald-600 block mt-1 font-mono">R$ <?php echo number_format($total_receber_aberto, 2, ',', '.'); ?></span>
                            <span class="text-[10px] text-slate-400 font-medium">Apenas títulos operacionais da empresa.</span>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-500 shadow-inner">
                            <i class="fa-solid fa-arrow-trend-up"></i>
                        </div>
                    </div>

                    <!-- Card 3: Comissões de Construtoras a Receber (Geral) -->
                    <div class="card-3d bg-white p-5 rounded-xl border border-slate-200 flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block"><i class="fa-solid fa-building text-[10px] text-indigo-400 mr-1"></i>Comissões Obras a Receber</span>
                            <span class="text-xl font-black text-slate-800 block mt-1 font-mono">R$ <?php echo number_format($comissoes_a_receber, 2, ',', '.'); ?></span>
                            <span class="text-[10px] text-slate-400 font-medium">Provisões de construtoras em aberto.</span>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600 shadow-inner">
                            <i class="fa-solid fa-receipt"></i>
                        </div>
                    </div>

                    <!-- Card 4: Repasses para Corretores a Pagar (Geral) -->
                    <div class="card-3d bg-white p-5 rounded-xl border border-slate-200 flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block"><i class="fa-solid fa-user-tie text-[10px] text-purple-400 mr-1"></i>Repasses Corretores a Pagar</span>
                            <span class="text-xl font-black text-purple-600 block mt-1 font-mono">R$ <?php echo number_format($comissoes_a_pagar, 2, ',', '.'); ?></span>
                            <span class="text-[10px] text-slate-400 font-medium">Comissões retidas aguardando repasse.</span>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-purple-50 flex items-center justify-center text-purple-600 shadow-inner">
                            <i class="fa-solid fa-handshake-angle"></i>
                        </div>
                    </div>

                </div>

                <!-- Gráfico de Distribuição por Categoria -->
                <div class="bg-white p-6 rounded-2xl shadow-md border border-slate-200">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-6"><i class="fa-solid fa-pie-chart text-indigo-500 mr-1"></i>Rateio de Despesas por Categoria (Contas a Pagar)</h3>
                    <div class="flex justify-center items-center relative" style="height: 260px;">
                        <?php if(!empty($label_categories = $label_categorias)): ?>
                            <canvas id="graficoDespesas"></canvas>
                        <?php else: ?>
                            <div class="text-slate-400 italic text-xs text-center"><i class="fa-regular fa-folder-open text-xl block mb-1"></i>Sem despesas registradas para alimentar o gráfico.</div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </div>

    </div>

    <!-- Script de Inicialização Gráfica Dinâmica -->
    <script>
        <?php if(!empty($label_categorias)): ?>
        const ctx = document.getElementById('graficoDespesas').getContext('2d');
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($label_categorias); ?>,
                datasets: [{
                    data: <?php echo json_encode($valor_categorias); ?>,
                    backgroundColor: [
                        '#4f46e5', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', 
                        '#06b6d4', '#ec4899', '#64748b', '#1e293b', '#f43f5e'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            font: { family: 'sans-serif', size: 11, weight: 'bold' },
                            color: '#475569'
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>