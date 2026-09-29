<?php
session_start();
require_once 'Database.php';

// Permissão: Apenas Financeiro e Master podem ver comissões
if (!isset($_SESSION['usuario_id']) || (!$_SESSION['is_financeiro'] && !$_SESSION['is_master'])) {
    header("Location: index.php");
    exit;
}

$db = new Database();

// Carrega dados para os filtros
$filtros_empreendimentos = $db->Query("SELECT id_empreendimento, nome_empreendimento FROM empreendimentos ORDER BY nome_empreendimento ASC");
$filtros_construtoras = $db->Query("SELECT id_construtora, nome_fantasia FROM construtoras ORDER BY nome_fantasia ASC");
$filtros_corretores = $db->Query("SELECT id_usuario, nome FROM usuarios WHERE corretor = true or master_user = true ORDER BY nome ASC");

// Captura filtros selecionados
$f_emp = isset($_GET['f_emp']) ? intval($_GET['f_emp']) : 0;
$f_const = isset($_GET['f_const']) ? intval($_GET['f_const']) : 0;
$f_corr = isset($_GET['f_corr']) ? intval($_GET['f_corr']) : 0;
$f_emissao = isset($_GET['f_emissao']) ? trim($_GET['f_emissao']) : '';
$f_venc = isset($_GET['f_venc']) ? trim($_GET['f_venc']) : '';

// Montagem da query com múltiplos relacionamentos de apoio
$sql = "SELECT co.*, e.nome_empreendimento, c.nome_fantasia, cl.nome as nome_cliente, u.nome as nome_corretor
        FROM comissao_imob co
        INNER JOIN empreendimentos e ON co.id_empreendimento = e.id_empreendimento
        INNER JOIN construtoras c ON co.id_construtora = c.id_construtora
        INNER JOIN clientes cl ON co.id_cliente = cl.id_cliente
        INNER JOIN usuarios u ON co.id_corretor = u.id_usuario
        WHERE 1=1";

if ($f_emp > 0)   $sql .= " AND co.id_empreendimento = $f_emp";
if ($f_const > 0) $sql .= " AND co.id_construtora = $f_const";
if ($f_corr > 0)  $sql .= " AND co.id_corretor = $f_corr";
if (!empty($f_emissao)) $sql .= " AND co.data_emissao = '$f_emissao'";
if (!empty($f_venc))    $sql .= " AND co.data_vencimento = '$f_venc'";

