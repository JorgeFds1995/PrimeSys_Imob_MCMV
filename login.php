<?php
session_start();
require_once 'Database.php';

// Se o usuário já estiver logado, redireciona direto para a index
if (isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    
    $login = str_replace("'", "''", trim($_POST['login']));
    $senha = trim($_POST['senha']);

    // Busca o usuário pelo login
    $sql = "SELECT id_usuario, login, senha, nome, foto, corretor, financeiro, master_user FROM usuarios WHERE login = '$login' LIMIT 1";
    $resultado = $db->Query($sql);

    if (!empty($resultado)) {
        $usuario = $resultado[0];

        // DESENCRIPTÇÃO E VALIDAÇÃO DA SENHA (password_verify)
        if (password_verify($senha, $usuario['senha'])) {
            // Guarda as informações cruciais na sessão do PHP
            $_SESSION['usuario_id']     = $usuario['id_usuario'];
            $_SESSION['usuario_nome']   = $usuario['nome'];
            $_SESSION['usuario_login']  = $usuario['login'];
            $_SESSION['usuario_foto']   = $usuario['foto'];
            
            // Guarda os níveis de acesso (booleanos/inteiros)
            $_SESSION['is_master']      = (bool)$usuario['master_user'];
            $_SESSION['is_corretor']    = (bool)$usuario['corretor'];
            $_SESSION['is_financeiro']  = (bool)$usuario['financeiro'];

            header("Location: index.php");
            exit;
        } else {
            $erro = "Usuário ou senha incorretos.";
        }
    } else {
        $erro = "Usuário ou senha incorretos.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso ao Sistema - PrimeSys Imob</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .btn-3d {
            transition: all 0.1s ease-in-out;
            box-shadow: 0 4px 0 0 rgba(79, 70, 229, 0.4);
        }
        .btn-3d:active {
            transform: translateY(4px);
            box-shadow: 0 0px 0 0 transparent;
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
<body class="bg-slate-900 min-h-screen font-sans flex items-center justify-center antialiased bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-slate-800 via-slate-900 to-black">

    <div class="w-full max-w-md p-4">
        
        <div class="text-center mb-8">
            <h1 class="text-3xl font-black text-indigo-500 tracking-tight">PrimeSys <span class="text-white font-bold">Imob</span></h1>
            <p class="text-sm text-slate-400 mt-2">Painel de Vendas & Relatórios</p>
        </div>

        <div class="bg-white/95 backdrop-blur-md p-8 rounded-2xl shadow-2xl border border-slate-200">
            <h2 class="text-xl font-bold text-slate-800 mb-6 flex items-center gap-2">
                <i class="fa-solid fa-lock text-indigo-600"></i> Autenticação
            </h2>

            <?php if ($erro): ?>
                <div class="mb-4 p-3 bg-red-100 border-l-4 border-red-500 text-red-700 text-sm rounded-r-lg flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?php echo $erro; ?></span>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Usuário / Login</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i class="fa-solid fa-user text-sm"></i>
                        </span>
                        <input type="text" name="login" required autocomplete="username" class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800" placeholder="Seu usuário">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Senha de Acesso</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i class="fa-solid fa-key text-sm"></i>
                        </span>
                        <input type="password" name="senha" required autocomplete="current-password" class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800" placeholder="••••••••">
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full btn-3d bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold py-3 rounded-xl shadow-lg flex items-center justify-center gap-2">
                        <i class="fa-solid fa-right-to-bracket"></i> Entrar no Sistema
                    </button>
                </div>
            </form>
        </div>

    </div>

</body>
</html>