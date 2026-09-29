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

$id_usuario_logado = intval($_SESSION['usuario_id']);

// =========================================================================
// SEGURANÇA MÁXIMA: Consulta em tempo real na coluna correta 'master_user'
// =========================================================================
$usuario_db = $db->Query("SELECT master_user FROM usuarios WHERE id_usuario = $id_usuario_logado");

$is_master_real = false; // Padrão seguro

if (!empty($usuario_db) && isset($usuario_db[0]['master_user'])) {
    $valor_master = $usuario_db[0]['master_user'];
    if ($valor_master === true || $valor_master === 1 || $valor_master === '1' || strtolower((string)$valor_master) === 'true') {
        $is_master_real = true;
    }
}

// Sincroniza a sessão para o resto do sistema
$_SESSION['is_master'] = $is_master_real;
// =========================================================================

// Definição das colunas/fases oficiais do Funil Imobiliário
$fases_funil = ['Lead', 'Atendimento', 'Aguardando Documentos', 'Análise Documento', 'Negociação', 'Perdido', 'Venda Fechada', 'Desligamento', 'Nota Anexada', 'Comissão Recebida'];

// 1. BASE DA QUERY ATUALIZADA (Traz o cliente, o corretor e o empreendimento de interesse)
$sql = "SELECT c.id_cliente, c.nome, c.telefone1, c.status_lead, c.origem_lead, 
               u.nome as nome_corretor, e.nome_empreendimento
        FROM clientes c
        LEFT JOIN usuarios u ON c.id_corretor = u.id_usuario
        LEFT JOIN empreendimentos e ON c.id_empreendimento = e.id_empreendimento
        WHERE 1=1";

// 2. FILTRO DE PRIVACIDADE: Corretores comuns só enxergam seus próprios leads
if (!$is_master_real) {
    $sql .= " AND (c.id_corretor = $id_usuario_logado OR c.id_corretor = 0 OR c.status_lead = 'Perdido' )";
}

// 3. BARRA DE PESQUISA GLOBAL OU POR CORRETOR (Isolada com parênteses para não quebrar o AND anterior)
if (!empty($busca_escapada)) {
    $sql .= " AND (c.nome LIKE '%$busca_escapada%' OR c.cpf LIKE '%$busca_escapada%')";
}

$sql .= " ORDER BY c.id_cliente DESC";
$todos_clientes = $db->Query($sql);