$sql .= " ORDER BY co.id_comissao DESC";
$comissoes = $db->Query($sql);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Comissões - PrimeSys Imob</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .btn-3d { transition: all 0.1s ease-in-out; box-shadow: 0 4px 0 0 rgba(0, 0, 0, 0.15); }
        .btn-3d:active { transform: translateY(4px); box-shadow: 0 0px 0 0 rgba(0, 0, 0, 0.15); }
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
        
        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">
                <i class="fa-solid fa-receipt text-indigo-600 mr-2"></i>Controle de Comissões & Faturamento
            </h1>
            <p class="text-sm text-slate-500 mt-1">Monitore repasses de construtoras, notas fiscais lançadas e pagamentos devidos aos corretores.</p>
        </div>
         
        <button type="button" onclick="abrirModalRelatorio()" class="btn-3d bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-5 rounded-lg flex items-center justify-center gap-2 self-start md:self-auto">
            <i class="fa-solid fa-print"></i> Relatório
        </button>
        <br>
         
        <!-- Painel Avançado de Filtros -->
        <div class="bg-white p-6 rounded-2xl shadow-md border border-slate-200 mb-6">
            <form action="" method="GET" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Empreendimento</label>
                    <select name="f_emp" class="w-full p-2 bg-slate-50 border rounded-lg text-sm text-slate-700">
                        <option value="0">Todos</option>
                        <?php foreach($filtros_empreendimentos as $item) echo "<option value='{$item['id_empreendimento']}' ".($f_emp==$item['id_empreendimento']?'selected':'').">{$item['nome_empreendimento']}</option>"; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Construtora</label>
                    <select name="f_const" class="w-full p-2 bg-slate-50 border rounded-lg text-sm text-slate-700">
                        <option value="0">Todas</option>
                        <?php foreach($filtros_construtoras as $item) echo "<option value='{$item['id_construtora']}' ".($f_const==$item['id_construtora']?'selected':'').">{$item['nome_fantasia']}</option>"; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Corretor</label>
                    <select name="f_corr" class="w-full p-2 bg-slate-50 border rounded-lg text-sm text-slate-700">
                        <option value="0">Todos</option>
                        <?php foreach($filtros_corretores as $item) echo "<option value='{$item['id_usuario']}' ".($f_corr==$item['id_usuario']?'selected':'').">{$item['nome']}</option>"; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Data Emissão</label>
                    <input type="date" name="f_emissao" value="<?php echo $f_emissao; ?>" class="w-full p-1.5 bg-slate-50 border rounded-lg text-sm text-slate-700">
                </div>
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Data Vencimento</label>
                        <input type="date" name="f_venc" value="<?php echo $f_venc; ?>" class="w-full p-1.5 bg-slate-50 border rounded-lg text-sm text-slate-700">
                    </div>
                    <button type="submit" class="btn-3d bg-slate-800 hover:bg-slate-900 text-white font-bold px-4 py-2 rounded-lg text-xs h-9">Filtrar</button>
                </div>
            </form>
        </div>

        <!-- Tabela de Lançamentos -->
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 text-xs font-bold uppercase tracking-wider">
                            <th class="py-4 px-4">Negócio (Cliente/Obra)</th>
                            <th class="py-4 px-4">Datas (Emissão/Venc)</th>
                            <th class="py-4 px-4 text-right">VGV Real</th>
                            <th class="py-4 px-4 text-right">Comissão Imob</th>
                            <th class="py-4 px-4 text-right">Repasse Corretor</th>
                            <th class="py-4 px-4 text-center">Status Receb.</th>
                            <th class="py-4 px-4 text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 text-sm">
                        <?php if(!empty($comissoes)): foreach($comissoes as $com): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-4 px-4">
                                    <div class="font-bold text-slate-800"><?php echo htmlspecialchars($com['nome_cliente']); ?></div>
                                    <div class="text-xs text-slate-400 font-medium"><?php echo htmlspecialchars($com['nome_empreendimento']); ?> | <?php echo htmlspecialchars($com['nome_fantasia']); ?></div>
                                    <div class="text-xs text-indigo-500 font-semibold mt-0.5"><i class="fa-solid fa-user-tie text-[10px] mr-1"></i><?php echo htmlspecialchars($com['nome_corretor']); ?></div>
                                </td>
                                <td class="py-4 px-4 text-xs font-medium text-slate-600">
                                    <div><span class="text-slate-400">Emissão:</span> <?php echo date('d/m/Y', strtotime($com['data_emissao'])); ?></div>
                                    <div class="mt-0.5"><span class="text-slate-400">Venc:</span> <?php echo date('d/m/Y', strtotime($com['data_vencimento'])); ?></div>
                                </td>
                                <td class="py-4 px-4 text-right font-semibold text-slate-700">R$ <?php echo number_format($com['valor_geral_venda'], 2, ',', '.'); ?></td>
                                <td class="py-4 px-4 text-right font-bold text-slate-900">R$ <?php echo number_format($com['valor_comissao_imob'], 2, ',', '.'); ?></td>
                                <td class="py-4 px-4 text-right font-semibold text-indigo-600">R$ <?php echo number_format($com['valor_comissao_corretor'], 2, ',', '.'); ?></td>
                                <td class="py-4 px-4 text-center">
                                    <?php if(!empty($com['data_recebimento'])): ?>
                                        <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">Quitado</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-amber-100 text-amber-800 border border-amber-200">Aguardando</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-4 text-center">
                                    <a href="comissao_editar.php?id=<?php echo $com['id_comissao']; ?>" class="btn-3d bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-1.5 px-3 rounded-lg text-xs inline-flex items-center gap-1">
                                        <i class="fas fa-edit"></i> Conciliar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="7" class="py-8 text-center text-slate-400 italic">Nenhum lançamento de comissão encontrado para os filtros informados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<!-- Modal de Filtros do Relatório -->
