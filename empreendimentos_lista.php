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

// Query unindo empreendimentos e construtoras para exibir o nome fantasia da parceira
$sql = "SELECT e.id_empreendimento, e.nome_empreendimento, e.valor, e.tipo, e.tipo_imovel, e.area, e.ativo, c.nome_fantasia as incorporadora
        FROM empreendimentos e
        INNER JOIN construtoras c ON e.id_incorporadora = c.id_construtora";

if (!empty($busca_escapada)) {
    $sql .= " WHERE e.nome_empreendimento LIKE '%$busca_escapada%' OR c.nome_fantasia LIKE '%$busca_escapada%'";
}
$sql .= " ORDER BY e.id_empreendimento DESC";

$empreendimentos = $db->Query($sql);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Empreendimentos - PrimeSys Imob</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .btn-3d {
            transition: all 0.1s ease-in-out;
            box-shadow: 0 4px 0 0 rgba(0, 0, 0, 0.15);
        }
        .btn-3d:active {
            transform: translateY(4px);
            box-shadow: 0 0px 0 0 rgba(0, 0, 0, 0.15);
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
<body class="bg-slate-100 min-h-screen font-sans antialiased">

    <div class="max-w-6xl mx-auto py-10 px-4">
        
        <?php if(isset($_GET['msg']) && $_GET['msg'] === 'cadastrado'): ?>
            <div class="mb-6 p-4 rounded-lg bg-green-500 text-white shadow-md flex items-center gap-2">
                <i class="fas fa-check-circle text-xl"></i>
                <span>Novo empreendimento cadastrado e disponível para vendas!</span>
            </div>
        <?php endif; ?>

        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">
                    <i class="fa-solid fa-building text-indigo-600 mr-2"></i>
                    Empreendimentos Cadastrados
                </h1>
                <p class="text-sm text-slate-500 mt-1">Consulte o portfólio completo de edifícios, condomínios e loteamentos.</p>
            </div>
            <a href="empreendimento.php" class="btn-3d bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-5 rounded-lg flex items-center justify-center gap-2 self-start md:self-auto">
                <i class="fas fa-plus"></i> Novo Empreendimento
            </a>
        </div>

        <div class="bg-white p-4 rounded-2xl shadow-md border border-slate-200 mb-6">
            <form action="" method="GET" class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" name="busca" value="<?php echo htmlspecialchars($busca); ?>" placeholder="Buscar por Nome do Empreendimento ou Construtora..." class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn-3d bg-slate-700 hover:bg-slate-800 text-white font-bold px-5 py-2.5 rounded-xl text-sm">Filtrar</button>
                    <?php if (!empty($busca)): ?>
                        <a href="empreendimentos_lista.php" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-sm flex items-center justify-center">Limpar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 text-xs font-bold uppercase tracking-wider">
                            <th class="py-4 px-6">Empreendimento / Incorporadora</th>
                            <th class="py-4 px-6">Classificação</th>
                            <th class="py-4 px-6">Área Privativa</th>
                            <th class="py-4 px-6 text-right">Valor Inicial</th>
                            <th class="py-4 px-6 text-center w-32">Status</th>
                            <th class="py-4 px-6 text-center w-32">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 text-sm text-slate-700">
                        <?php if (!empty($empreendimentos)): ?>
                            <?php foreach ($empreendimentos as $emp): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-4 px-6">
                                        <div class="font-bold text-slate-800 text-base"><?php echo htmlspecialchars($emp['nome_empreendimento']); ?></div>
                                        <div class="text-xs text-slate-400 mt-0.5"><i class="fa-solid fa-industry text-xs mr-1"></i><?php echo htmlspecialchars($emp['incorporadora']); ?></div>
                                    </td>
                                    <td class="py-4 px-6 space-y-1">
                                        <span class="inline-block text-[11px] font-extrabold px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-100"><?php echo htmlspecialchars($emp['tipo_imovel']); ?></span>
                                        <span class="inline-block text-[11px] font-extrabold px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 border border-slate-200"><?php echo htmlspecialchars($emp['tipo']); ?></span>
                                    </td>
                                    <td class="py-4 px-6 font-semibold text-slate-600">
                                        <?php echo number_format($emp['area'], 2, ',', '.'); ?> m²
                                    </td>
                                    <td class="py-4 px-6 text-right font-black text-slate-800">
                                        R$ <?php echo number_format($emp['valor'], 2, ',', '.'); ?>
                                    </td>
                                    <td class="py-4 px-6 text-center">
                                        <?php if ($emp['ativo']): ?>
                                            <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-emerald-100 text-emerald-800">Ativo</span>
                                        <?php else: ?>
                                            <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-red-100 text-red-800">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-6 text-center">
                                        <a href="empreendimento.php?id=<?php echo $emp['id_empreendimento']; ?>" class="btn-3d bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg text-xs inline-flex items-center gap-1.5">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="py-12 text-center text-slate-400 italic">
                                    <i class="fa-regular fa-folder-open text-3xl block mb-2 text-slate-300"></i>
                                    Nenhum empreendimento cadastrado ou encontrado.
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