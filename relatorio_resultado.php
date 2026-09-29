<?php
session_start();
require_once 'Database.php';

if (!isset($_SESSION['usuario_id']) || (!$_SESSION['is_financeiro'] && !$_SESSION['is_master'])) {
    header("Location: index.php");
    exit;
}

$db = new Database();

// Captura do período (Padrão: Mês Atual Completo de 2026)
$data_ini = isset($_GET['data_ini']) ? trim($_GET['data_ini']) : date('Y-m-01');
$data_fim = isset($_GET['data_fim']) ? trim($_GET['data_fim']) : date('Y-m-t');

// ==========================================
// 1. RECEITAS E DESPESAS OPERACIONAIS (AGRUPADAS POR CATEGORIA)
// ==========================================
// Receitas Recebidas (Liquidadas) no período
$sql_rec = "SELECT c.DESCRICAO as CATEGORIA, SUM(f.VALOR_BAIXA) as TOTAL_CAT
            FROM FINANCEIRO_MOV f
            INNER JOIN CATEGORIA_DESPESA_RECEITA c ON f.ID_CATEGORIA = c.ID_CATEGORIA
            WHERE f.PAGAR_RECEBER = 'R' AND f.DATA_BAIXA BETWEEN '$data_ini' AND '$data_fim'
            GROUP BY c.ID_CATEGORIA, c.DESCRICAO ORDER BY c.DESCRICAO ASC";
$receitas_cat = $db->Query($sql_rec);

// Despesas Pagas (Liquidadas) no período
$sql_desp = "SELECT c.DESCRICAO as CATEGORIA, SUM(f.VALOR_BAIXA) as TOTAL_CAT
             FROM FINANCEIRO_MOV f
             INNER JOIN CATEGORIA_DESPESA_RECEITA c ON f.ID_CATEGORIA = c.ID_CATEGORIA
             WHERE f.PAGAR_RECEBER = 'P' AND f.DATA_BAIXA BETWEEN '$data_ini' AND '$data_fim'
             GROUP BY c.ID_CATEGORIA, c.DESCRICAO ORDER BY c.DESCRICAO ASC";
$despesas_cat = $db->Query($sql_desp);

// ==========================================
// 2. CARTEIRA DE COMISSÕES DO PERÍODO (Provisões de Caixa)
// ==========================================
// Comissões a Receber (Faturamento pendente das Construtoras cujas Notas foram anexadas no período)
$sql_com_rec = "SELECT SUM(valor_comissao_imob) as total FROM comissao_imob WHERE data_recebimento IS NULL AND data_emissao BETWEEN '$data_ini' AND '$data_fim'";
$res_com_rec = $db->Query($sql_com_rec);
$comissoes_pend_receber = !empty($res_com_rec) ? floatval($res_com_rec[0]['total']) : 0.00;

// Comissões a Pagar (Repasses pendentes aos corretores no período)
$sql_com_pag = "SELECT SUM(valor_comissao_corretor) as total FROM comissao_imob WHERE data_pagamento_corretor IS NULL AND data_emissao BETWEEN '$data_ini' AND '$data_fim'";
$res_com_pag = $db->Query($sql_com_pag);
$comissoes_pend_pagar = !empty($res_com_pag) ? floatval($res_com_pag[0]['total']) : 0.00;

// ==========================================
// 3. SALDO DOS CAIXAS E BANCOS (Posição Atual Consolidada)
// ==========================================
$lista_bancos = $db->Query("SELECT ID_BANCO, DESC_BANCO, SALDO_INICIAL FROM BANCOS ORDER BY DESC_BANCO ASC");
$saldos_bancos = [];
if (!empty($lista_bancos)) {
    foreach ($lista_bancos as $bco) {
        $id_b = intval($bco['ID_BANCO']);
        $saldo_b = floatval($bco['SALDO_INICIAL']);
        
        $r1 = $db->Query("SELECT SUM(CASE WHEN PAGAR_RECEBER = 'R' THEN VALOR_BAIXA ELSE -VALOR_BAIXA END) as t FROM FINANCEIRO_MOV WHERE ID_BANCO = $id_b AND DATA_BAIXA IS NOT NULL");
        $saldo_b += !empty($r1) ? floatval($r1[0]['t']) : 0.00;

        $r2 = $db->Query("SELECT SUM(valor_comissao_imob) as t FROM comissao_imob WHERE id_banco_recebimento = $id_b AND data_recebimento IS NOT NULL");
        $saldo_b += !empty($r2) ? floatval($r2[0]['t']) : 0.00;

        $r3 = $db->Query("SELECT SUM(valor_comissao_corretor) as t FROM comissao_imob WHERE id_banco_pagamento_corretor = $id_b AND data_pagamento_corretor IS NOT NULL");
        $saldo_b -= !empty($r3) ? floatval($r3[0]['t']) : 0.00;

        $r4 = $db->Query("SELECT SUM(VALOR) as t FROM TRANSFERENCIAS WHERE ID_BANCO_DESTINO = $id_b");
        $saldo_b += !empty($r4) ? floatval($r4[0]['t']) : 0.00;

        $r5 = $db->Query("SELECT SUM(VALOR) as t FROM TRANSFERENCIAS WHERE ID_BANCO_ORIGEM = $id_b");
        $saldo_b -= !empty($r5) ? floatval($r5[0]['t']) : 0.00;

        $saldos_bancos[] = ['NOME' => $bco['DESC_BANCO'], 'SALDO' => $saldo_b];
    }
}

