<?php
session_start();
require_once 'Database.php';

if (!isset($_SESSION['usuario_id']) || (!$_SESSION['is_financeiro'] && !$_SESSION['is_master'])) {
    header("Location: index.php");
    exit;
}

$db = new Database();

// 1. CARREGA DADOS DE APOIO PARA OS MODAIS E FILTROS
$bancos = $db->Query("SELECT ID_BANCO, DESC_BANCO FROM BANCOS ORDER BY DESC_BANCO ASC");
$categorias = $db->Query("SELECT ID_CATEGORIA, DESCRICAO, TIPO FROM CATEGORIA_DESPESA_RECEITA ORDER BY DESCRICAO ASC");

// Captura de Filtros via URL GET
$f_tipo      = isset($_GET['f_tipo']) ? trim($_GET['f_tipo']) : '';
$f_cat       = isset($_GET['f_cat']) ? intval($_GET['f_cat']) : 0;
$f_emissao   = isset($_GET['f_emissao']) ? trim($_GET['f_emissao']) : '';
$f_vencimento= isset($_GET['f_vencimento']) ? trim($_GET['f_vencimento']) : '';

// 2. MONTAGEM DA CONSULTA COM INNER JOIN
$sql = "SELECT f.*, c.DESCRICAO as NOME_CATEGORIA 
        FROM FINANCEIRO_MOV f
        INNER JOIN CATEGORIA_DESPESA_RECEITA c ON f.ID_CATEGORIA = c.ID_CATEGORIA
        WHERE 1=1";

if (!empty($f_tipo))       $sql .= " AND f.PAGAR_RECEBER = '$f_tipo'";
if ($f_cat > 0)            $sql .= " AND f.ID_CATEGORIA = $f_cat";
if (!empty($f_emissao))    $sql .= " AND f.DATA_EMISSAO = '$f_emissao'";
if (!empty($f_vencimento)) $sql .= " AND f.DATA_VENCIMENTO = '$f_vencimento'";

$sql .= " ORDER BY f.DATA_VENCIMENTO ASC";
$movimentacoes = $db->Query($sql);

