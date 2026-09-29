<?php
session_start();
require_once 'Database.php';

if (!isset($_SESSION['usuario_id']) || (!$_SESSION['is_financeiro'] && !$_SESSION['is_master'])) {
    header("Location: index.php");
    exit;
}

$db = new Database();
$id_mov = isset($_GET['id']) ? intval($_GET['id']) : null;
$mov_atual = null;

if ($id_mov) {
    $res = $db->Query("SELECT * FROM FINANCEIRO_MOV WHERE ID_FINANCEIRO_MOV = $id_mov LIMIT 1");
    if (!empty($res)) $mov_atual = $res[0];
}

$categorias = $db->Query("SELECT * FROM CATEGORIA_DESPESA_RECEITA ORDER BY DESCRICAO ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pagar_receber = $_POST['pagar_receber'];
    $data_emissao  = $_POST['data_emissao'];
    $data_vencimento = $_POST['data_vencimento'];
    $documento     = str_replace("'", "''", trim($_POST['documento']));
    $nota_fiscal   = str_replace("'", "''", trim($_POST['nota_fiscal']));
    $id_categoria  = intval($_POST['id_categoria']);
    $valor         = str_replace(['.', ','], ['', '.'], $_POST['valor']);

    $id_edicao = !empty($_POST['id_financeiro_mov']) ? intval($_POST['id_financeiro_mov']) : null;

    if ($id_edicao) {
        $sql = "UPDATE FINANCEIRO_MOV SET 
                    PAGAR_RECEBER='$pagar_receber', DATA_EMISSAO='$data_emissao', DATA_VENCIMENTO='$data_vencimento', 
                    DOCUMENTO='$documento', NOTA_FISCAL='$nota_fiscal', VALOR=$valor, ID_CATEGORIA=$id_categoria 
                WHERE ID_FINANCEIRO_MOV = $id_edicao";
        $db->Query($sql);
        header("Location: financeiro_lista.php?msg=sucesso");
        exit;
    } else {
        $sql = "INSERT INTO FINANCEIRO_MOV (PAGAR_RECEBER, DATA_EMISSAO, DATA_VENCIMENTO, DOCUMENTO, NOTA_FISCAL, VALOR, ID_CATEGORIA, DESCONTO, JUROS, VALOR_BAIXA) 
                VALUES ('$pagar_receber', '$data_emissao', '$data_vencimento', '$documento', '$nota_fiscal', $valor, $id_categoria, 0, 0, 0)";
        $db->Query($sql);
        header("Location: financeiro_lista.php?msg=sucesso");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lançamento Financeiro - PrimeSys Imob</title>
    <!-- Tailwind CSS Oficial carregado corretamente -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/imask"></script>
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

    <div class="max-w-2xl mx-auto">
        <!-- Barra superior de navegação interna -->
        <div class="flex items-center justify-between mb-6 select-none">
            <h1 class="text-2xl font-black text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-file-invoice mr-2 text-indigo-600"></i>
                <?php echo $mov_atual ? 'Editar Título Financeiro' : 'Novo Lançamento Financeiro'; ?>
            </h1>
            <a href="financeiro_lista.php" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1 transition-colors">
                <i class="fa-solid fa-arrow-left"></i> Voltar para fluxo de caixa
            </a>
        </div>

        <!-- Formulário Estruturado -->
        <form action="" method="POST" class="bg-white p-8 rounded-2xl shadow-xl border border-slate-200 space-y-6 text-xs font-medium">
            <?php if($mov_atual): ?>
                <input type="hidden" name="id_financeiro_mov" value="<?php echo $mov_atual['ID_FINANCEIRO_MOV']; ?>">
            <?php endif; ?>
            
            <h2 class="text-sm font-bold text-indigo-600 border-b pb-2 mb-4 uppercase tracking-wider">
                <i class="fa-solid fa-money-check-dollar mr-1"></i> Especificações do Documento
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">Natureza do Título</label>
                    <select name="pagar_receber" required class="w-full p-2.5 bg-white border border-slate-300 rounded-lg text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="P" <?php echo (isset($mov_atual['PAGAR_RECEBER']) && $mov_atual['PAGAR_RECEBER'] == 'P') ? 'selected' : ''; ?>>A Pagar (Despesa / Custo / Repasse)</option>
                        <option value="R" <?php echo (isset($mov_atual['PAGAR_RECEBER']) && $mov_atual['PAGAR_RECEBER'] == 'R') ? 'selected' : ''; ?>>A Receber (Receita / Comissão)</option>
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">Categoria Contábil</label>
                    <select name="id_categoria" required class="w-full p-2.5 bg-white border border-slate-300 rounded-lg text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Selecione uma Categoria --</option>
                        <?php foreach($categorias as $c) {
                            $sel = (isset($mov_atual['ID_CATEGORIA']) && $mov_atual['ID_CATEGORIA'] == $c['ID_CATEGORIA']) ? 'selected' : '';
                            echo "<option value='{$c['ID_CATEGORIA']}' $sel>[{$c['TIPO']}] " . htmlspecialchars($c['DESCRICAO']) . "</option>";
                        } ?>
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">Data Emissão</label>
                    <input type="date" name="data_emissao" required value="<?php echo $mov_atual['DATA_EMISSAO'] ?? date('Y-m-d'); ?>" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-700">
                </div>

                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">Data Vencimento</label>
                    <input type="date" name="data_vencimento" required value="<?php echo $mov_atual['DATA_VENCIMENTO'] ?? ''; ?>" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-700">
                </div>

                <div class="md:col-span-2">
                    <label class="block font-bold text-slate-600 uppercase mb-1">Descrição do Documento / Fornecedor / Cliente</label>
                    <input type="text" name="documento" required value="<?php echo htmlspecialchars($mov_atual['DOCUMENTO'] ?? ''); ?>" placeholder="Ex: NF 4310 - Compra de Notebooks / Comissão Lead João" class="w-full p-2.5 border border-slate-300 rounded-lg text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">Número Nota Fiscal (Se houver)</label>
                    <input type="text" name="nota_fiscal" value="<?php echo htmlspecialchars($mov_atual['NOTA_FISCAL'] ?? ''); ?>" placeholder="Ex: 000.123.456" class="w-full p-2.5 border border-slate-300 rounded-lg text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">Valor Nominal do Título</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 font-bold text-sm text-slate-400">R$</span>
                        <input type="text" id="valor" name="valor" required value="<?php echo isset($mov_atual['VALOR']) ? number_format($mov_atual['VALOR'], 2, ',', '.') : ''; ?>" class="w-full p-2.5 pl-9 border border-slate-300 rounded-lg text-right font-bold text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-50">
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t flex justify-end">
                <button type="submit" class="btn-3d bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold px-10 py-3 rounded-xl shadow-lg text-xs flex items-center gap-1.5">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar Lançamento
                </button>
            </div>
        </form>
    </div>

    <!-- Script de Ativação do IMask -->
    <script>
        IMask(document.getElementById('valor'), { 
            mask: Number, 
            scale: 2, 
            thousandsSeparator: '.', 
            padFractionalZeros: true, 
            radix: ',' 
        });
    </script>
</body>
</html>