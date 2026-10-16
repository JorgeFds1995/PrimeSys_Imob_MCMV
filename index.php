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

// Verifica se o usuário é corretor (coluna 'corretor' na tabela 'usuarios')
$res_user_info = $db->Query("SELECT corretor FROM usuarios WHERE id_usuario = $id_usuario_logado LIMIT 1");
$is_corretor_user = (!empty($res_user_info) && ($res_user_info[0]['corretor'] == 1 || $res_user_info[0]['corretor'] === true));

// Gestão de Alternância de Visão (para Master que também é corretor)
// Se for master, pode alternar via GET ('visao=corretor' ou 'visao=master'). Padrão para master é 'master', para corretor puro é 'corretor'.
if ($is_master) {
    if (isset($_GET['visao'])) {
        $_SESSION['visao_atual'] = ($_GET['visao'] === 'corretor') ? 'corretor' : 'master';
    } elseif (!isset($_SESSION['visao_atual'])) {
        $_SESSION['visao_atual'] = 'master';
    }
    $modo_visao = $_SESSION['visao_atual'];
} else {
    $modo_visao = 'corretor';
}

// Processamento do formulário de Distribuição de Leads (Modal)
$msg_distribuicao = '';
$erro_distribuicao = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_distribuir_lead'])) {
    if ($is_master) {
        $nome_lead = str_replace("'", "''", trim($_POST['nome'] ?? ''));
        $telefone1 = preg_replace('/\D/', '', $_POST['telefone'] ?? '');
        $id_empreendimento = !empty($_POST['id_empreendimento']) ? intval($_POST['id_empreendimento']) : 0;
        
        if (!empty($nome_lead) && !empty($telefone1) && $id_empreendimento > 0) {
            // VALIDAÇÃO: Verifica se já existe um lead na tabela clientes com o mesmo telefone1
            $check_tel = $db->Query("SELECT c.id_cliente, u.nome as nome_corretor_existente 
                                     FROM clientes c 
                                     LEFT JOIN usuarios u ON c.id_corretor = u.id_usuario 
                                     WHERE c.telefone1 = '$telefone1' LIMIT 1");
            
            if (!empty($check_tel)) {
                $nome_corretor_resp = !empty($check_tel[0]['nome_corretor_existente']) ? $check_tel[0]['nome_corretor_existente'] : 'outro corretor';
                $erro_distribuicao = "Este lead já está cadastrado e sob responsabilidade de " . $nome_corretor_resp . ".";
            } else {
                // 1. Organizar os corretores (tabela usuarios em que a coluna corretor estiver como true por id_usuario)
                $corretores = $db->Query("SELECT id_usuario FROM usuarios WHERE corretor = 1 OR corretor = true ORDER BY id_usuario ASC");
                
                if (!empty($corretores)) {
                    // 2. Verificar na tabela distribuicao_leads na coluna ultimo_corretor qual o último id que recebeu lead
                    $tabela_dist = $db->Query("SELECT ultimo_corretor FROM distribuicao_leads LIMIT 1");
                    $ultimo_id = !empty($tabela_dist) ? intval($tabela_dist[0]['ultimo_corretor']) : 0;
                    
                    // Encontrar o índice do próximo corretor a partir do último
                    $proximo_corretor_id = 0;
                    $encontrou_indice = false;
                    
                    // Primeira tentativa: encontrar o próximo após o último_id
                    foreach ($corretores as $corr) {
                        if ($encontrou_indice) {
                            $proximo_corretor_id = intval($corr['id_usuario']);
                            break;
                        }
                        if (intval($corr['id_usuario']) === $ultimo_id) {
                            $encontrou_indice = true;
                        }
                    }
                    
                    // Se chegou ao fim da lista ou não encontrou, pega o primeiro da lista (volta ao ciclo)
                    if ($proximo_corretor_id === 0 && !empty($corretores)) {
                        $proximo_corretor_id = intval($corretores[0]['id_usuario']);
                    }
                    
                    // 3. Validação: se o corretor que era pra receber o lead, tem algum lead pertencente a ele na tabela clientes com a coluna status_lead igual a 'Lead', pula ele e vai pro próximo.
                    $candidatos = [];
                    // Reordena a lista de corretores começando a partir do $proximo_corretor_id
                    $lista_ordenada = [];
                    $passou = false;
                    foreach ($corretores as $corr) {
                        $uid = intval($corr['id_usuario']);
                        if ($uid === $proximo_corretor_id) {
                            $passou = true;
                        }
                        if ($passou) {
                            $lista_ordenada[] = $uid;
                        }
                    }
                    // Adiciona o restante do início até antes do proximo_corretor_id para completar o ciclo circular
                    foreach ($corretores as $corr) {
                        $uid = intval($corr['id_usuario']);
                        if ($uid === $proximo_corretor_id) {
                            break;
                        }
                        $lista_ordenada[] = $uid;
                    }
                    
                    $corretor_escolhido = 0;
                    foreach ($lista_ordenada as $uid_cand) {
                        // Verifica se tem algum lead com status_lead = 'Lead' para este corretor
                        
                        $data_hoje_sistema = date('Y-m-d');
                        
                        $leads_pendentes = $db->Query("SELECT id_cliente FROM clientes WHERE id_corretor = $uid_cand AND status_lead = 'Lead' AND data_cadastro < '$data_hoje_sistema' LIMIT 1");
                        if (empty($leads_pendentes)) {
                            // Não tem lead travado em 'Lead', pode receber!
                            $corretor_escolhido = $uid_cand;
                            break;
                        }
                    }
                    
                    // Se todos os corretores tiverem leads com status 'Lead' (fallback de segurança), pega o próximo da fila original mesmo
                    if ($corretor_escolhido === 0) {
                        $corretor_escolhido = $proximo_corretor_id;
                    }
                    
                    // 4. Inserir o lead na tabela clientes preenchendo as colunas nome, telefone1, id_empreendimento e id_corretor
                    $data_hoje = date('Y-m-d');
                    $sql_ins = "INSERT INTO clientes (nome, telefone1, id_empreendimento, id_corretor, status_lead, origem_lead, data_cadastro, data_ultima_mov) 
                                VALUES ('$nome_lead', '$telefone1', $id_empreendimento, $corretor_escolhido, 'Lead', 'Distribuição Automática', '$data_hoje', '$data_hoje')";
                    $db->Query($sql_ins);
                    
                    // 5. Ajustar o id na tabela distribuicao_leads
                    if (empty($tabela_dist)) {
                        $db->Query("INSERT INTO distribuicao_leads (ultimo_corretor) VALUES ($corretor_escolhido)");
                    } else {
                        $db->Query("UPDATE distribuicao_leads SET ultimo_corretor = $corretor_escolhido");
                    }
                    
                    // 6. Verificar se o corretor possui e-mail cadastrado e disparar notificação via PHPMailer
                    $info_corretor_email = $db->Query("SELECT nome, email FROM usuarios WHERE id_usuario = $corretor_escolhido LIMIT 1");
                    if (!empty($info_corretor_email)) {
                        $nome_corretor_atribuido = $info_corretor_email[0]['nome'];
                        $email_corretor_atribuido = $info_corretor_email[0]['email'];
                        
                        if (!empty($email_corretor_atribuido)) {
                            require_once 'php/enviar.php';
                            if (function_exists('enviarEmailNovoLead')) {
                                enviarEmailNovoLead($email_corretor_atribuido, $nome_corretor_atribuido);
                            }
                        }
                    }
                    
                    header("Location: index.php?msg_dist=sucesso");
                    exit;
                }
            }
        }
    }
}

