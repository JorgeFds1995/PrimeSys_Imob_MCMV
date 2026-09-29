<?php
session_start();
require_once 'Database.php';

// PROTEÇÃO DA PÁGINA: Se não houver sessão ativa, manda de volta pro login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Função rápida para criar uma rota de logout simples
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$db = new Database();

// --- CAPTURA DE VARIÁVEIS DE ACESSO E TEMPO ---
$id_usuario_logado = intval($_SESSION['usuario_id']);
$is_master         = (bool)$_SESSION['is_master'];

// Definições de Data Baseadas no Horário Atual (Ano Vigente: 2026)
$data_hoje   = date('Y-m-d');
$data_ini_mes = date('Y-m-01');
$data_fim_mes = date('Y-m-t');
$data_10_dias = date('Y-m-d', strtotime('-10 days'));

// Cláusula de restrição: Se não for master, trava estritamente as tabelas de clientes no corretor logado
$trava_corretor = !$is_master ? " AND id_corretor = $id_usuario_logado" : "";

// --- 1) CARD: EMPREENDIMENTOS ATIVOS (Visão Geral da Empresa) ---
$sql_emp = "SELECT COUNT(*) as total FROM empreendimentos WHERE ativo = 1";
$res_emp = $db->Query($sql_emp);
$total_empreendimentos_ativos = !empty($res_emp) ? $res_emp[0]['total'] : 0;

// --- 2) CARD: VENDAS NO MÊS ---
$sql_vendas = "SELECT COUNT(*) as total FROM clientes 
               WHERE status_lead IN ('Venda Fechada', 'Desligamento', 'Nota Anexada', 'Comissão Recebida') 
               AND data_ultima_mov BETWEEN '$data_ini_mes' AND '$data_hoje'" . $trava_corretor;
$res_vendas = $db->Query($sql_vendas);
$total_vendas_mes = !empty($res_vendas) ? $res_vendas[0]['total'] : 0;

// --- 3) CARD: NOVOS LEADS ---
$sql_leads = "SELECT COUNT(*) as total FROM clientes 
              WHERE data_cadastro BETWEEN '$data_10_dias' AND '$data_hoje'" . $trava_corretor;
$res_leads = $db->Query($sql_leads);
$total_novos_leads = !empty($res_leads) ? $res_leads[0]['total'] : 0;

// --- 4) CARD: VGV MENSAL ---
$sql_vgv = "SELECT SUM(vgv) as total_vgv FROM clientes 
            WHERE data_venda BETWEEN '$data_ini_mes' AND '$data_fim_mes'" . $trava_corretor;
$res_vgv = $db->Query($sql_vgv);
$total_vgv_mensal = (!empty($res_vgv) && $res_vgv[0]['total_vgv'] !== null) ? floatval($res_vgv[0]['total_vgv']) : 0.00;