<div id="modalRelatorio" class="fixed inset-0 bg-slate-900 bg-opacity-50 hidden backdrop-blur-sm justify-center items-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-md w-full mx-4 border border-slate-100 transform transition-all">
        <div class="flex items-center gap-3 text-indigo-600 mb-4">
            <i class="fa-solid fa-print text-2xl"></i>
            <h3 class="text-lg font-bold text-slate-800">Filtros do Relatório</h3>
        </div>
        <p class="text-xs text-slate-500 mb-4">Selecione os parâmetros abaixo para gerar o relatório de comissões para impressão.</p>
        
        <form action="comissao_impressao.php" method="GET" target="_blank" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Empreendimento</label>
                <select name="rep_emp" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm">
                    <option value="0">Todos</option>
                    <?php foreach($filtros_empreendimentos as $item) echo "<option value='{$item['id_empreendimento']}'>{$item['nome_empreendimento']}</option>"; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Construtora</label>
                <select name="rep_const" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm">
                    <option value="0">Todas</option>
                    <?php foreach($filtros_construtoras as $item) echo "<option value='{$item['id_construtora']}'>{$item['nome_fantasia']}</option>"; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Corretor</label>
                <select id="rep_corr" name="rep_corr" onchange="ajustarFiltrosStatus()" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm">
                    <option value="0">Nenhum (Visão Imobiliária)</option>
                    <?php foreach($filtros_corretores as $item) echo "<option value='{$item['id_usuario']}'>{$item['nome']}</option>"; ?>
                </select>
            </div>

            <!-- Período de Emissão -->
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Emissão De</label>
                    <input type="date" name="rep_data_de" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Até</label>
                    <input type="date" name="rep_data_ate" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-lg text-sm">
                </div>
            </div>

            <!-- Situação Dinâmica via JavaScript -->
            <div>
                <label id="label_status" class="block text-xs font-bold text-slate-600 uppercase mb-1">Situação dos Recebimentos</label>
                <select id="rep_status" name="rep_status" class="w-full p-2.5 bg-white border border-slate-300 rounded-lg text-sm">
                    <!-- Opções padrão para Imobiliária -->
                    <option value="ambas">Ambas (Recebidas e Em Aberto)</option>
                    <option value="pago">Recebidas</option>
                    <option value="aberto">Em Aberto</option>
                </select>
            </div>

            <div class="flex justify-end gap-2 mt-6 pt-4 border-t">
                <button type="button" onclick="fecharModalRelatorio()" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold px-4 py-2 rounded-lg text-xs transition-colors">Cancelar</button>
                <button type="submit" class="btn-3d bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-4 py-2 rounded-lg text-xs">Gerar Impressão</button>
            </div>
        </form>
    </div>
</div>

<script>
    function abrirModalRelatorio() {
        document.getElementById('modalRelatorio').style.display = 'flex';
    }
    function fecharModalRelatorio() {
        document.getElementById('modalRelatorio').style.display = 'none';
    }

    // Altera as labels e opções dinamicamente se um corretor for selecionado
    function ajustarFiltrosStatus() {
        const corretorId = document.getElementById('rep_corr').value;
        const label = document.getElementById('label_status');
        const selectStatus = document.getElementById('rep_status');
        
        // Reseta as opções
        selectStatus.innerHTML = '';

        if (corretorId !== "0") {
            // Contexto focado no Corretor (Pagos vs Aberto)
            label.innerText = "Situação dos Repasses ao Corretor";
            selectStatus.options.add(new Option("Ambas (Pagas e Em Aberto)", "ambas"));
            selectStatus.options.add(new Option("Comissões Pagas", "pago"));
            selectStatus.options.add(new Option("Comissões Em Aberto", "aberto"));
        } else {
            // Contexto focado na Imobiliária (Recebidas vs Aberto)
            label.innerText = "Situação dos Recebimentos";
            selectStatus.options.add(new Option("Ambas (Recebidas e Em Aberto)", "ambas"));
            selectStatus.options.add(new Option("Recebidas", "pago"));
            selectStatus.options.add(new Option("Em Aberto", "aberto"));
        }
    }
</script>

</body>
</html>