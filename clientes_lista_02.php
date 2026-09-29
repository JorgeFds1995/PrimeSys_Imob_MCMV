<?php
session_start();
require_once 'Database.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$db = new Database();

$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$busca_escapada = str_replace("'", "''", $busca);

// Captura o ID do usuário da sessão
$id_usuario_logado = intval($_SESSION['usuario_id']);

// =========================================================================
// AJUSTE DEFINITIVO: Busca a coluna correta 'master_user' do seu banco
// =========================================================================
$usuario_db = $db->Query("SELECT master_user FROM usuarios WHERE id_usuario = $id_usuario_logado");

$is_master_real = false; // Padrão seguro: assume que NÃO é master

if (!empty($usuario_db) && isset($usuario_db[0]['master_user'])) {
    $valor_master = $usuario_db[0]['master_user'];
    
    // Se master_user for 1, '1', true ou 'true', ele vira master.
    if ($valor_master === true || $valor_master === 1 || $valor_master === '1' || strtolower((string)$valor_master) === 'true') {
        $is_master_real = true;
    }
}

// Atualiza a sessão interna usando o nome que você preferir usar no sistema
$_SESSION['is_master'] = $is_master_real;
// =========================================================================

// 1. BASE DA QUERY (Traz o cliente e o nome do seu corretor)
$sql = "SELECT c.id_cliente, c.nome, c.cpf, c.telefone1, c.status_lead, c.origem_lead, u.nome as nome_corretor 
        FROM clientes c
        LEFT JOIN usuarios u ON c.id_corretor = u.id_usuario";

// 2. FILTRO DE PRIVACIDADE: Agora vai respeitar o 'master_user = 0' do banco
if (!$is_master_real) {
    // Se NÃO for usuário master, restringe estritamente aos leads dele
    $sql .= " WHERE c.id_corretor = $id_usuario_logado";
    $sql .= " AND (c.id_corretor = $id_usuario_logado OR c.id_corretor = 0 OR c.status_lead = 'Perdido' )";
    
    // Se o corretor comum também digitar algo na barra de pesquisa
    if (!empty($busca_escapada)) {
        $sql .= " AND (c.nome LIKE '%$busca_escapada%' OR c.cpf LIKE '%$busca_escapada%')";
    }
} else {
    // Se for MASTER (master_user = 1), ele vê tudo
    if (!empty($busca_escapada)) {
        $sql .= " WHERE (c.nome LIKE '%$busca_escapada%' OR c.cpf LIKE '%$busca_escapada%')";
    }
}

$sql .= " ORDER BY c.status_lead DESC";
$clientes = $db->Query($sql);

