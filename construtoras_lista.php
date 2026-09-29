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

// Busca por Nome Fantasia ou CNPJ bruto
if (!empty($busca_escapada)) {
    $sql = "SELECT id_construtora, nome_fantasia, razao_social, cnpj, telefone1, email, gerente_responsavel 
            FROM construtoras 
            WHERE nome_fantasia LIKE '%$busca_escapada%' OR cnpj LIKE '%$busca_escapada%'
            ORDER BY nome_fantasia ASC";
} else {
    $sql = "SELECT id_construtora, nome_fantasia, razao_social, cnpj, telefone1, email, gerente_responsavel 
            FROM construtoras 
            ORDER BY nome_fantasia ASC";
}

$construtoras = $db->Query($sql);

// Funções auxiliares simples de máscara apenas para exibição em tela
function formatarCnpj($val) {
    if(strlen($val) === 14) {
        return substr($val,0,2).'.'.substr($val,2,3).'.'.substr($val,5,3).'/'.substr($val,8,4).'-'.substr($val,12,2);
    }
    return $val;
}
function formatarFone($val) {
    if(strlen($val) === 11) {
        return "(".substr($val,0,2).") ".substr($val,2,5)."-".substr($val,7,4);
    } elseif(strlen($val) === 10) {
        return "(".substr($val,0,2).") ".substr($val,2,4)."-".substr($val,6,4);
    }
    return $val;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Construtoras - PrimeSys Imob</title>
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
                <span>Nova construtora cadastrada e vinculada à imobiliária!</span>
            </div>
        <?php endif; ?>

        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">
                    <i class="fa-solid fa-industry text-indigo-600 mr-2"></i>
                    Construtoras Parceiras
                </h1>
                <p class="text-sm text-slate-500 mt-1">Gerencie a listagem e os canais de contato com incorporadoras.</p>
            </div>
            <a href="construtora.php" class="btn-3d bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-5 rounded-lg flex items-center justify-center gap-2 self-start md:self-auto">
                <i class="fas fa-plus"></i> Nova Construtora
            </a>
        </div>

        <div class="bg-white p-4 rounded-2xl shadow-md border border-slate-200 mb-6">
            <form action="" method="GET" class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" name="busca" value="<?php echo htmlspecialchars($busca); ?>" placeholder="Buscar por Nome Fantasia ou CNPJ..." class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn-3d bg-slate-700 hover:bg-slate-800 text-white font-bold px-5 py-2.5 rounded-xl text-sm">Filtrar</button>
                    <?php if (!empty($busca)): ?>
                        <a href="construtoras_lista.php" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-sm flex items-center justify-center">Limpar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 text-xs font-bold uppercase tracking-wider">
                            <th class="py-4 px-6">Nome / Razão Social</th>
                            <th class="py-4 px-6">CNPJ</th>
                            <th class="py-4 px-6">Contato</th>
                            <th class="py-4 px-6">Gerente</th>
                            <th class="py-4 px-6 text-center w-32">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 text-sm text-slate-700">
                        <?php if (!empty($construtoras)): ?>
                            <?php foreach ($construtoras as $const): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-4 px-6">
                                        <div class="font-bold text-slate-800 text-base"><?php echo htmlspecialchars($const['nome_fantasia']); ?></div>
                                        <div class="text-xs text-slate-400 mt-0.5"><?php echo htmlspecialchars($const['razao_social']); ?></div>
                                    </td>
                                    <td class="py-4 px-6 font-mono text-xs text-slate-600">
                                        <?php echo !empty($const['cnpj']) ? formatarCnpj($const['cnpj']) : '<span class="text-slate-400 italic">Não cadastrado</span>'; ?>
                                    </td>
                                    <td class="py-4 px-6">
                                        <div class="flex items-center gap-1.5 text-slate-600 font-medium">
                                            <i class="fa-solid fa-phone text-xs text-indigo-400"></i>
                                            <?php echo !empty($const['telefone1']) ? formatarFone($const['telefone1']) : '<span class="text-slate-400 text-xs italic">Sem fone</span>'; ?>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-xs text-slate-500 mt-1">
                                            <i class="fa-regular fa-envelope text-indigo-400"></i>
                                            <?php echo !empty($const['email']) ? htmlspecialchars($const['email']) : '<span class="text-slate-400 italic">Sem e-mail</span>'; ?>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6 text-slate-600 font-medium">
                                        <?php echo !empty($const['gerente_responsavel']) ? htmlspecialchars($const['gerente_responsavel']) : '<span class="text-slate-400 text-xs italic">Não informado</span>'; ?>
                                    </td>
                                    <td class="py-4 px-6 text-center whitespace-nowrap">
                                        <a href="construtora.php?id=<?php echo $const['id_construtora']; ?>" class="btn-3d bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg text-xs inline-flex items-center gap-1.5">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="py-12 text-center text-slate-400 italic">
                                    <i class="fa-regular fa-folder-open text-3xl block mb-2 text-slate-300"></i>
                                    Nenhuma construtora parceira foi encontrada.
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