// 3. PROCESSAMENTO DO CADASTRO RÁPIDO DE CATEGORIAS (POST VIA MODAL)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_categoria'])) {
    $desc = str_replace("'", "''", trim($_POST['descricao_cat']));
    $tipo = $_POST['tipo_cat'];
    $db->Query("INSERT INTO CATEGORIA_DESPESA_RECEITA (DESCRICAO, TIPO) VALUES ('$desc', '$tipo')");
    header("Location: financeiro_lista.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fluxo de Caixa - PrimeSys Imob</title>
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
<body class="bg-slate-100 min-h-screen font-sans antialiased text-slate-800">

    <div class="max-w-7xl mx-auto py-10 px-4">
        
        <!-- Cabeçalho -->
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4 select-none">
            <div>
                <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">
                    <i class="fa-solid fa-money-bill-transfer text-indigo-600 mr-2"></i>Contas a Pagar & Receber
                </h1>
                <p class="text-sm text-slate-500 mt-1">Monitore o fluxo de caixa, realize baixas operacionais e gerencie o plano de contas.</p>
            </div>
            <div class="flex gap-2">
                <button onclick="abrirModalCat()" class="btn-3d bg-slate-700 hover:bg-slate-800 text-white font-bold py-2.5 px-4 rounded-lg flex items-center gap-2 text-xs">
                    <i class="fas fa-tags"></i> Categorias
                </button>
                <a href="financeiro_mov.php" class="btn-3d bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-5 rounded-lg flex items-center gap-2 text-xs">
                    <i class="fas fa-plus"></i> Novo Lançamento
                </a>
            </div>
        </div>

        <!-- Filtros Contábeis -->
        <div class="bg-white p-5 rounded-2xl shadow-md border border-slate-200 mb-6 select-none">
            <form action="" method="GET" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4 text-xs">
                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">Operação</label>
                    <select name="f_tipo" class="w-full p-2.5 bg-slate-50 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Todas</option>
                        <option value="P" <?php echo $f_tipo == 'P' ? 'selected' : ''; ?>>Contas a Pagar (Despesa)</option>
                        <option value="R" <?php echo $f_tipo == 'R' ? 'selected' : ''; ?>>Contas a Receber (Receita)</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">Categoria</label>
                    <select name="f_cat" class="w-full p-2.5 bg-slate-50 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="0">Todas</option>
                        <?php if(!empty($categorias)): foreach($categorias as $c): ?>
                            <option value="<?php echo $c['ID_CATEGORIA']; ?>" <?php echo $f_cat == $c['ID_CATEGORIA'] ? 'selected' : ''; ?>>
                                [<?php echo $c['TIPO']; ?>] <?php echo htmlspecialchars($c['DESCRICAO']); ?>
                            </option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">Data Emissão</label>
                    <input type="date" name="f_emissao" value="<?php echo $f_emissao; ?>" class="w-full p-2 bg-slate-50 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">Data Vencimento</label>
                    <input type="date" name="f_vencimento" value="<?php echo $f_vencimento; ?>" class="w-full p-2 bg-slate-50 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full btn-3d bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 rounded-lg text-xs">Filtrar Lançamentos</button>
                </div>
            </form>
        </div>

        <!-- Tabela Contábil -->
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 text-xs font-bold uppercase tracking-wider">
                            <th class="py-4 px-4 w-12 text-center">Tipo</th>
                            <th class="py-4 px-4">Documento / Histórico</th>
                            <th class="py-4 px-4">Categoria</th>
                            <th class="py-4 px-4">Vencimento</th>
                            <th class="py-4 px-4 text-right">Valor Original</th>
                            <th class="py-4 px-4 text-center">Situação</th>
                            <th class="py-4 px-4 text-center w-44">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <?php if(!empty($movimentacoes)): foreach($movimentacoes as $mov): 
                            $is_pago = !empty($mov['DATA_BAIXA']);
                            $is_despesa = $mov['PAGAR_RECEBER'] === 'P';
                        ?>
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-4 px-4 text-center font-bold">
                                    <span class="px-2.5 py-1 rounded-md text-xs font-black <?php echo $is_despesa ? 'bg-red-50 text-red-600':'bg-emerald-50 text-emerald-600'; ?>">
                                        <?php echo $is_despesa ? 'P' : 'R'; ?>
                                    </span>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="font-bold text-slate-800"><?php echo htmlspecialchars($mov['DOCUMENTO']); ?></div>
                                    <div class="text-xs text-slate-400 font-medium">NF: <?php echo htmlspecialchars($mov['NOTA_FISCAL'] ?? '---'); ?></div>
                                </td>
                                <td class="py-4 px-4 font-semibold text-xs text-slate-600"><?php echo htmlspecialchars($mov['NOME_CATEGORIA']); ?></td>
                                <td class="py-4 px-4 text-xs font-mono font-bold text-slate-600"><?php echo date('d/m/Y', strtotime($mov['DATA_VENCIMENTO'])); ?></td>
                                <td class="py-4 px-4 text-right font-bold text-slate-900">R$ <?php echo number_format($mov['VALOR'], 2, ',', '.'); ?></td>
                                <td class="py-4 px-4 text-center">
                                    <?php if($is_pago): ?>
                                        <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">Baixado</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-amber-100 text-amber-800 border border-amber-200">Aberto</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-4 text-center whitespace-nowrap space-x-1">
                                    <a href="financeiro_mov.php?id=<?php echo $mov['ID_FINANCEIRO_MOV']; ?>" class="btn-3d bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold py-1.5 px-2.5 rounded-lg text-xs inline-flex items-center gap-1">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if(!$is_pago): ?>
                                        <button onclick="abrirModalBaixa(<?php echo $mov['ID_FINANCEIRO_MOV']; ?>, '<?php echo $mov['VALOR']; ?>')" class="btn-3d bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-1.5 px-3 rounded-lg text-xs inline-flex items-center gap-1">
                                            <i class="fas fa-check-double"></i> Baixar
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="7" class="py-12 text-center text-slate-400 italic">Nenhum lançamento financeiro registrado para os filtros informados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL 1: GERENCIAMENTO DE CATEGORIAS -->
    <div id="modalCat" class="fixed inset-0 bg-slate-900 bg-opacity-50 hidden backdrop-blur-sm justify-center items-center z-50">
        <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-sm w-full mx-4 border">
            <h3 class="text-lg font-bold text-slate-800 mb-4"><i class="fas fa-tags text-indigo-600 mr-2"></i>Nova Categoria</h3>
            <form action="" method="POST" class="space-y-4 text-xs">
                <input type="hidden" name="action_categoria" value="1">
                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">Descrição da Categoria</label>
                    <input type="text" name="descricao_cat" required class="w-full p-2.5 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">Natureza Contábil</label>
                    <select name="tipo_cat" required class="w-full p-2.5 bg-white border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="P">Despesa (Contas a Pagar)</option>
                        <option value="R">Receita (Contas a Receber)</option>
                    </select>
                </div>
                <div class="flex justify-end gap-2 pt-4 border-t">
                    <button type="button" onclick="fecharModalCat()" class="bg-slate-200 text-slate-700 font-bold px-4 py-2 rounded-lg text-xs">Cancelar</button>
                    <button type="submit" class="btn-3d bg-indigo-600 text-white font-bold px-4 py-2 rounded-lg text-xs">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: LIQUIDAÇÃO / BAIXA DE TÍTULOS -->
    <div id="modalBaixa" class="fixed inset-0 bg-slate-900 bg-opacity-50 hidden backdrop-blur-sm justify-center items-center z-50">
        <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-md w-full mx-4 border">
            <h3 class="text-lg font-bold text-slate-800 mb-4"><i class="fas fa-cash-register text-indigo-600 mr-2"></i>Baixar Documento</h3>
            <form action="financeiro_baixa_action.php" method="POST" class="space-y-4 text-xs">
                <input type="hidden" id="baixa_id" name="id_mov">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-600 uppercase mb-1">Valor Original</label>
                        <input type="text" id="baixa_vlr_orig_exibicao" readonly class="w-full p-2.5 bg-slate-50 border rounded-lg font-bold text-right text-slate-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-600 uppercase mb-1">Data da Baixa</label>
                        <input type="date" name="data_baixa" required value="<?php echo date('Y-m-d'); ?>" class="w-full p-2.5 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-600 uppercase mb-1">Descontos (-)</label>
                        <input type="text" id="m_desc" name="desconto" class="w-full p-2.5 border rounded-lg text-right focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-600 uppercase mb-1">Juros / Multa (+)</label>
                        <input type="text" id="m_juros" name="juros" class="w-full p-2.5 border rounded-lg text-right focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">Valor Real Liquido (Valor Baixa)</label>
                    <input type="text" id="m_baixa" name="valor_baixa" required class="w-full p-2.5 border rounded-lg font-black text-right text-base text-slate-900 bg-amber-50 border-amber-300 focus:outline-none">
                </div>
                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">Conta Bancária da Operação</label>
                    <select name="id_banco" required class="w-full p-2.5 bg-white border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Escolha o Banco Destino/Origem --</option>
                        <?php if(!empty($bancos)): foreach($bancos as $b): ?>
                            <option value="<?php echo $b['ID_BANCO']; ?>"><?php echo htmlspecialchars($b['DESC_BANCO']); ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">Observações Internas</label>
                    <input type="text" name="observacao_baixa" class="w-full p-2.5 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="flex justify-end gap-2 mt-4 pt-4 border-t">
                    <button type="button" onclick="fecharModalBaixa()" class="bg-slate-200 text-slate-700 font-bold px-4 py-2 rounded-lg text-xs">Cancelar</button>
                    <button type="submit" class="btn-3d bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-5 py-2 rounded-lg text-xs">Confirmar Liquidação</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts de Controle Operacional de Tela -->
    <script>
        function abrirModalCat() { document.getElementById('modalCat').style.display = 'flex'; }
        function fecharModalCat() { document.getElementById('modalCat').style.display = 'none'; }
        
        let valorOriginalFloat = 0.00;

        function abrirModalBaixa(id, valorCru) {
            document.getElementById('baixa_id').value = id;
            valorOriginalFloat = parseFloat(valorCru);
            
            // Seta os valores padrões formatados em Real
            const valorFormatado = valorOriginalFloat.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('baixa_vlr_orig_exibicao').value = 'R$ ' + valorFormatado;
            
            // Reseta inputs e joga o valor limpo nos campos controlados pelo IMask
            maskDesc.value = '0,00';
            maskJuros.value = '0,00';
            maskBaixa.value = valorFormatado;

            document.getElementById('modalBaixa').style.display = 'flex';
        }
        function fecharModalBaixa() { document.getElementById('modalBaixa').style.display = 'none'; }

        // Ativação e amarração matemática das máscaras decimais IMask
        const confDecimal = { mask: Number, scale: 2, signed: false, thousandsSeparator: '.', padFractionalZeros: true, radix: ',' };
        
        const maskDesc  = IMask(document.getElementById('m_desc'), confDecimal);
        const maskJuros = IMask(document.getElementById('m_juros'), confDecimal);
        const maskBaixa = IMask(document.getElementById('m_baixa'), confDecimal);

        // Função de recalculo em tempo real (Valor Baixa = Original - Desconto + Juros)
        function recalcularEfetivo() {
            const desc  = maskDesc.unmaskedValue ? parseFloat(maskDesc.unmaskedValue) : 0;
            const juros = maskJuros.unmaskedValue ? parseFloat(maskJuros.unmaskedValue) : 0;
            const resultado = valorOriginalFloat - desc + juros;
            
            maskBaixa.value = resultado.toFixed(2).replace('.', ',');
        }

        document.getElementById('m_desc').addEventListener('input', recalcularEfetivo);
        document.getElementById('m_juros').addEventListener('input', recalcularEfetivo);
    </script>
</body>
</html>