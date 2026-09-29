<?php
session_start();
require_once 'Database.php';

if (!isset($_SESSION['usuario_id']) || (!$_SESSION['is_financeiro'] && !$_SESSION['is_master'])) {
    die('Acesso negado.');
}

$db = new Database();

// 1. CAPTURA DOS PARÂMETROS GARANTINDO INTEIROS (Evita falha de validação)
$rep_emp   = isset($_GET['rep_emp']) ? intval($_GET['rep_emp']) : 0;
$rep_const = isset($_GET['rep_const']) ? intval($_GET['rep_const']) : 0;
$rep_corr  = isset($_GET['rep_corr']) ? intval($_GET['rep_corr']) : 0;
$data_de   = isset($_GET['rep_data_de']) ? trim($_GET['rep_data_de']) : '';
$data_ate  = isset($_GET['rep_data_ate']) ? trim($_GET['rep_data_ate']) : '';
$status    = isset($_GET['rep_status']) ? trim($_GET['rep_status']) : 'ambas';

// 2. QUERY BASE LIMPA
$sql = "SELECT co.*, e.nome_empreendimento, c.nome_fantasia, cl.nome as nome_cliente, u.nome as nome_corretor
        FROM comissao_imob co
        INNER JOIN empreendimentos e ON co.id_empreendimento = e.id_empreendimento
        INNER JOIN construtoras c ON co.id_construtora = c.id_construtora
        INNER JOIN clientes cl ON co.id_cliente = cl.id_cliente
        INNER JOIN usuarios u ON co.id_corretor = u.id_usuario
        WHERE 1=1";

// 3. APLICAÇÃO RÍGIDA DOS FILTROS INDEPENDENTES
if ($rep_emp > 0) {
    $sql .= " AND co.id_empreendimento = $rep_emp";
}

if ($rep_const > 0) {
    $sql .= " AND co.id_construtora = $rep_const";
}

if ($rep_corr > 0) {
    $sql .= " AND co.id_corretor = $rep_corr";
}

// Filtro de período isolado
if (!empty($data_de) && !empty($data_ate)) {
    $sql .= " AND co.data_emissao >= '$data_de' AND co.data_emissao <= '$data_ate'";
}

// Lógica de Status Condicional por Alvo (Corretor vs Imobiliária)
if ($rep_corr > 0) {
    if ($status === 'pago')    $sql .= " AND co.data_pagamento_corretor IS NOT NULL";
    if ($status === 'aberto')  $sql .= " AND co.data_pagamento_corretor IS NULL";
} else {
    if ($status === 'pago')    $sql .= " AND co.data_recebimento IS NOT NULL";
    if ($status === 'aberto')  $sql .= " AND co.data_recebimento IS NULL";
}

$sql .= " ORDER BY co.data_emissao ASC";
$dados = $db->Query($sql);