// Carregar empreendimentos ativos para o combobox do modal de distribuição
$empreendimentos_ativos_modal = $db->Query("SELECT id_empreendimento, nome_empreendimento FROM empreendimentos WHERE ativo = 1 ORDER BY nome_empreendimento ASC");

// Definições de Data Baseadas no Horário Atual (Ano Vigente: 2026)
$data_hoje   = date('Y-m-d');
$data_ini_mes = date('Y-m-01');
$data_fim_mes = date('Y-m-t');
$data_10_dias = date('Y-m-d', strtotime('-10 days'));

// Cláusula de restrição: Se a visão atual for corretor, restringe estritamente ao usuário logado
$trava_corretor = ($modo_visao === 'corretor') ? " AND id_corretor = $id_usuario_logado" : "";

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

// --- DADOS PARA OS GRÁFICOS / BI ---
$top5_empreendimentos = [];
$vgv_por_corretor = [];
$leads_estagnados = [];
$vgv_por_empreendimento_corretor = [];
$leads_estagnados_corretor = [];
$top5_origens_leads = [];
$funil_etapas_corretor = [];

if ($modo_visao === 'master') {
    // 1. Top 5 Empreendimentos com mais leads
    $top5_empreendimentos = $db->Query("SELECT e.nome_empreendimento, COUNT(c.id_cliente) as total_leads 
                                        FROM empreendimentos e 
                                        LEFT JOIN clientes c ON e.id_empreendimento = c.id_empreendimento 
                                        GROUP BY e.id_empreendimento, e.nome_empreendimento 
                                        ORDER BY total_leads DESC LIMIT 5");

    // 2. Composição do VGV por Corretor (Mês atual)
    $vgv_por_corretor = $db->Query("SELECT u.nome as corretor, SUM(c.vgv) as total_vgv 
                                    FROM clientes c 
                                    JOIN usuarios u ON c.id_corretor = u.id_usuario 
                                    WHERE c.data_venda BETWEEN '$data_ini_mes' AND '$data_fim_mes' AND c.vgv > 0 
                                    GROUP BY u.id_usuario, u.nome 
                                    ORDER BY total_vgv DESC");

    // 3. Corretores com leads sem movimentar a mais de 5 dias
    $data_limite_inativo = date('Y-m-d', strtotime('-5 days'));
    $leads_estagnados = $db->Query("SELECT u.nome as corretor, c.nome as cliente, c.telefone1, c.data_ultima_mov, 
                                    DATEDIFF('$data_hoje', c.data_ultima_mov) as dias_parado 
                                    FROM clientes c 
                                    JOIN usuarios u ON c.id_corretor = u.id_usuario 
                                    WHERE c.status_lead NOT IN ('Venda Fechada', 'Desligamento', 'Perdido', 'Comissão Recebida') 
                                    AND c.data_ultima_mov < '$data_limite_inativo' 
                                    ORDER BY dias_parado DESC");

    // 4. Top 5 Origens de Lead + Outros (Soma) da tabela clientes
    $todas_origens = $db->Query("SELECT 
                                    COALESCE(NULLIF(TRIM(origem_lead), ''), 'Não Informado') as origem, 
                                    COUNT(*) as total 
                                 FROM clientes 
                                 GROUP BY origem 
                                 ORDER BY total DESC");

    if (!empty($todas_origens)) {
        $contador_origens = 0;
        $soma_outros = 0;
        foreach ($todas_origens as $orig) {
            if ($contador_origens < 5) {
                $top5_origens_leads[] = [
                    'origem' => $orig['origem'],
                    'total' => intval($orig['total'])
                ];
            } else {
                $soma_outros += intval($orig['total']);
            }
            $contador_origens++;
        }
        if ($soma_outros > 0 || count($todas_origens) > 5) {
            $top5_origens_leads[] = [
                'origem' => 'Outros (Soma)',
                'total' => $soma_outros
            ];
        }
    }
} else {
    // Visão Corretor: Composição do VGV do mês atual por empreendimento para o usuário logado
    $vgv_por_empreendimento_corretor = $db->Query("SELECT e.nome_empreendimento, SUM(c.vgv) as total_vgv 
                                                   FROM clientes c 
                                                   JOIN empreendimentos e ON c.id_empreendimento = e.id_empreendimento 
                                                   WHERE c.id_corretor = $id_usuario_logado 
                                                   AND c.data_venda BETWEEN '$data_ini_mes' AND '$data_fim_mes' 
                                                   AND c.vgv > 0 
                                                   GROUP BY e.id_empreendimento, e.nome_empreendimento 
                                                   ORDER BY total_vgv DESC");

    // Visão Corretor: Leads do usuário logado sem movimentar a mais de 5 dias
    $data_limite_inativo = date('Y-m-d', strtotime('-5 days'));
    $leads_estagnados_corretor = $db->Query("SELECT c.nome as cliente, c.telefone1, c.data_ultima_mov, 
                                             DATEDIFF('$data_hoje', c.data_ultima_mov) as dias_parado 
                                             FROM clientes c 
                                             WHERE c.id_corretor = $id_usuario_logado 
                                             AND c.status_lead NOT IN ('Venda Fechada', 'Desligamento', 'Perdido', 'Comissão Recebida') 
                                             AND c.data_ultima_mov < '$data_limite_inativo' 
                                             ORDER BY dias_parado DESC");

    // Visão Corretor: Quantidade de leads por etapa (status_lead) para o gráfico de funil vertical
    $funil_etapas_corretor = $db->Query("SELECT 
                                            COALESCE(NULLIF(TRIM(status_lead), ''), 'Não Definido') as etapa, 
                                            COUNT(*) as total 
                                         FROM clientes 
                                         WHERE id_corretor = $id_usuario_logado 
                                         GROUP BY etapa 
                                         ORDER BY total DESC");
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
    <!-- Chart.js para gráficos profissionais -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .btn-3d { transition: all 0.1s ease-in-out; box-shadow: 0 4px 0 0 rgba(0, 0, 0, 0.15); }
        .btn-3d:active { transform: translateY(4px); box-shadow: 0 0px 0 0 rgba(0, 0, 0, 0.15); }

        /* Simulação elegante de efeito 3D em pizza chart Container */
        .pie-3d-container {
            position: relative;
            filter: drop-shadow(0 10px 8px rgba(0, 0, 0, 0.12));
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
            <?php if ($is_master): ?>
                <!-- Botão de Alternância de Visão para Master que também possui acesso/perfil -->
                <div class="bg-slate-100 p-1 rounded-xl border border-slate-200 flex items-center gap-1">
                    <a href="index.php?visao=master" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all <?php echo ($modo_visao === 'master') ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-600 hover:text-slate-900'; ?>">
                        <i class="fa-solid fa-shield-halved mr-1"></i> Visão Master
                    </a>
                    <a href="index.php?visao=corretor" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all <?php echo ($modo_visao === 'corretor') ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-600 hover:text-slate-900'; ?>">
                        <i class="fa-solid fa-user-tie mr-1"></i> Visão Corretor
                    </a>
                </div>
            <?php endif; ?>

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
                            <?php if ($_SESSION['is_master']): ?>
                            <a href="#" onclick="abrirModalDistribuirLeads(event)" class="flex items-center gap-3 pl-14 pr-6 py-2.5 text-sm hover:text-white hover:bg-slate-800/50 transition-colors">
                                <i class="fa-solid fa-share-nodes text-xs text-indigo-400"></i>
                                <span>Distribuir Leads</span>
                            </a>
                            <?php endif; ?>
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
            <?php if(isset($_GET['msg_dist']) && $_GET['msg_dist'] === 'sucesso'): ?>
                <div class="mb-6 p-4 rounded-lg bg-green-500 text-white shadow-md flex items-center gap-2">
                    <i class="fas fa-check-circle text-xl"></i>
                    <span>Lead distribuído e cadastrado com sucesso para o próximo corretor elegível!</span>
                </div>
            <?php endif; ?>

            <?php if(!empty($erro_distribuicao)): ?>
                <div class="mb-6 p-4 rounded-lg bg-red-500 text-white shadow-md flex items-center gap-2">
                    <i class="fas fa-exclamation-triangle text-xl"></i>
                    <span><?php echo htmlspecialchars($erro_distribuicao); ?></span>
                </div>
            <?php endif; ?>

            <div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-3xl font-extrabold text-slate-800 tracking-tight">Painel de Vendas</h2>
                    <p class="text-sm text-slate-500 mt-1">Olá, <?php echo explode(' ', $_SESSION['usuario_nome'])[0]; ?>! Veja as atividades do sistema.</p>
                </div>
                <?php if ($is_master): ?>
                    <div>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold <?php echo ($modo_visao === 'master') ? 'bg-purple-100 text-purple-700 border border-purple-200' : 'bg-indigo-100 text-indigo-700 border border-indigo-200'; ?>">
                            <i class="fa-solid <?php echo ($modo_visao === 'master') ? 'fa-shield-halved' : 'fa-user-tie'; ?>"></i>
                            Modo Ativo: <?php echo ($modo_visao === 'master') ? 'Visão Master (Geral)' : 'Visão Corretor (Pessoal)'; ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                
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

            <!-- SEÇÃO DE INTELIGÊNCIA DE BI E GRÁFICOS PROFISSIONAIS (EXCLUSIVO PARA MASTER) -->
            <?php if ($modo_visao === 'master'): ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                
                <!-- GRÁFICO 1: Top 5 Empreendimentos com mais leads (Barras Horizontais) -->
                <div class="card-3d bg-white p-6 rounded-2xl border border-slate-200 flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                                <i class="fa-solid fa-chart-bar"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-extrabold text-slate-800">Top 5 Empreendimentos (Leads)</h3>
                                <p class="text-xs text-slate-400">Empreendimentos com maior volume de prospecção</p>
                            </div>
                        </div>
                        <span class="text-xs font-bold px-2.5 py-1 bg-indigo-50 text-indigo-600 rounded-full">Ranking</span>
                    </div>
                    <div class="relative h-64 w-full flex items-center justify-center">
                        <canvas id="topEmpreendimentosChart"></canvas>
                    </div>
                </div>

                <!-- GRÁFICO 2: Composição do VGV por Corretor (Pizza estilo 3D) -->
                <div class="card-3d bg-white p-6 rounded-2xl border border-slate-200 flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-purple-50 flex items-center justify-center text-purple-600">
                                <i class="fa-solid fa-chart-pie"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-extrabold text-slate-800">Composição do VGV por Corretor</h3>
                                <p class="text-xs text-slate-400">Participação nas vendas fechadas do mês atual</p>
                            </div>
                        </div>
                        <span class="text-xs font-bold px-2.5 py-1 bg-purple-50 text-purple-600 rounded-full">B.I. Vendas</span>
                    </div>
                    <div class="relative h-64 w-full flex items-center justify-center pie-3d-container">
                        <canvas id="vgvCorretorChart"></canvas>
                    </div>
                </div>

            </div>

            <!-- NOVO GRÁFICO / CARD: Top 5 Origens de Lead + Outros (Soma) (Visão Master) -->
            <div class="grid grid-cols-1 lg:grid-cols-1 gap-6 mb-8">
                <div class="card-3d bg-white p-6 rounded-2xl border border-slate-200 flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-teal-50 flex items-center justify-center text-teal-600">
                                <i class="fa-solid fa-bullhorn"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-extrabold text-slate-800">Top 5 Origens de Leads</h3>
                                <p class="text-xs text-slate-400">Canais de aquisição com maior volume de leads (Demais somados em Outros)</p>
                            </div>
                        </div>
                        <span class="text-xs font-bold px-2.5 py-1 bg-teal-50 text-teal-600 rounded-full">Marketing</span>
                    </div>
                    <div class="relative h-64 w-full flex items-center justify-center">
                        <canvas id="topOrigensLeadsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- TABELA BI: Corretores com Leads sem movimentar a mais de 5 dias -->
            <div class="card-3d bg-white rounded-2xl border border-slate-200 overflow-hidden mb-8">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-slate-800">Leads Estagnados (> 5 Dias Sem Movimentação)</h3>
                            <p class="text-xs text-slate-400">Atenção gerencial para carteiras com baixa interatividade recente</p>
                        </div>
                    </div>
                    <span class="text-xs font-bold px-3 py-1 bg-amber-100 text-amber-800 rounded-full">
                        <?php echo count($leads_estagnados); ?> Alertas
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 text-xs font-bold uppercase tracking-wider">
                                <th class="py-3 px-6">Corretor Responsável</th>
                                <th class="py-3 px-6">Lead / Cliente</th>
                                <th class="py-3 px-6">Contato</th>
                                <th class="py-3 px-6 text-center">Última Movimentação</th>
                                <th class="py-3 px-6 text-center">Dias Parado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-sm text-slate-700">
                            <?php if (!empty($leads_estagnados)): ?>
                                <?php foreach ($leads_estagnados as $estagnado): ?>
                                    <tr class="hover:bg-slate-50/80 transition-colors">
                                        <td class="py-3 px-6 font-bold text-slate-800">
                                            <i class="fa-regular fa-user mr-1 text-indigo-500"></i>
                                            <?php echo htmlspecialchars($estagnado['corretor']); ?>
                                        </td>
                                        <td class="py-3 px-6 font-medium text-slate-800">
                                            <?php echo htmlspecialchars($estagnado['cliente']); ?>
                                        </td>
                                        <td class="py-3 px-6 text-slate-600">
                                            <i class="fa-solid fa-phone text-xs text-indigo-400 mr-1"></i>
                                            <?php echo !empty($estagnado['telefone1']) ? htmlspecialchars($estagnado['telefone1']) : '<span class="text-slate-400 italic text-xs">Não informado</span>'; ?>
                                        </td>
                                        <td class="py-3 px-6 text-center text-slate-500 text-xs font-mono">
                                            <?php echo date('d/m/Y', strtotime($estagnado['data_ultima_mov'])); ?>
                                        </td>
                                        <td class="py-3 px-6 text-center whitespace-nowrap">
                                            <span class="px-2.5 py-1 bg-red-100 text-red-700 font-bold rounded-lg text-xs">
                                                <?php echo $estagnado['dias_parado']; ?> dias
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-10 text-center text-slate-400 italic">
                                        <i class="fa-regular fa-face-smile text-3xl block mb-2 text-slate-300"></i>
                                        Nenhum lead estagnado há mais de 5 dias no momento. Excelente ritmo de atendimento!
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- SEÇÃO DE INTELIGÊNCIA DE BI E GRÁFICOS (VISÃO CORRETOR) -->
            <?php if ($modo_visao === 'corretor'): ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- GRÁFICO 1: Composição do VGV do Mês Atual por Empreendimento (Pizza 3D) -->
                <div class="card-3d bg-white p-6 rounded-2xl border border-slate-200 flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-purple-50 flex items-center justify-center text-purple-600">
                                <i class="fa-solid fa-chart-pie"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-extrabold text-slate-800">Composição do Seu VGV por Empreendimento (Mês Atual)</h3>
                                <p class="text-xs text-slate-400">Distribuição das suas vendas fechadas entre os empreendimentos</p>
                            </div>
                        </div>
                        <span class="text-xs font-bold px-2.5 py-1 bg-purple-50 text-purple-600 rounded-full">Meu Desempenho</span>
                    </div>
                    <div class="relative h-72 w-full flex items-center justify-center pie-3d-container">
                        <canvas id="vgvEmpreendimentoCorretorChart"></canvas>
                    </div>
                </div>

                <!-- GRÁFICO 2: Funil Vertical de Leads por Etapa (Visão Corretor) -->
                <div class="card-3d bg-white p-6 rounded-2xl border border-slate-200 flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                                <i class="fa-solid fa-filter"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-extrabold text-slate-800">Funil de Leads por Etapa</h3>
                                <p class="text-xs text-slate-400">Quantidade de leads na sua carteira divididos por status</p>
                            </div>
                        </div>
                        <span class="text-xs font-bold px-2.5 py-1 bg-indigo-50 text-indigo-600 rounded-full">Funil Vertical</span>
                    </div>
                    <div class="relative h-72 w-full flex items-center justify-center">
                        <canvas id="funilEtapasCorretorChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- TABELA: Seus Leads sem movimentar a mais de 5 dias -->
            <div class="card-3d bg-white rounded-2xl border border-slate-200 overflow-hidden mb-8">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-slate-800">Seus Leads Estagnados (> 5 Dias Sem Movimentação)</h3>
                            <p class="text-xs text-slate-400">Leads da sua carteira que necessitam de contato ou atualização recente</p>
                        </div>
                    </div>
                    <span class="text-xs font-bold px-3 py-1 bg-amber-100 text-amber-800 rounded-full">
                        <?php echo count($leads_estagnados_corretor); ?> Alertas
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 text-xs font-bold uppercase tracking-wider">
                                <th class="py-3 px-6">Lead / Cliente</th>
                                <th class="py-3 px-6">Contato</th>
                                <th class="py-3 px-6 text-center">Última Movimentação</th>
                                <th class="py-3 px-6 text-center">Dias Parado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-sm text-slate-700">
                            <?php if (!empty($leads_estagnados_corretor)): ?>
                                <?php foreach ($leads_estagnados_corretor as $estagnado): ?>
                                    <tr class="hover:bg-slate-50/80 transition-colors">
                                        <td class="py-3 px-6 font-medium text-slate-800">
                                            <?php echo htmlspecialchars($estagnado['cliente']); ?>
                                        </td>
                                        <td class="py-3 px-6 text-slate-600">
                                            <i class="fa-solid fa-phone text-xs text-indigo-400 mr-1"></i>
                                            <?php echo !empty($estagnado['telefone1']) ? htmlspecialchars($estagnado['telefone1']) : '<span class="text-slate-400 italic text-xs">Não informado</span>'; ?>
                                        </td>
                                        <td class="py-3 px-6 text-center text-slate-500 text-xs font-mono">
                                            <?php echo date('d/m/Y', strtotime($estagnado['data_ultima_mov'])); ?>
                                        </td>
                                        <td class="py-3 px-6 text-center whitespace-nowrap">
                                            <span class="px-2.5 py-1 bg-red-100 text-red-700 font-bold rounded-lg text-xs">
                                                <?php echo $estagnado['dias_parado']; ?> dias
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="py-10 text-center text-slate-400 italic">
                                        <i class="fa-regular fa-face-smile text-3xl block mb-2 text-slate-300"></i>
                                        Parabéns! Você não tem nenhum lead estagnado há mais de 5 dias.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- MODAL DE DISTRIBUIR LEADS -->
    <div id="modalDistribuirLeads" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-lg p-6 relative animate-in fade-in zoom-in duration-150">
            
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 font-bold">
                        <i class="fa-solid fa-share-nodes text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-extrabold text-slate-800">Distribuir Novo Lead</h3>
                        <p class="text-xs text-slate-500">Atribuição inteligente e rotativa entre os corretores ativos.</p>
                    </div>
                </div>
                <button type="button" onclick="fecharModalDistribuirLeads()" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="index.php" method="POST" class="space-y-4">
                <input type="hidden" name="acao_distribuir_lead" value="1">

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nome do Lead</label>
                    <input type="text" name="nome" required placeholder="Digite o nome completo do cliente..." class="w-full p-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm text-slate-800">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Telefone Principal</label>
                    <input type="text" id="tel_distribuicao" name="telefone" required placeholder="(00) 00000-0000" class="w-full p-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm text-slate-800">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Empreendimento de Interesse</label>
                    <select name="id_empreendimento" required class="w-full p-2.5 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm text-slate-800">
                        <option value="">-- Selecione o Empreendimento Ativo --</option>
                        <?php if (!empty($empreendimentos_ativos_modal)): ?>
                            <?php foreach ($empreendimentos_ativos_modal as $emp): ?>
                                <option value="<?php echo $emp['id_empreendimento']; ?>"><?php echo htmlspecialchars($emp['nome_empreendimento']); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                    <button type="button" onclick="fecharModalDistribuirLeads()" class="px-5 py-2.5 rounded-xl text-sm font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors">
                        Cancelar
                    </button>
                    <button type="submit" class="btn-3d bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold px-6 py-2.5 rounded-xl flex items-center gap-2">
                        <i class="fas fa-save"></i> Salvar e Distribuir
                    </button>
                </div>
            </form>

        </div>
    </div>

    <script src="https://unpkg.com/imask"></script>
    <script>
        function toggleMenuTree(buttonElement) {
            const submenu = buttonElement.nextElementSibling;
            const arrow = buttonElement.querySelector('.arrow-icon');
            submenu.classList.toggle('open');
            arrow.style.transform = submenu.classList.contains('open') ? 'rotate(180deg)' : 'rotate(0deg)';
        }

        function abrirModalDistribuirLeads(event) {
            if (event) event.preventDefault();
            document.getElementById('modalDistribuirLeads').classList.remove('hidden');
        }

        function fecharModalDistribuirLeads() {
            document.getElementById('modalDistribuirLeads').classList.add('hidden');
        }

        // Máscara para o telefone no modal
        if (document.getElementById('tel_distribuicao')) {
            IMask(document.getElementById('tel_distribuicao'), { mask: [{ mask: '(00) 0000-0000' }, { mask: '(00) 00000-0000' }] });
        }

        <?php if ($modo_visao === 'master'): ?>
        // --- SCRIPTS DE RENDERIZAÇÃO DOS GRÁFICOS (CHART.JS - MASTER) ---
        
        // 1. Gráfico de Barras Horizontais (Top 5 Empreendimentos)
        const topEmpData = {
            labels: [
                <?php 
                if (!empty($top5_empreendimentos)) {
                    foreach($top5_empreendimentos as $item) {
                        echo "'" . addslashes($item['nome_empreendimento']) . "',";
                    }
                }
                ?>
            ],
            datasets: [{
                label: 'Total de Leads',
                data: [
                    <?php 
                    if (!empty($top5_empreendimentos)) {
                        foreach($top5_empreendimentos as $item) {
                            echo intval($item['total_leads']) . ",";
                        }
                    }
                    ?>
                ],
                backgroundColor: 'rgba(79, 70, 229, 0.85)',
                borderColor: 'rgba(79, 70, 229, 1)',
                borderWidth: 1,
                borderRadius: 6,
                barThickness: 22
            }]
        };

        const ctxTopEmp = document.getElementById('topEmpreendimentosChart').getContext('2d');
        new Chart(ctxTopEmp, {
            type: 'bar',
            data: topEmpData,
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        padding: 10,
                        titleFont: { size: 13, weight: 'bold' },
                        bodyFont: { size: 12 }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: 'rgba(226, 232, 240, 0.6)' },
                        ticks: { precision: 0 }
                    },
                    y: {
                        grid: { display: false }
                    }
                }
            }
        });

        // 2. Gráfico de Pizza 3D (Composição do VGV por Corretor)
        const vgvData = {
            labels: [
                <?php 
                if (!empty($vgv_por_corretor)) {
                    foreach($vgv_por_corretor as $item) {
                        echo "'" . addslashes($item['corretor']) . "',";
                    }
                }
                ?>
            ],
            datasets: [{
                data: [
                    <?php 
                    if (!empty($vgv_por_corretor)) {
                        foreach($vgv_por_corretor as $item) {
                            echo floatval($item['total_vgv']) . ",";
                        }
                    }
                    ?>
                ],
                backgroundColor: [
                    'rgba(79, 70, 229, 0.9)',
                    'rgba(168, 85, 247, 0.9)',
                    'rgba(14, 165, 233, 0.9)',
                    'rgba(16, 185, 129, 0.9)',
                    'rgba(245, 158, 11, 0.9)',
                    'rgba(239, 68, 68, 0.9)'
                ],
                borderWidth: 2,
                borderColor: '#ffffff',
                hoverOffset: 6
            }]
        };

        const ctxVgv = document.getElementById('vgvCorretorChart').getContext('2d');
        new Chart(ctxVgv, {
            type: 'pie',
            data: vgvData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            font: { size: 11, weight: '600' }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        padding: 10,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                let value = context.parsed || 0;
                                let formatted = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
                                return ' ' + label + ': ' + formatted;
                            }
                        }
                    }
                }
            }
        });

        // 3. Gráfico de Barras (Top 5 Origens de Leads + Outros)
        const topOrigensData = {
            labels: [
                <?php 
                if (!empty($top5_origens_leads)) {
                    foreach($top5_origens_leads as $item) {
                        echo "'" . addslashes($item['origem']) . "',";
                    }
                }
                ?>
            ],
            datasets: [{
                label: 'Total de Leads',
                data: [
                    <?php 
                    if (!empty($top5_origens_leads)) {
                        foreach($top5_origens_leads as $item) {
                            echo intval($item['total']) . ",";
                        }
                    }
                    ?>
                ],
                backgroundColor: 'rgba(13, 148, 136, 0.85)',
                borderColor: 'rgba(13, 148, 136, 1)',
                borderWidth: 1,
                borderRadius: 6,
                barThickness: 22
            }]
        };

        const ctxTopOrigens = document.getElementById('topOrigensLeadsChart').getContext('2d');
        new Chart(ctxTopOrigens, {
            type: 'bar',
            data: topOrigensData,
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        padding: 10,
                        titleFont: { size: 13, weight: 'bold' },
                        bodyFont: { size: 12 }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: 'rgba(226, 232, 240, 0.6)' },
                        ticks: { precision: 0 }
                    },
                    y: {
                        grid: { display: false }
                    }
                }
            }
        });
        <?php endif; ?>

        <?php if ($modo_visao === 'corretor'): ?>
        // --- SCRIPTS DE RENDERIZAÇÃO DOS GRÁFICOS (CHART.JS - CORRETOR) ---
        const vgvEmpCorretorData = {
            labels: [
                <?php 
                if (!empty($vgv_por_empreendimento_corretor)) {
                    foreach($vgv_por_empreendimento_corretor as $item) {
                        echo "'" . addslashes($item['nome_empreendimento']) . "',";
                    }
                }
                ?>
            ],
            datasets: [{
                data: [
                    <?php 
                    if (!empty($vgv_por_empreendimento_corretor)) {
                        foreach($vgv_por_empreendimento_corretor as $item) {
                            echo floatval($item['total_vgv']) . ",";
                        }
                    }
                    ?>
                ],
                backgroundColor: [
                    'rgba(79, 70, 229, 0.9)',
                    'rgba(14, 165, 233, 0.9)',
                    'rgba(16, 185, 129, 0.9)',
                    'rgba(245, 158, 11, 0.9)',
                    'rgba(168, 85, 247, 0.9)',
                    'rgba(239, 68, 68, 0.9)'
                ],
                borderWidth: 2,
                borderColor: '#ffffff',
                hoverOffset: 6
            }]
        };

        const ctxVgvEmpCorretor = document.getElementById('vgvEmpreendimentoCorretorChart').getContext('2d');
        new Chart(ctxVgvEmpCorretor, {
            type: 'pie',
            data: vgvEmpCorretorData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            font: { size: 11, weight: '600' }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        padding: 10,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                let value = context.parsed || 0;
                                let formatted = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
                                return ' ' + label + ': ' + formatted;
                            }
                        }
                    }
                }
            }
        });

        // Gráfico de Funil Vertical (Barreiras / Barras Verticais por Etapa) para a Visão Corretor
        const funilEtapasData = {
            labels: [
                <?php 
                if (!empty($funil_etapas_corretor)) {
                    foreach($funil_etapas_corretor as $item) {
                        echo "'" . addslashes($item['etapa']) . "',";
                    }
                }
                ?>
            ],
            datasets: [{
                label: 'Quantidade de Leads',
                data: [
                    <?php 
                    if (!empty($funil_etapas_corretor)) {
                        foreach($funil_etapas_corretor as $item) {
                            echo intval($item['total']) . ",";
                        }
                    }
                    ?>
                ],
                backgroundColor: 'rgba(79, 70, 229, 0.85)',
                borderColor: 'rgba(79, 70, 229, 1)',
                borderWidth: 1,
                borderRadius: 6,
                barThickness: 28
            }]
        };

        const ctxFunilEtapas = document.getElementById('funilEtapasCorretorChart').getContext('2d');
        new Chart(ctxFunilEtapas, {
            type: 'bar',
            data: funilEtapasData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        padding: 10,
                        titleFont: { size: 13, weight: 'bold' },
                        bodyFont: { size: 12 }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11, weight: '600' } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(226, 232, 240, 0.6)' },
                        ticks: { precision: 0 }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>