// Formatação do VGV para exibição elegante abreviada ou por extenso
if ($total_vgv_mensal >= 1000000) {
    $vgv_exibicao = "R$ " . number_format($total_vgv_mensal / 1000000, 1, ',', '.') . "M";
} else {
    $vgv_exibicao = "R$ " . number_format($total_vgv_mensal, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - PrimeSys Imob</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .bg-topbar { background-color: #ffffff; }
        .bg-sidebar { background-color: #1e293b; }
        .bg-sidebar-hover:hover { background-color: #334155; }
        .bg-submenu { background-color: #0f172a; }
        
        .card-3d {
            transition: transform 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }
        .card-3d:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
        }
        
        .menu-tree {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.25s ease-out;
        }
        .menu-tree.open {
            max-height: 250px;
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
<body class="bg-slate-100 min-h-screen font-sans antialiased flex flex-col">

    <header class="bg-topbar h-16 flex items-center justify-between px-6 border-b border-slate-200 shadow-sm z-20 select-none">
        <div class="flex items-center gap-2">
            <img src="primesys_imob_logo.png" alt="Logo Primesys Imob" width="30" height="30">
            <span class="text-xl font-black text-indigo-600 tracking-tight">PrimeSys <span class="text-slate-800 font-bold">Imob</span></span>
        </div>
        
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2.5 text-sm font-semibold text-slate-700">
                <div class="w-8 h-8 rounded-full overflow-hidden bg-slate-200 border border-slate-300 shadow-inner">
                    <?php if (!empty($_SESSION['usuario_foto'])): ?>
                        <img src="data:image/jpeg;base64,<?php echo base64_encode($_SESSION['usuario_foto']); ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" class="w-full h-full object-cover opacity-70">
                    <?php endif; ?>
                </div>
                <span><?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></span>
            </div>
            <a href="index.php?logout=1" class="text-slate-400 hover:text-red-500 text-sm font-medium flex items-center gap-1 transition-colors" title="Sair do sistema">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Sair
            </a>
        </div>
    </header>

    <div class="flex flex-1 relative">
        
        <aside class="bg-sidebar w-64 flex flex-col text-slate-300 min-h-full shadow-xl z-10 select-none">
            <nav class="flex-1 py-4 space-y-1">
                
                <a href="index.php" class="flex items-center gap-3 px-6 py-3 bg-sidebar-hover text-white font-medium border-l-4 border-indigo-500 transition-all">
                    <i class="fa-solid fa-chart-pie w-5 text-center text-sm text-indigo-400"></i>
                    <span>Dashboard</span>
                </a>
                
                <?php if ($_SESSION['is_master']): ?>
                  <a href="construtoras_lista.php" class="flex items-center gap-3 px-6 py-3 bg-sidebar-hover hover:text-white transition-colors">
                  <i class="fa-solid fa-industry w-5 text-center text-sm text-indigo-400"></i>
                  <span>Construtoras Parceiras</span>
                  </a>
                  
                  <a href="empreendimentos_lista.php" class="flex items-center gap-3 px-6 py-3 bg-sidebar-hover hover:text-white transition-colors">
                  <i class="fa-solid fa-building w-5 text-center text-sm text-indigo-400"></i>
                  <span>Cadastro de Empreendimentos</span>
                  </a>
                <?php endif; ?>

                <?php if ($_SESSION['is_corretor'] || $_SESSION['is_master']): ?>             
                    <div>
                        <button type="button" onclick="toggleMenuTree(this)" class="w-full flex items-center justify-between px-6 py-3 bg-sidebar-hover hover:text-white transition-colors focus:outline-none">
                            <div class="flex items-center gap-3">
                                <i class="fa-solid fa-address-book w-5 text-center text-sm text-indigo-400"></i>
                                <span>Clientes & Leads</span>
                            </div>
                            <i class="fa-solid fa-chevron-down text-xs transition-transform duration-200 arrow-icon"></i>
                        </button>
                        <div class="menu-tree bg-submenu flex flex-col">
                            <a href="clientes_lista.php" class="flex items-center gap-3 pl-14 pr-6 py-2.5 text-sm hover:text-white hover:bg-slate-800/50 transition-colors">
                                <i class="fa-solid fa-brands fa-trello text-xs text-indigo-400"></i>
                                <span>Kanban</span>
                            </a>
                            <a href="clientes_lista_02.php" class="flex items-center gap-3 pl-14 pr-6 py-2.5 text-sm hover:text-white hover:bg-slate-800/50 transition-colors">
                                <i class="fa-solid fa-solid fa-list text-xs text-indigo-400"></i>
                                <span>Lista</span>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($_SESSION['is_financeiro'] || $_SESSION['is_master']): ?>
                    <a href="comissoes_lista.php" class="flex items-center gap-3 px-6 py-3 bg-sidebar-hover hover:text-white transition-colors">
                        <i class="fa-solid fa-receipt w-5 text-center text-sm text-indigo-400"></i>
                        <span>Comissões</span>
                    </a>
                    
                    <div>
                        <button type="button" onclick="toggleMenuTree(this)" class="w-full flex items-center justify-between px-6 py-3 bg-sidebar-hover hover:text-white transition-colors focus:outline-none">
                            <div class="flex items-center gap-3">
                                <i class="fa-solid fa-sack-dollar w-5 text-center text-sm text-indigo-400"></i>
                                <span>Financeiro</span>
                            </div>
                            <i class="fa-solid fa-chevron-down text-xs transition-transform duration-200 arrow-icon"></i>
                        </button>
                        <div class="menu-tree bg-submenu flex flex-col">

                            <a href="fluxo_caixa.php" class="flex items-center gap-3 pl-14 pr-6 py-2.5 text-sm hover:text-white hover:bg-slate-800/50 transition-colors">
                                <i class="fa-solid fa-scale-unbalanced-flip text-xs text-indigo-400"></i>
                                <span>Fluxo de Caixa</span>
                            </a>
                        
                            <a href="financeiro_lista.php" class="flex items-center gap-3 pl-14 pr-6 py-2.5 text-sm hover:text-white hover:bg-slate-800/50 transition-colors">
                                <i class="fa-solid fa-money-bill-transfer text-xs text-indigo-400"></i>
                                <span>Contas a Pagar / Receber</span>
                            </a>
                            
                            <a href="extrato_bancario.php" class="flex items-center gap-3 pl-14 pr-6 py-2.5 text-sm hover:text-white hover:bg-slate-800/50 transition-colors">
                                <i class="fa-solid fa-receipt text-xs text-indigo-400"></i>
                                <span>Extrato Bancário</span>
                            </a>
                            
                            <a href="relatorio_resultado.php" class="flex items-center gap-3 pl-14 pr-6 py-2.5 text-sm hover:text-white hover:bg-slate-800/50 transition-colors">
                                <i class="fa-solid fa-chart-line text-xs text-indigo-400"></i>
                                <span>Resultado Mensal</span>
                            </a>
                            
                            <a href="bancos_lista.php" class="flex items-center gap-3 pl-14 pr-6 py-2.5 text-sm hover:text-white hover:bg-slate-800/50 transition-colors">
                                <i class="fa-solid fa-coins text-xs text-indigo-400"></i>
                                <span>Bancos</span>
                            </a>
                        </div>
                    </div>
                    
                <?php endif; ?>

                <?php if ($_SESSION['is_master']): ?>
                    <div>
                        <button type="button" onclick="toggleMenuTree(this)" class="w-full flex items-center justify-between px-6 py-3 bg-sidebar-hover hover:text-white transition-colors focus:outline-none">
                            <div class="flex items-center gap-3">
                                <i class="fa-solid fa-gear w-5 text-center text-sm text-indigo-400"></i>
                                <span>Configurações</span>
                            </div>
                            <i class="fa-solid fa-chevron-down text-xs transition-transform duration-200 arrow-icon"></i>
                        </button>
                        <div class="menu-tree bg-submenu flex flex-col">
                            <a href="usuarios_lista.php" class="flex items-center gap-3 pl-14 pr-6 py-2.5 text-sm hover:text-white hover:bg-slate-800/50 transition-colors">
                                <i class="fa-solid fa-users text-xs text-indigo-400"></i>
                                <span>Usuários</span>
                            </a>
                            <a href="bancos_lista.php" class="flex items-center gap-3 pl-14 pr-6 py-2.5 text-sm hover:text-white hover:bg-slate-800/50 transition-colors">
                                <i class="fa-solid fa-building-columns text-xs text-indigo-400"></i>
                                <span>Bancos (Instituições)</span>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

            </nav>
            
            <div class="p-4 border-t border-slate-700/40 text-center text-xs text-slate-500 font-medium">
                PrimeSys Imob &copy; 2026
            </div>
        </aside>

        <main class="flex-1 p-8 overflow-y-auto">
            <div class="mb-8">
                <h2 class="text-3xl font-extrabold text-slate-800 tracking-tight">Painel de Vendas</h2>
                <p class="text-sm text-slate-500 mt-1">Olá, <?php echo explode(' ', $_SESSION['usuario_nome'])[0]; ?>! Veja as atividades do sistema.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                
                <!-- 1) CARD: EMPREENDIMENTOS ATIVOS -->
                <div class="card-3d bg-white p-6 rounded-2xl border border-slate-200 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Empreendimentos Ativos</span>
                        <h3 class="text-3xl font-black text-slate-800 mt-1"><?php echo $total_empreendimentos_ativos; ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600">
                        <i class="fa-solid fa-building text-xl"></i>
                    </div>
                </div>

                <!-- 2) CARD: VENDAS NO MÊS -->
                <div class="card-3d bg-white p-6 rounded-2xl border border-slate-200 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Vendas no Mês</span>
                        <h3 class="text-3xl font-black text-slate-800 mt-1"><?php echo $total_vendas_mes; ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600">
                        <i class="fa-solid fa-handshake text-xl"></i>
                    </div>
                </div>

                <!-- 3) CARD: NOVOS LEADS -->
                <div class="card-3d bg-white p-6 rounded-2xl border border-slate-200 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Novos Leads</span>
                        <h3 class="text-3xl font-black text-slate-800 mt-1"><?php echo $total_novos_leads; ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-amber-50 flex items-center justify-center text-amber-500">
                        <i class="fa-solid fa-users-viewfinder text-xl"></i>
                    </div>
                </div>

                <!-- 4) CARD: VGV MENSAL -->
                <div class="card-3d bg-white p-6 rounded-2xl border border-slate-200 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">VGV Mensal</span>
                        <h3 class="text-2xl font-black text-slate-800 mt-2"><?php echo $vgv_exibicao; ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-purple-50 flex items-center justify-center text-purple-600">
                        <i class="fa-solid fa-sack-dollar text-xl"></i>
                    </div>
                </div>
                
            </div>
        </main>
    </div>

    <script>
        function toggleMenuTree(buttonElement) {
            const submenu = buttonElement.nextElementSibling;
            const arrow = buttonElement.querySelector('.arrow-icon');
            submenu.classList.toggle('open');
            arrow.style.transform = submenu.classList.contains('open') ? 'rotate(180deg)' : 'rotate(0deg)';
        }
    </script>
</body>
</html>