$subtotal = 0.00;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório Contábil de Comissões - PrimeSys Imob</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS focado em ocultar elementos de tela na impressão física/PDF */
        @media print {
            .no-print { display: none !important; }
            body { background-color: #fff; padding: 0; }
            .print-container { border: none; shadow: none; max-width: 100%; width: 100%; }
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
<body class="bg-slate-50 min-h-screen p-6 font-sans text-slate-800 flex flex-col items-center">

    <!-- Painel Flutuante de Comando de Impressão (Ocultado no papel) -->
    <div class="no-print w-full max-w-4xl bg-white p-4 rounded-xl shadow-md border mb-6 flex justify-between items-center">
        <span class="text-sm font-semibold text-slate-500"><i class="fa-solid fa-circle-info text-indigo-500 mr-1"></i> Visualize as quebras de página antes de confirmar a impressão física.</span>
        <button onclick="window.print()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg text-sm flex items-center gap-2 shadow transition-all transform active:scale-95">
            <i class="fa-solid fa-print"></i> Executar Impressão / Salvar PDF
        </button>
    </div>

    <!-- Folha do Relatório Estruturada -->
    <div class="print-container bg-white w-full max-w-4xl p-10 rounded-2xl shadow-sm border border-slate-200">
        
        <!-- Cabeçalho Institucional -->
        <div class="flex justify-between items-start border-b pb-4 mb-6">
            <div>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">PRIMESYS IMOB</h2>
                <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-0.5">Relatório Gerencial de Comissões</p>
            </div>
            <div class="text-right text-xs font-medium text-slate-400">
                <div>Emitido em: <?php echo date('d/m/Y H:i'); ?></div>
                <div>Filtro: <?php echo ($rep_corr > 0) ? 'Focado por Corretor' : 'Visão Geral Imobiliária'; ?></div>
            </div>
        </div>

        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="border-b-2 border-slate-300 text-slate-500 font-bold uppercase tracking-wider">
                    <th class="pb-3">Emissão</th>
                    <th class="pb-3">Empreendimento</th>
                    <th class="pb-3">Cliente / Comprador</th>
                    <?php if ($rep_corr > 0): ?>
                        <th class="pb-3 text-center">Status Corretor</th>
                        <th class="pb-3 text-center">Data Pgto</th>
                        <th class="pb-3 text-right">Comissão Corretor</th>
                    <?php else: ?>
                        <th class="pb-3">Corretor</th>
                        <th class="pb-3 text-center">Status Receb.</th>
                        <th class="pb-3 text-right">Comissão Imob</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 text-slate-700">
                <?php if (!empty($dados)): foreach ($dados as $linha): ?>
                    <tr class="align-middle">
                        <td class="py-3 font-medium"><?php echo date('d/m/Y', strtotime($linha['data_emissao'])); ?></td>
                        <td class="py-3 font-semibold"><?php echo htmlspecialchars($linha['nome_empreendimento']); ?></td>
                        <td class="py-3"><?php echo htmlspecialchars($linha['nome_cliente']); ?></td>
                        
                        <?php if ($rep_corr > 0): 
                            // Renderização de Colunas caso o Filtro de Corretor esteja ativado
                            $is_pago = !empty($linha['data_pagamento_corretor']);
                            $subtotal += floatval($linha['valor_comissao_corretor']);
                        ?>
                            <td class="py-3 text-center font-bold <?php echo $is_pago ? 'text-emerald-600':'text-red-500'; ?>">
                                <?php echo $is_pago ? 'Pago' : 'Em Aberto'; ?>
                            </td>
                            <td class="py-3 text-center font-medium text-slate-500">
                                <?php echo $is_pago ? date('d/m/Y', strtotime($linha['data_pagamento_corretor'])) : '---'; ?>
                            </td>
                            <td class="py-3 text-right font-bold text-slate-900">R$ <?php echo number_format($linha['valor_comissao_corretor'], 2, ',', '.'); ?></td>
                        
                        <?php else: 
                            // Renderização Padrão voltada para o Caixa da Imobiliária
                            $is_recebido = !empty($linha['data_recebimento']);
                            $subtotal += floatval($linha['valor_comissao_imob']);
                        ?>
                            <td class="py-3 text-slate-500 font-medium"><?php echo htmlspecialchars($linha['nome_corretor']); ?></td>
                            <td class="py-3 text-center font-bold <?php echo $is_recebido ? 'text-emerald-600':'text-amber-600'; ?>">
                                <?php echo $is_recebido ? 'Recebido' : 'Pendente'; ?>
                            </td>
                            <td class="py-3 text-right font-bold text-slate-900">R$ <?php echo number_format($linha['valor_comissao_imob'], 2, ',', '.'); ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="6" class="py-8 text-center text-slate-400 italic">Nenhum lançamento correspondeu aos critérios informados no fechamento.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Bloco do Sub-Total do Relatório Comercial -->
        <div class="mt-6 border-t-2 border-slate-300 pt-4 flex justify-end">
            <div class="text-right space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wide">Sub-total Acumulado</span>
                <div class="text-2xl font-black text-slate-900 font-mono">
                    R$ <?php echo number_format($subtotal, 2, ',', '.'); ?>
                </div>
            </div>
        </div>

    </div>

</body>
</html>