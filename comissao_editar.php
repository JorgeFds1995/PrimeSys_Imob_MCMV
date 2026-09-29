<?php
session_start();
require_once 'Database.php';

if (!isset($_SESSION['usuario_id']) || (!$_SESSION['is_financeiro'] && !$_SESSION['is_master'])) {
    header("Location: index.php");
    exit;
}

$db = new Database();
$id = isset($_GET['id']) ? intval($_GET['id']) : die('ID inválido.');

$sql = "SELECT co.*, cl.nome as cliente, u.nome as corretor FROM comissao_imob co 
        INNER JOIN clientes cl ON co.id_cliente = cl.id_cliente
        INNER JOIN usuarios u ON co.id_corretor = u.id_usuario
        WHERE co.id_comissao = $id LIMIT 1";
$resultado = $db->Query($sql);
(!empty($resultado)) ? $com = $resultado[0] : die('Registro não encontrado.');

// Carrega os bancos para as comboboxes
$filtros_bancos = $db->Query("SELECT id_banco, desc_banco FROM bancos ORDER BY desc_banco ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data_vencimento = $_POST['data_vencimento'];
    $data_recebimento = !empty($_POST['data_recebimento']) ? "'".$_POST['data_recebimento']."'" : 'NULL';
    $data_pagamento_corretor = !empty($_POST['data_pagamento_corretor']) ? "'".$_POST['data_pagamento_corretor']."'" : 'NULL';
    
    $nota_fiscal_imobiliaria = str_replace("'", "''", trim($_POST['nota_fiscal_imobiliaria']));
    $nota_fiscal_corretor = str_replace("'", "''", trim($_POST['nota_fiscal_corretor']));
    $observacao = str_replace("'", "''", trim($_POST['observacao']));
    
    $id_banco_recebimento = !empty($_POST['id_banco_recebimento']) ? intval($_POST['id_banco_recebimento']) : 'NULL';
    $id_banco_pagamento_corretor = !empty($_POST['id_banco_pagamento_corretor']) ? intval($_POST['id_banco_pagamento_corretor']) : 'NULL';

    $sql_up = "UPDATE comissao_imob SET 
                data_vencimento = '$data_vencimento', data_recebimento = $data_recebimento, 
                data_pagamento_corretor = $data_pagamento_corretor, nota_fiscal_imobiliaria = '$nota_fiscal_imobiliaria', 
                nota_fiscal_corretor = '$nota_fiscal_corretor', id_banco_recebimento = $id_banco_recebimento, 
                id_banco_pagamento_corretor = $id_banco_pagamento_corretor, observacao = '$observacao'
               WHERE id_comissao = $id";
    
    $db->Query($sql_up);
    header("Location: comissoes_lista.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Conciliação de Comissão - PrimeSys Imob</title>
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
<body class="bg-slate-100 min-h-screen font-sans antialiased text-slate-800 py-10 px-4">

    <div class="max-w-3xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-black text-slate-800"><i class="fa-solid fa-file-invoice-dollar text-indigo-600 mr-2"></i>Conciliação Financeira</h1>
            <a href="comissoes_lista.php" class="text-xs font-bold text-indigo-600 hover:underline">Voltar à listagem</a>
        </div>

        <form action="" method="POST" class="bg-white p-6 rounded-2xl shadow-xl border border-slate-200 space-y-6">
            <div class="bg-slate-50 p-4 rounded-xl text-xs font-semibold text-slate-500 space-y-1">
                <div><span class="text-slate-400">Cliente / Comprador:</span> <strong class="text-slate-700"><?php echo htmlspecialchars($com['cliente']); ?></strong></div>
                <div><span class="text-slate-400">Corretor Credenciado:</span> <strong class="text-slate-700"><?php echo htmlspecialchars($com['corretor']); ?></strong></div>
                <div class="grid grid-cols-3 pt-2 text-sm">
                    <div>VGV: <span class="text-slate-800 block font-bold">R$ <?php echo number_format($com['valor_geral_venda'],2,',','.'); ?></span></div>
                    <div>Comissão Imob: <span class="text-emerald-600 block font-bold">R$ <?php echo number_format($com['valor_comissao_imob'],2,',','.'); ?></span></div>
                    <div>Comissão Corr: <span class="text-indigo-600 block font-bold">R$ <?php echo number_format($com['valor_comissao_corretor'],2,',','.'); ?></span></div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Data de Vencimento</label>
                    <input type="date" name="data_vencimento" value="<?php echo $com['data_vencimento']; ?>" required class="w-full p-2 border rounded-lg text-sm bg-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nota Fiscal Imobiliária</label>
                    <input type="text" name="nota_fiscal_imobiliaria" value="<?php echo htmlspecialchars($com['nota_fiscal_imobiliaria'] ?? ''); ?>" class="w-full p-2 border rounded-lg text-sm bg-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Data Recebimento (Faturamento)</label>
                    <input type="date" name="data_recebimento" value="<?php echo $com['data_recebimento']; ?>" class="w-full p-2 border rounded-lg text-sm bg-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Cód. Banco Recebimento</label>
                    <select name="id_banco_recebimento" class="w-full p-2 border rounded-lg text-sm bg-white text-slate-700">
                        <option value="">Selecione um banco</option>
                        <?php 
                        if (!empty($filtros_bancos)) {
                            foreach ($filtros_bancos as $banco) {
                                $selected = ($com['id_banco_recebimento'] == $banco['id_banco']) ? 'selected' : '';
                                echo "<option value='" . $banco['id_banco'] . "' " . $selected . ">" . htmlspecialchars($banco['desc_banco']) . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>

            <h3 class="text-sm font-bold text-indigo-600 border-b pb-1 pt-2"><i class="fa-solid fa-reply mr-1"></i>Repasse ao Corretor</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nota Fiscal Corretor / Recibo</label>
                    <input type="text" name="nota_fiscal_corretor" value="<?php echo htmlspecialchars($com['nota_fiscal_corretor'] ?? ''); ?>" class="w-full p-2 border rounded-lg text-sm bg-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Data de Pagamento ao Corretor</label>
                    <input type="date" name="data_pagamento_corretor" value="<?php echo $com['data_pagamento_corretor']; ?>" class="w-full p-2 border rounded-lg text-sm bg-white">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Cód. Banco Saída Repasse</label>
                    <select name="id_banco_pagamento_corretor" class="w-full p-2 border rounded-lg text-sm bg-white text-slate-700">
                        <option value="">Selecione um banco</option>
                        <?php 
                        if (!empty($filtros_bancos)) {
                            foreach ($filtros_bancos as $banco) {
                                $selected = ($com['id_banco_pagamento_corretor'] == $banco['id_banco']) ? 'selected' : '';
                                echo "<option value='" . $banco['id_banco'] . "' " . $selected . ">" . htmlspecialchars($banco['desc_banco']) . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Observações Internas Financeiras</label>
                <input type="text" name="observacao" value="<?php echo htmlspecialchars($com['observacao'] ?? ''); ?>" class="w-full p-2 border rounded-lg text-sm bg-white">
            </div>

            <div class="pt-4 border-t flex justify-end">
                <button type="submit" class="btn-3d bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold px-6 py-2.5 rounded-xl shadow-lg text-xs">
                    Gravar Conciliação
                </button>
            </div>
        </form>
    </div>

</body>
</html>