// ==========================================
// 4. PERFORMANCE COMERCIAL: LEADS, VGV E VENDAS DO PERÍODO
// ==========================================
// Total de Leads Capturados no período
$sql_leads = "SELECT COUNT(*) as total FROM clientes WHERE data_cadastro BETWEEN '$data_ini' AND '$data_fim'";
$res_leads = $db->Query($sql_leads);
$total_leads_periodo = !empty($res_leads) ? $res_leads[0]['total'] : 0;

// VGV Total da Empresa no Período
$sql_vgv_total = "SELECT SUM(vgv) as total FROM clientes WHERE status_lead IN ('Venda Fechada', 'Nota Anexada', 'Comissão Recebida') AND data_venda BETWEEN '$data_ini' AND '$data_fim'";
$res_vgv_total = $db->Query($sql_vgv_total);
$vgv_total_empresa = !empty($res_vgv_total) ? floatval($res_vgv_total[0]['total']) : 0.00;

// VGV por Corretor no Período
$sql_vgv_corr = "SELECT u.nome as CORRETOR, SUM(c.vgv) as TOTAL_VGV, COUNT(c.id_cliente) as QTD_VENDAS
                 FROM clientes c
                 INNER JOIN usuarios u ON c.id_corretor = u.id_usuario
                 WHERE c.status_lead IN ('Venda Fechada', 'Nota Anexada', 'Comissão Recebida') AND c.data_venda BETWEEN '$data_ini' AND '$data_fim'
                 GROUP BY u.id_usuario, u.nome ORDER BY TOTAL_VGV DESC";
$vgv_por_corretor = $db->Query($sql_vgv_corr);

// Listagem Cronológica de Vendas Efetivadas no período
$sql_lista_vendas = "SELECT c.nome as CLIENTE, c.data_venda, c.vgv, c.status_lead, e.nome_empreendimento, u.nome as CORRETOR
                     FROM clientes c
                     INNER JOIN empreendimentos e ON c.id_empreendimento = e.id_empreendimento
                     INNER JOIN usuarios u ON c.id_corretor = u.id_usuario
                     WHERE c.status_lead IN ('Venda Fechada', 'Nota Anexada', 'Comissão Recebida') AND c.data_venda BETWEEN '$data_ini' AND '$data_fim'
                     ORDER BY c.data_venda ASC";