function formatCpf($v) {
    return (strlen($v) === 11) ? substr($v,0,3).'.'.substr($v,3,3).'.'.substr($v,6,3).'-'.substr($v,9,2) : $v;
}
function formatFone($v) {
    return (strlen($v) === 11) ? "(".substr($v,0,2).") ".substr($v,2,5)."-".substr($v,7,4) : ((strlen($v)===10)?"(".substr($v,0,2).") ".substr($v,2,4)."-".substr($v,6,4):$v);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pipeline de Clientes/Leads - PrimeSys Imob</title>
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

    <div class="max-w-6xl mx-auto py-10 px-4">
        
        <?php if(isset($_GET['msg']) && $_GET['msg'] === 'cadastrado'): ?>
            <div class="mb-6 p-4 rounded-lg bg-green-500 text-white shadow-md flex items-center gap-2">
                <i class="fas fa-check-circle text-xl"></i>
                <span>Novo Lead capturado e distribuído com sucesso no funil!</span>
            </div>
        <?php endif; ?>

        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">
                    <i class="fa-solid fa-users-viewfinder text-indigo-600 mr-2"></i>
                    Clientes & Leads (Funil de Vendas)
                </h1>
                <p class="text-sm text-slate-500 mt-1">Acompanhe a triagem e evolução das propostas comerciais da imobiliária.</p>
            </div>
            <a href="cliente.php" class="btn-3d bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-5 rounded-lg flex items-center justify-center gap-2 self-start md:self-auto">
                <i class="fas fa-plus"></i> Capturar Novo Lead
            </a>
        </div>

        <!-- Filtro/Busca Rápida -->
        <!-- CONTAINER PAI COM FLEXBOX -->
        <div class="flex items-start gap-4 mb-6">
        
           <!-- Primeira DIV (Formulário) -->
           <div class="bg-white p-4 rounded-2xl shadow-md border border-slate-200 select-none max-w-xl flex-1">
              <form action="" method="GET" class="flex gap-3">
                    <div class="relative flex-1">
                       <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                          <i class="fas fa-search"></i>
                       </span>
                       <input type="text" name="busca" value="<?php echo htmlspecialchars($busca); ?>" placeholder="Buscar lead por nome ou CPF..." class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800 text-sm">
                    </div>
                    <button type="submit" class="btn-3d bg-slate-700 hover:bg-slate-800 text-white font-bold px-5 py-2.5 rounded-xl text-sm">Filtrar</button>
                    <?php if (!empty($busca)): ?>
                       <a href="clientes_lista_02.php" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-sm flex items-center justify-center">Limpar</a>
                    <?php endif; ?>
              </form>
           </div>
           
           <!-- Segunda DIV (Nova div ao lado) -->
           <div class="bg-white p-3 rounded-2xl shadow-md border border-slate-200 select-none max-w-xl flex-1">
              
              <!-- Container Pai alinhando os botões em linha -->
              <div class="flex items-center gap-2">
              
                 <a href="index.php" class="flex items-center gap-3 px-6 py-3 bg-sidebar-hover text-indigo-400 font-medium transition-all">
                    <i class="fa-solid fa-house w-5 text-center text-sm text-indigo-400"></i>
                    <span>Home</span>
                 </a>
                 
                 <a href="clientes_lista.php" class="flex items-center gap-3 px-6 py-3 bg-sidebar-hover text-indigo-400 font-medium transition-all">
                    <i class="fa-solid fa-brands fa-trello text-xs text-indigo-400"></i>
                    <span>Kanban</span>
                 </a>
                 
                 <a href="clientes_lista_02.php" class="flex items-center gap-3 px-6 py-3 bg-sidebar-hover text-indigo-400 font-medium transition-all">
                    <i class="fa-solid fa-solid fa-list text-xs text-indigo-400"></i>
                    <span>Lista</span>
                 </a>
              
              </div>
              
           </div>
        
        </div>

        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 text-xs font-bold uppercase tracking-wider">
                            <th class="py-4 px-6">Cliente / CPF</th>
                            <th class="py-4 px-6">Contato</th>
                            <th class="py-4 px-6">Canal / Origem</th>
                            <th class="py-4 px-6">Corretor Titular</th>
                            <th class="py-4 px-6 text-center w-36">Fase do Funil</th>
                            <th class="py-4 px-6 text-center w-32">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 text-sm text-slate-700">
                        <?php if (!empty($clientes)): ?>
                            <?php foreach ($clientes as $cli): 
                                // Mapeamento estético de cores para as principais fases do funil imobiliário
                                $corBadge = "bg-slate-100 text-slate-700";
                                if($cli['status_lead'] == 'Lead') $corBadge = "bg-blue-100 text-blue-800";
                                if($cli['status_lead'] == 'Visita' || $cli['status_lead'] == 'Negociação') $corBadge = "bg-amber-100 text-amber-800";
                                if($cli['status_lead'] == 'Análise Documento') $corBadge = "bg-purple-100 text-purple-800";
                                if($cli['status_lead'] == 'Venda Fechada' || $cli['status_lead'] == 'Comissão Recebida') $corBadge = "bg-emerald-100 text-emerald-800";
                                if($cli['status_lead'] == 'Perdido' || $cli['status_lead'] == 'Desligamento') $corBadge = "bg-red-100 text-red-800";
                            ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-4 px-6">
                                        <div class="font-bold text-slate-800 text-base"><?php echo htmlspecialchars($cli['nome']); ?></div>
                                        <div class="text-xs font-mono text-slate-400 mt-0.5"><?php echo !empty($cli['cpf']) ? formatCpf($cli['cpf']) : 'Sem CPF informado'; ?></div>
                                    </td>
                                    <td class="py-4 px-6 font-medium text-slate-600">
                                        <i class="fa-solid fa-phone text-xs text-indigo-400 mr-1"></i><?php echo !empty($cli['telefone1']) ? formatFone($cli['telefone1']) : '---'; ?>
                                    </td>
                                    <td class="py-4 px-6">
                                        <span class="text-xs font-bold px-2 py-0.5 rounded bg-slate-100 border text-slate-600"><?php echo htmlspecialchars($cli['origem_lead'] ?? 'Direto'); ?></span>
                                    </td>
                                    <td class="py-4 px-6 text-slate-600 font-semibold">
                                        <i class="fa-regular fa-circle-user text-xs mr-1 text-slate-400"></i><?php echo htmlspecialchars($cli['nome_corretor'] ?? 'Não distribuído'); ?>
                                    </td>
                                    <td class="py-4 px-6 text-center">
                                        <span class="px-3 py-1 text-xs font-extrabold rounded-full border <?php echo $corBadge; ?>">
                                            <?php echo htmlspecialchars($cli['status_lead']); ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-6 text-center">
                                        <a href="cliente.php?id=<?php echo $cli['id_cliente']; ?>" class="btn-3d bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg text-xs inline-flex items-center gap-1.5">
                                            <i class="fas fa-edit"></i> Ficha
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="py-12 text-center text-slate-400 italic">
                                    <i class="fa-regular fa-folder-open text-3xl block mb-2 text-slate-300"></i>
                                    Nenhum cliente ou lead registrado nesta imobiliária.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</body>
</html>