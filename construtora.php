<?php
session_start();
require_once 'Database.php';

// Proteção básica de sessão
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$db = new Database();

// Inicialização de variáveis para edição
$id_construtora = isset($_GET['id']) ? intval($_GET['id']) : null;
$construtora_atual = null;

if ($id_construtora) {
    $sql = "SELECT * FROM construtoras WHERE id_construtora = $id_construtora LIMIT 1";
    $resultado = $db->Query($sql);
    if (!empty($resultado)) {
        $construtora_atual = $resultado[0];
    }
}

// Processamento do Formulário (Salvar / Atualizar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Escapa aspas para segurança de sintaxe SQL na classe Database
    $razao_social = str_replace("'", "''", trim($_POST['razao_social']));
    $nome_fantasia = str_replace("'", "''", trim($_POST['nome_fantasia']));
    
    // Remove caracteres especiais das máscaras antes de salvar
    $cnpj = preg_replace('/\D/', '', $_POST['cnpj']);
    $cep = preg_replace('/\D/', '', $_POST['cep']);
    $telefone1 = preg_replace('/\D/', '', $_POST['telefone1']);
    $telefone2 = preg_replace('/\D/', '', $_POST['telefone2']);
    $fone_gerente = preg_replace('/\D/', '', $_POST['fone_gerente']);
    
    $endereco = str_replace("'", "''", trim($_POST['endereco']));
    $complemento = str_replace("'", "''", trim($_POST['complemento']));
    $email = str_replace("'", "''", trim($_POST['email']));
    $gerente_responsavel = str_replace("'", "''", trim($_POST['gerente_responsavel']));
    $email_gerente = str_replace("'", "''", trim($_POST['email_gerente']));
    $observacao = str_replace("'", "''", trim($_POST['observacao']));
    
    $id_edicao = !empty($_POST['id_construtora']) ? intval($_POST['id_construtora']) : null;

    if ($id_edicao) {
        // --- MODO EDIÇÃO ---
        $sql = "UPDATE construtoras SET 
                    razao_social = '$razao_social', 
                    nome_fantasia = '$nome_fantasia', 
                    cnpj = '$cnpj', 
                    endereco = '$endereco', 
                    complemento = '$complemento', 
                    cep = '$cep', 
                    telefone1 = '$telefone1', 
                    telefone2 = '$telefone2', 
                    email = '$email', 
                    gerente_responsavel = '$gerente_responsavel', 
                    fone_gerente = '$fone_gerente', 
                    email_gerente = '$email_gerente', 
                    observacao = '$observacao'
                WHERE id_construtora = $id_edicao";
        
        $db->Query($sql);
        header("Location: construtora.php?id=$id_edicao&msg=sucesso");
        exit;
    } else {
        // --- MODO CADASTRO ---
        $sql = "INSERT INTO construtoras (razao_social, nome_fantasia, cnpj, endereco, complemento, cep, telefone1, telefone2, email, gerente_responsavel, fone_gerente, email_gerente, observacao) 
                VALUES ('$razao_social', '$nome_fantasia', '$cnpj', '$endereco', '$complemento', '$cep', '$telefone1', '$telefone2', '$email', '$gerente_responsavel', '$fone_gerente', '$email_gerente', '$observacao')";
        
        $db->Query($sql);
        header("Location: construtoras_lista.php?msg=cadastrado");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Construtora - PrimeSys Imob</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/imask"></script>
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

    <div class="max-w-5xl mx-auto py-10 px-4">
        
        <?php if(isset($_GET['msg'])): ?>
            <div class="mb-6 p-4 rounded-lg bg-green-500 text-white shadow-md flex items-center gap-2">
                <i class="fas fa-check-circle text-xl"></i>
                <span>Parceiro atualizado com sucesso!</span>
            </div>
        <?php endif; ?>

        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">
                    <i class="fa-solid fa-industry text-indigo-600 mr-2"></i>
                    <?php echo $construtora_atual ? 'Editar Construtora' : 'Nova Construtora'; ?>
                </h1>
                <p class="text-sm text-slate-500 mt-1">Gerencie os dados das construtoras e incorporadoras parceiras.</p>
            </div>
            <a href="construtoras_lista.php" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                <i class="fa-solid fa-arrow-left"></i> Voltar para a lista
            </a>
        </div>

        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?><?php echo $construtora_atual ? '?id='.$id_construtora : ''; ?>" method="POST" class="space-y-6">
            <?php if($construtora_atual): ?>
                <input type="hidden" name="id_construtora" value="<?php echo $construtora_atual['id_construtora']; ?>">
            <?php endif; ?>

            <div class="bg-white p-8 rounded-2xl shadow-xl border border-slate-200 space-y-6">
                
                <h2 class="text-xl font-bold text-indigo-600 border-b pb-2"><i class="fa-solid fa-building mr-2"></i>Informações Institucionais</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Razão Social</label>
                        <input type="text" name="razao_social" required value="<?php echo $construtora_atual['razao_social'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nome Fantasia</label>
                        <input type="text" name="nome_fantasia" required value="<?php echo $construtora_atual['nome_fantasia'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">CNPJ</label>
                        <input type="text" id="cnpj" name="cnpj" value="<?php echo $construtora_atual['cnpj'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800" placeholder="00.000.000/0000-00">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">E-mail Corporativo</label>
                        <input type="email" name="email" value="<?php echo $construtora_atual['email'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800" placeholder="empresa@construtora.com.br">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Telefone Principal</label>
                        <input type="text" id="telefone1" name="telefone1" value="<?php echo $construtora_atual['telefone1'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800" placeholder="(00) 0000-0000">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Telefone Secundário</label>
                        <input type="text" id="telefone2" name="telefone2" value="<?php echo $construtora_atual['telefone2'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800" placeholder="(00) 00000-0000">
                    </div>
                </div>

                <h2 class="text-xl font-bold text-indigo-600 border-b pb-2 pt-2"><i class="fa-solid fa-map-location-dot mr-2"></i>Localização</h2>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">CEP</label>
                        <input type="text" id="cep" name="cep" value="<?php echo $construtora_atual['cep'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800" placeholder="00000-000">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Endereço Residencial / Comercial</label>
                        <input type="text" name="endereco" value="<?php echo $construtora_atual['endereco'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800" placeholder="Av, Rua, Número...">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Complemento</label>
                        <input type="text" name="complemento" value="<?php echo $construtora_atual['complemento'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800" placeholder="Sala, Bloco...">
                    </div>
                </div>

                <h2 class="text-xl font-bold text-indigo-600 border-b pb-2 pt-2"><i class="fa-solid fa-user-tie mr-2"></i>Gerente Responsável</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nome do Gerente</label>
                        <input type="text" name="gerente_responsavel" value="<?php echo $construtora_atual['gerente_responsavel'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Telefone do Gerente</label>
                        <input type="text" id="fone_gerente" name="fone_gerente" value="<?php echo $construtora_atual['fone_gerente'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800" placeholder="(00) 00000-0000">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">E-mail do Gerente</label>
                        <input type="email" name="email_gerente" value="<?php echo $construtora_atual['email_gerente'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800" placeholder="gerente@construtora.com">
                    </div>
                </div>

                <h2 class="text-xl font-bold text-indigo-600 border-b pb-2 pt-2"><i class="fa-solid fa-comment-medical mr-2"></i>Observações Internas</h2>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Notas / Detalhes de Parceria</label>
                    <input type="text" name="observacao" value="<?php echo $construtora_atual['observacao'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800" placeholder="Acordos de comissão, tabelas de preço padrão, etc.">
                </div>

                <div class="pt-4 border-t flex justify-end">
                    <button type="submit" class="btn-3d bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold px-8 py-3 rounded-xl shadow-lg flex items-center gap-2">
                        <i class="fas fa-save"></i> Salvar Construtora
                    </button>
                </div>

            </div>
        </form>
    </div>

    <script>
        // Aplicando máscaras idênticas às telas de usuário
        IMask(document.getElementById('cnpj'), { mask: '00.000.000/0000-00' });
        IMask(document.getElementById('cep'), { mask: '00000-000' });
        IMask(document.getElementById('telefone1'), { mask: [{ mask: '(00) 0000-0000' }, { mask: '(00) 00000-0000' }] });
        IMask(document.getElementById('telefone2'), { mask: [{ mask: '(00) 0000-0000' }, { mask: '(00) 00000-0000' }] });
        IMask(document.getElementById('fone_gerente'), { mask: [{ mask: '(00) 0000-0000' }, { mask: '(00) 00000-0000' }] });
    </script>
</body>
</html>