// Organiza os clientes em arrays separados por fase do funil para montar as colunas
$quadro_kanban = array_fill_keys($fases_funil, []);
if (!empty($todos_clientes)) {
    foreach ($todos_clientes as $cli) {
        $status = $cli['status_lead'];
        if (array_key_exists($status, $quadro_kanban)) {
            $quadro_kanban[$status][] = $cli;
        } else {
            $quadro_kanban['Lead'][] = $cli; // Fallback de segurança
        }
    }
}

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
    <title>Pipeline Kanban - PrimeSys Imob</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SortableJS para o arrastar e soltar suave -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <style>
        .btn-3d { transition: all 0.1s ease-in-out; box-shadow: 0 4px 0 0 rgba(0, 0, 0, 0.15); }
        .btn-3d:active { transform: translateY(4px); box-shadow: 0 0px 0 0 rgba(0, 0, 0, 0.15); }
        /* Scrollbar customizada e fina para o quadro horizontal */
        .kanban-container::-webkit-scrollbar { height: 8px; }
        .kanban-container::-webkit-scrollbar-track { background: #f1f5f9; }
        .kanban-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .kanban-container::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
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
<body class="bg-slate-100 min-h-screen font-sans antialiased text-slate-800 flex flex-col">

    <div class="w-full flex-1 flex flex-col py-8 px-6">
        
        <!-- Cabeçalho -->
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4 select-none">
            <div>
                <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">
                    <i class="fa-solid fa-users-viewfinder text-indigo-600 mr-2"></i>
                    Quadro Kanban (Funil de Vendas)
                </h1>
                <p class="text-sm text-slate-500 mt-1">Arrastar os cards entre as baias atualiza o status do cliente e aciona gatilhos de faturamento automaticamente.</p>
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
                       <a href="clientes_lista.php" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-sm flex items-center justify-center">Limpar</a>
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

        <!-- Área do Quadro Kanban Horizontal -->
        <div class="flex-1 overflow-x-auto kanban-container pb-4">
            <div class="flex gap-4 items-start pr-4 h-full" style="width: max-content;">
                
                <?php foreach ($fases_funil as $fase): 
                    // Definição dinâmica de cores para os cabeçalhos das colunas
                    $corTopo = "border-t-indigo-500";
                    if($fase == 'Lead') $corTopo = "border-t-blue-500";
                    if($fase == 'Visita' || $fase == 'Negociação') $corTopo = "border-t-amber-500";
                    if($fase == 'Análise Documento') $corTopo = "border-t-purple-500";
                    if($fase == 'Venda Fechada' || $fase == 'Comissão Recebida') $corTopo = "border-t-emerald-500";
                    if($fase == 'Perdido' || $fase == 'Desligamento') $corTopo = "border-t-red-500";
                ?>
                    <!-- Coluna de Fase -->
                    <div class="bg-slate-200/70 w-72 rounded-2xl p-4 flex flex-col max-h-[calc(100vh-230px)] border-t-4 <?php echo $corTopo; ?> shadow-sm border border-x-slate-300/60 border-b-slate-300/60">
                        
                        <!-- Título da Coluna -->
                        <div class="flex items-center justify-between mb-3 select-none">
                            <span class="font-bold text-slate-700 text-sm truncate"><?php echo $fase; ?></span>
                            <span class="text-xs bg-slate-300 text-slate-600 font-bold px-2 py-0.5 rounded-full" id="contador-<?php echo md5($fase); ?>">
                                <?php echo count($quadro_kanban[$fase]); ?>
                            </span>
                        </div>

                        <!-- Lista de Cards (Área Draggável) -->
                        <div class="kanban-dropzone flex-1 overflow-y-auto space-y-3 pr-1" data-status="<?php echo htmlspecialchars($fase); ?>">
                            <?php if (!empty($quadro_kanban[$fase])): ?>
                                <?php foreach ($quadro_kanban[$fase] as $cli): ?>
                                    
                                    <!-- Card do Lead Atualizado com as Novas Informações -->
                                    <div class="bg-white p-4 rounded-xl shadow border border-slate-200 cursor-grab active:cursor-grabbing hover:border-indigo-400 transition-all card-3d relative" data-id="<?php echo $cli['id_cliente']; ?>">
                                        
                                        <!-- Nome do Cliente -->
                                        <div class="font-bold text-slate-800 text-sm tracking-tight line-clamp-1 mb-1" title="<?php echo htmlspecialchars($cli['nome']); ?>">
                                            <?php echo htmlspecialchars($cli['nome']); ?>
                                        </div>
                                        
                                        <!-- Telefone Formatado -->
                                        <div class="text-[11px] font-medium text-slate-600 flex items-center gap-1.5 mb-2">
                                            <i class="fa-solid fa-phone text-[10px] text-slate-400"></i>
                                            <?php echo !empty($cli['telefone1']) ? formatFone($cli['telefone1']) : '<span class="text-slate-400 italic">Sem telefone</span>'; ?>
                                        </div>
                                    
                                        <!-- Empreendimento de Interesse -->
                                        <div class="text-[11px] font-semibold text-indigo-600 bg-indigo-50/50 px-2 py-1 rounded-md border border-indigo-100/60 flex items-center gap-1 mb-2">
                                            <i class="fa-solid fa-building text-[10px] opacity-75"></i>
                                            <span class="truncate" title="Empreendimento de Interesse">
                                                <?php echo !empty($cli['nome_empreendimento']) ? htmlspecialchars($cli['nome_empreendimento']) : 'Interesse não definido'; ?>
                                            </span>
                                        </div>
                                        
                                        <!-- Rodapé do Card: Origem do Lead e Botão Ficha -->
                                        <div class="mt-2 pt-2 border-t border-slate-100 flex items-center justify-between">
                                            <!-- Origem do Lead -->
                                            <span class="text-[10px] font-extrabold px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200 text-slate-500 uppercase tracking-wide" title="Origem do Lead">
                                                <i class="fa-solid fa-arrow-up-right-from-square text-[9px] mr-1 opacity-60"></i><?php echo htmlspecialchars($cli['origem_lead'] ?? 'Direto'); ?>
                                            </span>
                                            
                                            <!-- Link para abrir a Ficha Completa -->
                                            <a href="cliente.php?id=<?php echo $cli['id_cliente']; ?>" class="bg-slate-100 hover:bg-indigo-50 text-indigo-600 font-bold px-2.5 py-1 rounded-md text-[11px] inline-flex items-center gap-1 border border-slate-200 hover:border-indigo-200 transition-colors">
                                                <i class="fas fa-edit text-[9px]"></i> Ficha
                                            </a>
                                        </div>
                                    </div>

                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                    </div>
                <?php endforeach; ?>

            </div>
        </div>

    </div>
    
    <!-- Script de Ativação do Kanban Corrigido para Caracteres Especiais e Acentuação -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const dropzones = document.querySelectorAll('.kanban-dropzone');

        dropzones.forEach(zone => {
            new Sortable(zone, {
                group: 'kanban-pipeline', 
                animation: 150,
                ghostClass: 'bg-indigo-50', 
                chosenClass: 'opacity-50',
                dragClass: 'shadow-2xl',
                
                onEnd: function (evt) {
                    const itemEl = evt.item; 
                    const idCliente = itemEl.getAttribute('data-id');
                    const novoStatus = evt.to.getAttribute('data-status');
                    const statusAntigo = evt.from.getAttribute('data-status');

                    // Se não mudou de coluna, não faz nada
                    if (statusAntigo === novoStatus) return;

                    // Atualiza as contagens numéricas nas baias
                    atualizarContadores(evt.from, evt.to);

                    // Envia os dados assincronamente via POST para salvar no MySQL
                    fetch('kanban_update.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `id_cliente=${idCliente}&status_lead=${encodeURIComponent(novoStatus)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.sucesso) {
                            alert('Erro ao salvar no banco de dados: ' + data.erro);
                            window.location.reload(); 
                        }
                    })
                    .catch(error => {
                        console.error('Erro de conexão com o servidor:', error);
                        window.location.reload();
                    });
                }
            });
        });

        // CORREÇÃO: Função simplificada que não usa btoa/criptografia e evita travamentos por acentuação
        function atualizarContadores(colunaOrigem, colunaDestino) {
            const painelOrigem = colunaOrigem.closest('.bg-slate-200\\/70');
            const painelDestino = colunaDestino.closest('.bg-slate-200\\/70');
            
            if (painelOrigem) {
                painelOrigem.querySelector('span[id^="contador-"]').innerText = colunaOrigem.children.length;
            }
            if (painelDestino) {
                painelDestino.querySelector('span[id^="contador-"]').innerText = colunaDestino.children.length;
            }
        }
    });
</script>
</body>
</html>