<?php
require_once 'Database.php'; // Certifique-se de que o arquivo da sua classe está com este nome

$db = new Database();

// Captura o termo de busca se houver
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$busca_escapada = str_replace("'", "''", $busca);

// Monta a query para listar os usuários (com ou sem filtro de busca)
if (!empty($busca_escapada)) {
    $sql = "SELECT id_usuario, foto, nome, login, telefone, email, endereco 
            FROM usuarios 
            WHERE nome LIKE '%$busca_escapada%' OR login LIKE '%$busca_escapada%'
            ORDER BY nome ASC";
} else {
    $sql = "SELECT id_usuario, foto, nome, login, telefone, email, endereco 
            FROM usuarios 
            ORDER BY nome ASC";
}

$usuarios = $db->Query($sql);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Usuários - PrimeSys Imob</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Mantendo o efeito 3D realçado nos botões da identidade visual */
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
        
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">
                    <i class="fa-solid fa-users text-indigo-600 mr-2"></i>
                    Usuários Cadastrados
                </h1>
                <p class="text-sm text-slate-500 mt-1">Consulte, edite ou altere as credenciais dos corretores e colaboradores do sistema.</p>
            </div>
            <a href="usuario.php" class="btn-3d bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-5 rounded-lg flex items-center justify-center gap-2 self-start md:self-auto">
                <i class="fas fa-plus"></i> Cadastrar Novo
            </a>
        </div>

        <div class="bg-white p-4 rounded-2xl shadow-md border border-slate-200 mb-6">
            <form action="" method="GET" class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" name="busca" value="<?php echo htmlspecialchars($busca); ?>" placeholder="Buscar por nome ou login..." class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn-3d bg-slate-700 hover:bg-slate-800 text-white font-bold px-5 py-2.5 rounded-xl text-sm">
                        Filtrar
                    </button>
                    <?php if (!empty($busca)): ?>
                        <a href="usuarios_lista.php" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-sm flex items-center justify-center">
                            Limpar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 text-xs font-bold uppercase tracking-wider">
                            <th class="py-4 px-6 text-center w-24">Foto</th>
                            <th class="py-4 px-6">Nome / Login</th>
                            <th class="py-4 px-6">Contato</th>
                            <th class="py-4 px-6">Endereço</th>
                            <th class="py-4 px-6 text-center w-40">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 text-sm text-slate-700">
                        <?php if (!empty($usuarios)): ?>
                            <?php foreach ($usuarios as $user): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    
                                    <td class="py-4 px-6 whitespace-nowrap text-center">
                                        <div class="inline-block w-12 h-12 rounded-full overflow-hidden ring-2 ring-indigo-500/30 shadow-inner bg-slate-100">
                                            <?php if (!empty($user['foto'])): ?>
                                                <img src="data:image/jpeg;base64,<?php echo base64_encode($user['foto']); ?>" class="w-full h-full object-cover" alt="Avatar">
                                            <?php else: ?>
                                                <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" class="w-full h-full object-cover opacity-60" alt="Padrão">
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <td class="py-4 px-6">
                                        <div class="font-bold text-slate-800 text-base"><?php echo htmlspecialchars($user['nome']); ?></div>
                                        <div class="text-xs text-slate-400 font-mono mt-0.5">
                                            <i class="fa-regular fa-user mr-1"></i><?php echo htmlspecialchars($user['login']); ?>
                                        </div>
                                    </td>
                                    
                                    <td class="py-4 px-6">
                                        <div class="flex items-center gap-1.5 text-slate-600 font-medium">
                                            <i class="fa-solid fa-phone text-xs text-indigo-400"></i>
                                            <?php echo !empty($user['telefone']) ? htmlspecialchars($user['telefone']) : '<span class="text-slate-400 text-xs italic">Não informado</span>'; ?>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-xs text-slate-500 mt-1">
                                            <i class="fa-regular fa-envelope text-indigo-400"></i>
                                            <?php echo !empty($user['email']) ? htmlspecialchars($user['email']) : '<span class="text-slate-400 italic">Não informado</span>'; ?>
                                        </div>
                                    </td>
                                    
                                    <td class="py-4 px-6 max-w-xs truncate">
                                        <span class="text-slate-600" title="<?php echo htmlspecialchars($user['endereco']); ?>">
                                            <?php echo !empty($user['endereco']) ? htmlspecialchars($user['endereco']) : '<span class="text-slate-400 text-xs italic">Não informado</span>'; ?>
                                        </span>
                                    </td>
                                    
                                    <td class="py-4 px-6 text-center whitespace-nowrap">
                                        <div class="flex items-center justify-center gap-2">
                                            <a href="usuario.php?id=<?php echo $user['id_usuario']; ?>" class="btn-3d bg-indigo-600 hover:bg-indigo-700 text-white font-bold p-2 rounded-lg text-xs flex items-center gap-1.5" title="Editar Cadastro Completo">
                                                <i class="fas fa-edit"></i> Editar
                                            </a>
                                            <a href="usuario.php?id=<?php echo $user['id_usuario']; ?>&abrirModal=1" class="btn-3d bg-amber-500 hover:bg-amber-600 text-white font-bold p-2 rounded-lg text-xs flex items-center gap-1.5" title="Alterar Senha do Usuário">
                                                <i class="fas fa-key"></i> Senha
                                            </a>
                                        </div>
                                    </td>

                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="py-12 text-center text-slate-400 italic">
                                    <i class="fa-regular fa-folder-open text-3xl block mb-2 text-slate-300"></i>
                                    Nenhum usuário encontrado na base de dados.
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