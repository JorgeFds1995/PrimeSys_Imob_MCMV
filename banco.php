<?php
session_start();
require_once 'Database.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$db = new Database();

// Inicialização de variáveis para edição
$id_banco = isset($_GET['id']) ? intval($_GET['id']) : null;
$banco_atual = null;

if ($id_banco) {
    $sql = "SELECT * FROM BANCOS WHERE ID_BANCO = $id_banco LIMIT 1";
    $resultado = $db->Query($sql);
    if (!empty($resultado)) {
        $banco_atual = $resultado[0];
    }
}

// Processamento do Formulário (Salvar / Atualizar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $desc_banco = str_replace("'", "''", trim($_POST['desc_banco']));
    
    // Tratamento do valor decimal vindo da máscara (ex: 1.500,00 -> 1500.00)
    $saldo_inicial = !empty($_POST['saldo_inicial']) ? str_replace(['.', ','], ['', '.'], $_POST['saldo_inicial']) : 0.00;
    
    $id_edicao = !empty($_POST['id_banco']) ? intval($_POST['id_banco']) : null;

    if ($id_edicao) {
        // --- MODO EDIÇÃO ---
        $sql = "UPDATE BANCOS SET 
                    DESC_BANCO = '$desc_banco', 
                    SALDO_INICIAL = $saldo_inicial 
                WHERE ID_BANCO = $id_edicao";
        
        $db->Query($sql);
        header("Location: banco.php?id=$id_edicao&msg=sucesso");
        exit;
    } else {
        // --- MODO CADASTRO ---
        $sql = "INSERT INTO BANCOS (DESC_BANCO, SALDO_INICIAL) 
                VALUES ('$desc_banco', $saldo_inicial)";
        
        $db->Query($sql);
        header("Location: bancos_lista.php?msg=cadastrado");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Conta Bancária - PrimeSys Imob</title>
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
<body class="bg-slate-100 min-h-screen font-sans antialiased text-slate-800">

    <div class="max-w-3xl mx-auto py-10 px-4">
        
        <?php if(isset($_GET['msg'])): ?>
            <div class="mb-6 p-4 rounded-lg bg-green-500 text-white shadow-md flex items-center gap-2">
                <i class="fas fa-check-circle text-xl"></i>
                <span>Conta bancária atualizada com sucesso!</span>
            </div>
        <?php endif; ?>

        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">
                    <i class="fa-solid fa-building-columns text-indigo-600 mr-2"></i>
                    <?php echo $banco_atual ? 'Editar Conta Bancária' : 'Nova Conta Bancária'; ?>
                </h1>
                <p class="text-sm text-slate-500 mt-1">Gerencie as contas e instituições para conciliação de recebimentos e repasses.</p>
            </div>
            <a href="bancos_lista.php" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                <i class="fa-solid fa-arrow-left"></i> Voltar para a lista
            </a>
        </div>

        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?><?php echo $banco_atual ? '?id='.$id_banco : ''; ?>" method="POST" class="space-y-6">
            <?php if($banco_atual): ?>
                <input type="hidden" name="id_banco" value="<?php echo $banco_atual['ID_BANCO']; ?>">
            <?php endif; ?>

            <div class="bg-white p-8 rounded-2xl shadow-xl border border-slate-200 space-y-6">
                
                <h2 class="text-xl font-bold text-indigo-600 border-b pb-2"><i class="fa-solid fa-wallet mr-2"></i>Dados da Conta / Instituição</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Descrição do Banco / Conta</label>
                        <input type="text" name="desc_banco" required value="<?php echo htmlspecialchars($banco_atual['DESC_BANCO'] ?? ''); ?>" placeholder="Ex: Banco do Brasil - Ag. Principal, Caixa Econômica Federal" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Saldo Inicial</label>
                        <div class="relative">
                            <span class="absolute left-3 top-2.5 text-slate-400 font-bold text-sm">R$</span>
                            <input type="text" id="saldo_inicial" name="saldo_inicial" value="<?php echo isset($banco_atual['SALDO_INICIAL']) ? number_format($banco_atual['SALDO_INICIAL'], 2, ',', '.') : ''; ?>" class="w-full p-2.5 pl-9 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800 text-right font-semibold">
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t flex justify-end">
                    <button type="submit" class="btn-3d bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold px-8 py-3 rounded-xl shadow-lg flex items-center gap-2">
                        <i class="fas fa-save"></i> Gravar Conta
                    </button>
                </div>

            </div>
        </form>
    </div>

    <!-- Scripts de Formatação Dinâmica (IMask) -->
    <script>
        IMask(document.getElementById('saldo_inicial'), {
            mask: Number, scale: 2, signed: true, thousandsSeparator: '.', padFractionalZeros: true, radix: ','
        });
    </script>
</body>
</html>