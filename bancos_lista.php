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

$sql = "SELECT * FROM BANCOS WHERE 1=1";
if (!empty($busca_escapada)) {
    $sql .= " AND DESC_BANCO LIKE '%$busca_escapada%'";
}
$sql .= " ORDER BY DESC_BANCO ASC";

$bancos = $db->Query($sql);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Contas Bancárias - PrimeSys Imob</title>
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
<body class="bg-slate-100 min-h-screen font-sans antialiased text-slate-800">

    <div class="max-w-5xl mx-auto py-10 px-4">
        
        <?php if(isset($_GET['msg']) && $_GET['msg'] === 'cadastrado'): ?>
            <div class="mb-6 p-4 rounded-lg bg-green-500 text-white shadow-md flex items-center gap-2">
                <i class="fas fa-check-circle text-xl"></i>
                <span>Nova instituição financeira cadastrada com sucesso!</span>
            </div>
        <?php endif; ?>

        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">
                    <i class="fa-solid fa-building-columns text-indigo-600 mr-2"></i>
                    Contas Bancárias / Instituições
                </h1>
                <p class="text-sm text-slate-500 mt-1">Gerencie as contas integradas para movimentações contábeis do caixa imobiliário.</p>
            </div>
            <a href="banco.php" class="btn-3d bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-5 rounded-lg flex items-center justify-center gap-2 self-start md:self-auto">
                <i class="fas fa-plus"></i> Nova Conta
            </a>
        </div>

        <!-- Filtro -->
        <div class="bg-white p-4 rounded-2xl shadow-md border border-slate-200 mb-6 max-w-xl">
            <form action="" method="GET" class="flex gap-3">
                <div class="relative flex-1">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" name="busca" value="<?php echo htmlspecialchars($busca); ?>" placeholder="Buscar por descrição do banco..." class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800 text-sm">
                </div>
                <button type="submit" class="btn-3d bg-slate-700 hover:bg-slate-800 text-white font-bold px-5 py-2.5 rounded-xl text-sm">Filtrar</button>
                <?php if (!empty($busca)): ?>
                    <a href="bancos_lista.php" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-sm flex items-center justify-center">Limpar</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Tabela -->
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 text-xs font-bold uppercase tracking-wider">
                            <th class="py-4 px-6 w-16 text-center">ID</th>
                            <th class="py-4 px-6">Descrição / Nome da Conta</th>
                            <th class="py-4 px-6 text-right w-48">Saldo Inicial</th>
                            <th class="py-4 px-6 text-center w-32">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 text-sm text-slate-700">
                        <?php if (!empty($bancos)): ?>
                            <?php foreach ($bancos as $bco): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-4 px-6 text-center font-mono text-xs text-slate-400">
                                        #<?php echo $bco['ID_BANCO']; ?>
                                    </td>
                                    <td class="py-4 px-6">
                                        <div class="font-bold text-slate-800 text-base flex items-center gap-2">
                                            <i class="fa-solid fa-wallet text-slate-400 text-sm"></i>
                                            <?php echo htmlspecialchars($bco['DESC_BANCO']); ?>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6 text-right font-black text-slate-800">
                                        R$ <?php echo number_format($bco['SALDO_INICIAL'], 2, ',', '.'); ?>
                                    </td>
                                    <td class="py-4 px-6 text-center">
                                        <a href="banco.php?id=<?php echo $bco['ID_BANCO']; ?>" class="btn-3d bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg text-xs inline-flex items-center gap-1.5">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="py-12 text-center text-slate-400 italic">
                                    <i class="fa-regular fa-folder-open text-3xl block mb-2 text-slate-300"></i>
                                    Nenhuma conta bancária cadastrada ou encontrada.
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