$lista_vendas_detalhada = $db->Query($sql_lista_vendas);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório Executivo de Resultados - PrimeSys Imob</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; p: 0; }
            .print-shadow { box-shadow: none !important; border: 1px solid #cbd5e1 !important; }
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

    <!-- Barra de Controle Superior (Ocultada no papel) -->
    <div class="no-print bg-white border-b border-slate-200 sticky top-0 z-30 px-6 py-4 shadow-sm select-none flex flex-col sm:flex-row justify-between items-center gap-4">
        <form action="" method="GET" class="flex flex-wrap items-center gap-3 text-xs font-bold">
            <div>
                <label class="block text-slate-500 uppercase mb-0.5">Resultado De</label>
                <input type="date" name="data_ini" value="<?php echo $data_ini; ?>" class="p-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-slate-500 uppercase mb-0.5">Até</label>
                <input type="date" name="data_fim" value="<?php echo $data_fim; ?>" class="p-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold px-5 py-2.5 rounded-lg text-xs self-end h-9 transition-colors shadow">Filtrar Período</button>
        </form>
        <button onclick="window.print()" class="bg-slate-800 hover:bg-slate-900 text-white font-bold py-2 px-5 rounded-lg text-xs flex items-center gap-2 shadow self-end sm:self-auto h-9">
            <i class="fa-solid fa-print"></i> Imprimir Balanço
        </button>
    </div>

    <div class="max-w-6xl mx-auto py-8 px-4 space-y-8 print-shadow">
        
        <!-- Cabeçalho Institucional -->
        <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-200 flex justify-between items-start">
            <div>
                <span class="text-xs font-black tracking-widest text-indigo-600 uppercase bg-indigo-50 px-2.5 py-1 rounded-md">Balanço Corporativo</span>
                <h1 class="text-3xl font-black text-slate-800 tracking-tight mt-3">Relatório de Resultados & Performance</h1>
                <p class="text-sm text-slate-500 mt-1">Resultados apurados entre <strong><?php echo date('d/m/Y', strtotime($data_ini)); ?></strong> e <strong><?php echo date('d/m/Y', strtotime($data_fim)); ?></strong>.</p>
            </div>
            <div class="text-right text-xs text-slate-400 font-bold uppercase tracking-wider">
                <div>PrimeSys Imob &copy; 2026</div>
                <div class="text-[10px] text-slate-300 font-medium normal-case mt-1">Gerado em: <?php echo date('d/m/Y H:i'); ?></div>
            </div>
        </div>

        <!-- SEÇÃO 1: PAINEL EXECUTIVO DE SÍNTESE (KPIs COLORIDOS) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm border-l-4 border-l-indigo-600">
                <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider">VGV Total Comercial</span>
                <div class="text-xl font-black text-slate-800 font-mono mt-1">R$ <?php echo number_format($vgv_total_empresa, 2, ',', '.'); ?></div>
            </div>
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm border-l-4 border-l-blue-500">
                <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Novos Leads Captados</span>
                <div class="text-xl font-black text-blue-600 font-mono mt-1"><?php echo $total_leads_periodo; ?> Leads</div>
            </div>
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm border-l-4 border-l-amber-500">
                <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Comissões a Receber (Provisão)</span>
                <div class="text-xl font-black text-amber-600 font-mono mt-1">R$ <?php echo number_format($comissoes_pend_receber, 2, ',', '.'); ?></div>
            </div>
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm border-l-4 border-l-purple-500">
                <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Repasses a Pagar (Retido)</span>
                <div class="text-xl font-black text-purple-600 font-mono mt-1">R$ <?php echo number_format($comissoes_pend_pagar, 2, ',', '.'); ?></div>
            </div>
        </div>

        <!-- SEÇÃO 2: DRE GERENCIAL - DESPESAS E RECEITAS AGRUPADAS POR CATEGORIA -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <!-- Caixa de Receitas Realizadas -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h3 class="text-sm font-black text-emerald-600 uppercase tracking-wider mb-4 border-b pb-2 flex items-center gap-2">
                    <i class="fa-solid fa-square-plus"></i> Receitas Realizadas (Por Categoria)
                </h3>
                <table class="w-full text-xs">
                    <tbody>
                        <?php 
                        $total_receitas_liq = 0.00;
                        if(!empty($receitas_cat)): foreach($receitas_cat as $r): 
                            $total_receitas_liq += floatval($r['TOTAL_CAT']);
                        ?>
                            <tr class="border-b border-slate-100"><td class="py-2.5 font-bold text-slate-700"><?php echo htmlspecialchars($r['CATEGORIA']); ?></td><td class="py-2.5 text-right font-mono font-bold text-slate-900">R$ <?php echo number_format($r['TOTAL_CAT'], 2, ',', '.'); ?></td></tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="2" class="py-4 text-slate-400 italic">Sem receitas liquidadas no período.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="mt-4 pt-3 border-t-2 border-slate-200 flex justify-between items-center text-sm font-black text-emerald-600">
                    <span>RECEITA OPERACIONAL LÍQUIDA</span>
                    <span class="font-mono">R$ <?php echo number_format($total_receitas_liq, 2, ',', '.'); ?></span>
                </div>
            </div>

            <!-- Caixa de Despesas Liquidadas -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h3 class="text-sm font-black text-red-500 uppercase tracking-wider mb-4 border-b pb-2 flex items-center gap-2">
                    <i class="fa-solid fa-square-minus"></i> Despesas Liquidadas (Por Categoria)
                </h3>
                <table class="w-full text-xs">
                    <tbody>
                        <?php 
                        $total_despesas_liq = 0.00;
                        if(!empty($despesas_cat)): foreach($despesas_cat as $d): 
                            $total_despesas_liq += floatval($d['TOTAL_CAT']);
                        ?>
                            <tr class="border-b border-slate-100"><td class="py-2.5 font-bold text-slate-700"><?php echo htmlspecialchars($d['CATEGORIA']); ?></td><td class="py-2.5 text-right font-mono font-bold text-slate-900">R$ <?php echo number_format($d['TOTAL_CAT'], 2, ',', '.'); ?></td></tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="2" class="py-4 text-slate-400 italic">Sem despesas pagas no período.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="mt-4 pt-3 border-t-2 border-slate-200 flex justify-between items-center text-sm font-black text-red-500">
                    <span>TOTAL DE CUSTOS DESBOLSADOS</span>
                    <span class="font-mono">R$ <?php echo number_format($total_despesas_liq, 2, ',', '.'); ?></span>
                </div>
            </div>
        </div>

        <!-- SEÇÃO 3: RANKING DE VGV POR CORRETOR -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-black text-slate-800 uppercase tracking-wider mb-4 border-b pb-2 flex items-center gap-2">
                <i class="fa-solid fa-medal text-indigo-500"></i> Performance e Desempenho por Corretor
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 uppercase font-bold border-b border-slate-200"><th class="p-3">Corretor Técnico</th><th class="p-3 text-center w-36">Contratos Fechados</th><th class="p-3 text-right w-48">Volume Geral de Venda (VGV)</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if(!empty($vgv_por_corretor)): foreach($vgv_por_corretor as $v_cor): ?>
                            <tr><td class="p-3 font-bold text-slate-800"><?php echo htmlspecialchars($v_cor['CORRETOR']); ?></td><td class="p-3 text-center font-semibold text-slate-500"><?php echo $v_cor['QTD_VENDAS']; ?> un.</td><td class="p-3 text-right font-mono font-black text-slate-900">R$ <?php echo number_format($v_cor['TOTAL_VGV'], 2, ',', '.'); ?></td></tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="3" class="p-4 text-center italic text-slate-400">Nenhum corretor pontuou faturamento no período.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SEÇÃO 4: EXTRATO INTEGRADO DAS CONTAS BANCÁRIAS -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-black text-slate-800 uppercase tracking-wider mb-4 border-b pb-2 flex items-center gap-2">
                <i class="fa-solid fa-wallet text-slate-500"></i> Saldos e Disponibilidades Bancárias (Atual)
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                <?php if(!empty($saldos_bancos)): foreach($saldos_bancos as $s_bco): ?>
                    <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl flex justify-between items-center">
                        <span class="font-bold text-xs text-slate-600 truncate max-w-[130px]"><?php echo htmlspecialchars($s_bco['NOME']); ?></span>
                        <span class="font-mono font-bold text-xs <?php echo $s_bco['SALDO'] >=0 ? 'text-slate-900':'text-red-500'; ?>">R$ <?php echo number_format($s_bco['SALDO'], 2, ',', '.'); ?></span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- SEÇÃO 5: MAPA GERAL E DETALHADO DE VENDAS EFETIVADAS -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-black text-slate-800 uppercase tracking-wider mb-4 border-b pb-2 flex items-center gap-2">
                <i class="fa-solid fa-folder-closed text-indigo-500"></i> Listagem Cronológica de Vendas do Período
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 uppercase font-bold border-b border-slate-200"><th class="p-3">Data Venda</th><th class="p-3">Cliente / Comprador</th><th class="p-3">Produto / Empreendimento</th><th class="p-3">Responsável</th><th class="p-3 text-center">Status</th><th class="p-3 text-right">VGV Realizado</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if(!empty($lista_vendas_detalhada)): foreach($lista_vendas_detalhada as $venda): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="p-3 font-mono text-slate-500"><?php echo date('d/m/Y', strtotime($venda['data_venda'])); ?></td>
                                <td class="p-3 font-bold text-slate-800"><?php echo htmlspecialchars($venda['CLIENTE']); ?></td>
                                <td class="p-3 font-semibold text-slate-600"><?php echo htmlspecialchars($venda['nome_empreendimento']); ?></td>
                                <td class="p-3 text-slate-500"><?php echo htmlspecialchars($venda['CORRETOR']); ?></td>
                                <td class="p-3 text-center">
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border bg-indigo-50 text-indigo-700 border-indigo-200"><?php echo htmlspecialchars($venda['status_lead']); ?></span>
                                </td>
                                <td class="p-3 text-right font-mono font-bold text-slate-900">R$ <?php echo number_format($venda['vgv'], 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6" class="p-6 text-center italic text-slate-400">Nenhum contrato assinado ou fechado dentro destas datas.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</body>
</html>