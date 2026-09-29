<?php
session_start();
require_once 'Database.php';

if (!isset($_SESSION['usuario_id']) || (!$_SESSION['is_financeiro'] && !$_SESSION['is_master'])) {
    header("Location: index.php");
    exit;
}

$db = new Database();

// 1. CARREGA LISTA DE BANCOS PARA O HEADER E MODAL
$bancos = $db->Query("SELECT ID_BANCO, DESC_BANCO, SALDO_INICIAL FROM BANCOS ORDER BY DESC_BANCO ASC");

// Captura de Filtros do Header
$id_banco_sel = isset($_GET['id_banco']) ? intval($_GET['id_banco']) : (isset($bancos[0]['ID_BANCO']) ? intval($bancos[0]['ID_BANCO']) : 0);
$data_ini     = isset($_GET['data_ini']) ? trim($_GET['data_ini']) : date('Y-m-01');
$data_fim     = isset($_GET['data_fim']) ? trim($_GET['data_fim']) : date('Y-m-t');

// Processamento do Lançamento de Transferência (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_transferencia'])) {
    $data_tf      = $_POST['data_tf'];
    $banco_origem = intval($_POST['banco_origem']);
    $banco_destino= intval($_POST['banco_destino']);
    $observacao   = str_replace("'", "''", trim($_POST['observacao']));
    $valor        = str_replace(['.', ','], ['', '.'], $_POST['valor_tf']);

    if ($banco_origem === $banco_destino) {
        header("Location: extrato_bancario.php?id_banco=$id_banco_sel&data_ini=$data_ini&data_fim=$data_fim&erro=mesmo_banco");
        exit;
    }

    $sql_tf = "INSERT INTO TRANSFERENCIAS (DATA, ID_BANCO_ORIGEM, ID_BANCO_DESTINO, VALOR, OBSERVACAO) 
               VALUES ('$data_tf', $banco_origem, $banco_destino, $valor, '$observacao')";
    $db->Query($sql_tf);
    
    header("Location: extrato_bancario.php?id_banco=$id_banco_sel&data_ini=$data_ini&data_fim=$data_fim&msg=tf_sucesso");
    exit;
}

// 2. LOGÍSTICA DE CÁLCULO DE SALDO ANTERIOR E EXTRATO (Matemática de Caixa)
$saldo_inicial_cadastro = 0.00;
foreach ($bancos as $b) {
    if ($b['ID_BANCO'] == $id_banco_sel) {
        $saldo_inicial_cadastro = floatval($b['SALDO_INICIAL']);
        break;
    }
}

// A. Busca TODAS as entradas/saídas de Baixas Financeiras desse banco anteriores à data inicial
$mov_ant_fin = $db->Query("SELECT SUM(CASE WHEN PAGAR_RECEBER = 'R' THEN VALOR_BAIXA ELSE -VALOR_BAIXA END) as total FROM FINANCEIRO_MOV WHERE ID_BANCO = $id_banco_sel AND DATA_BAIXA < '$data_ini'");
$total_ant_fin = !empty($mov_ant_fin) ? floatval($mov_ant_fin[0]['total']) : 0.00;

// B. Busca TODAS as transferências (crédito/débito) desse banco anteriores à data inicial
$tf_ant_saida = $db->Query("SELECT SUM(VALOR) as total FROM TRANSFERENCIAS WHERE ID_BANCO_ORIGEM = $id_banco_sel AND DATA < '$data_ini'");
$total_ant_saida = !empty($tf_ant_saida) ? floatval($tf_ant_saida[0]['total']) : 0.00;

$tf_ant_entrada = $db->Query("SELECT SUM(VALOR) as total FROM TRANSFERENCIAS WHERE ID_BANCO_DESTINO = $id_banco_sel AND DATA < '$data_ini'");
$total_ant_entrada = !empty($tf_ant_entrada) ? floatval($tf_ant_entrada[0]['total']) : 0.00;

// C. Busca histórico anterior de Comissões Recebidas (Entradas) e Repasses aos Corretores (Saídas)
$com_ant_entrada = $db->Query("SELECT SUM(VALOR_COMISSAO_IMOB) as total FROM COMISSAO_IMOB WHERE ID_BANCO_RECEBIMENTO = $id_banco_sel AND DATA_RECEBIMENTO < '$data_ini'");
$total_com_ant_entrada = !empty($com_ant_entrada) ? floatval($com_ant_entrada[0]['total']) : 0.00;

$com_ant_saida = $db->Query("SELECT SUM(VALOR_COMISSAO_CORRETOR) as total FROM COMISSAO_IMOB WHERE ID_BANCO_PAGAMENTO_CORRETOR = $id_banco_sel AND DATA_PAGAMENTO_CORRETOR < '$data_ini'");
$total_com_ant_saida = !empty($com_ant_saida) ? floatval($com_ant_saida[0]['total']) : 0.00;

// O Saldo no primeiro dia do período é a soma algébrica de todo o passado histórico estruturado
$saldo_inicial_periodo = $saldo_inicial_cadastro + $total_ant_fin - $total_ant_saida + $total_ant_entrada + $total_com_ant_entrada - $total_com_ant_saida;

// 3. CAPTURA DOS LANÇAMENTOS DENTRO DO PERÍODO INFORMADO
$extrato = [];

// Baixas normais do módulo financeiro
$res_fin = $db->Query("SELECT DATA_BAIXA as DATA, DOCUMENTO as HISTORICO, CASE WHEN PAGAR_RECEBER = 'R' THEN VALOR_BAIXA ELSE -VALOR_BAIXA END as VALOR, 'FIN' as ORIGEM FROM FINANCEIRO_MOV WHERE ID_BANCO = $id_banco_sel AND DATA_BAIXA BETWEEN '$data_ini' AND '$data_fim'");
if (!empty($res_fin)) $extrato = array_merge($extrato, $res_fin);

// Transferências enviadas (Saídas)
$res_tf_sai = $db->Query("SELECT DATA, CONCAT('Transferência efetuada para Banco ID: ', ID_BANCO_DESTINO, ' - ', OBSERVACAO) as HISTORICO, -VALOR as VALOR, 'TFS' as ORIGEM FROM TRANSFERENCIAS WHERE ID_BANCO_ORIGEM = $id_banco_sel AND DATA BETWEEN '$data_ini' AND '$data_fim'");
if (!empty($res_tf_sai)) $extrato = array_merge($extrato, $res_tf_sai);

// Transferências recebidas (Entradas)
$res_tf_ent = $db->Query("SELECT DATA, CONCAT('Transferência recebida do Banco ID: ', ID_BANCO_ORIGEM, ' - ', OBSERVACAO) as HISTORICO, VALOR as VALOR, 'TFE' as ORIGEM FROM TRANSFERENCIAS WHERE ID_BANCO_DESTINO = $id_banco_sel AND DATA BETWEEN '$data_ini' AND '$data_fim'");
if (!empty($res_tf_ent)) $extrato = array_merge($extrato, $res_tf_ent);

// Comissões Recebidas pela Imobiliária (Entradas - CMI)
$res_com_imob = $db->Query("SELECT co.DATA_RECEBIMENTO as DATA, CONCAT('Faturamento Comissão - Cliente: ', cl.nome) as HISTORICO, co.VALOR_COMISSAO_IMOB as VALOR, 'CMI' as ORIGEM FROM COMISSAO_IMOB co INNER JOIN clientes cl ON co.id_cliente = cl.id_cliente WHERE co.ID_BANCO_RECEBIMENTO = $id_banco_sel AND co.DATA_RECEBIMENTO BETWEEN '$data_ini' AND '$data_fim'");
if (!empty($res_com_imob)) $extrato = array_merge($extrato, $res_com_imob);

// Comissões Pagas de Repasse ao Corretor (Saídas - CMC)
$res_com_corr = $db->Query("SELECT co.DATA_PAGAMENTO_CORRETOR as DATA, CONCAT('Repasse Comissão efetuado ao Corretor: ', u.nome) as HISTORICO, -co.VALOR_COMISSAO_CORRETOR as VALOR, 'CMC' as ORIGEM FROM COMISSAO_IMOB co INNER JOIN usuarios u ON co.id_corretor = u.id_usuario WHERE co.ID_BANCO_PAGAMENTO_CORRETOR = $id_banco_sel AND co.DATA_PAGAMENTO_CORRETOR BETWEEN '$data_ini' AND '$data_fim'");
if (!empty($res_com_corr)) $extrato = array_merge($extrato, $res_com_corr);

// Ordena o extrato mesclado por ordem cronológica de data
usort($extrato, function($a, $b) { return strcmp($a['DATA'], $b['DATA']); });
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Extrato Bancário & Caixa - PrimeSys Imob</title>
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

    <div class="max-w-6xl mx-auto py-10 px-4">
        
        <!-- Alerts Contábeis -->
        <?php if(isset($_GET['erro']) && $_GET['erro'] === 'mesmo_banco'): ?>
            <div class="mb-6 p-4 rounded-lg bg-red-500 text-white shadow-md flex items-center gap-2 text-xs font-bold">
                <i class="fas fa-exclamation-triangle text-xl"></i>
                <span>Operação Recusada: O caixa de destino da transferência não pode ser idêntico ao caixa de origem!</span>
            </div>
        <?php endif; ?>
        <?php if(isset($_GET['msg']) && $_GET['msg'] === 'tf_sucesso'): ?>
            <div class="mb-6 p-4 rounded-lg bg-green-500 text-white shadow-md flex items-center gap-2 text-xs font-bold">
                <i class="fas fa-check-circle text-xl"></i>
                <span>Transferência bancária liquidada e distribuída com sucesso!</span>
            </div>
        <?php endif; ?>

        <!-- Cabeçalho Operacional de Filtro -->
        <div class="bg-white p-6 rounded-2xl shadow-md border border-slate-200 mb-6 select-none">
            <form action="" method="GET" class="flex flex-col md:flex-row items-end gap-4 text-xs font-bold">
                <div class="flex-1 w-full">
                    <label class="block text-slate-600 uppercase mb-1">Selecionar Conta / Banco</label>
                    <select name="id_banco" required class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-800 focus:ring-2 focus:ring-indigo-500">
                        <?php foreach($bancos as $b) echo "<option value='{$b['ID_BANCO']}' ".($id_banco_sel==$b['ID_BANCO']?'selected':'').">{$b['DESC_BANCO']}</option>"; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-600 uppercase mb-1">Período De</label>
                    <input type="date" name="data_ini" value="<?php echo $data_ini; ?>" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 text-slate-700">
                </div>
                <div>
                    <label class="block text-slate-600 uppercase mb-1">Até</label>
                    <input type="date" name="data_fim" value="<?php echo $data_fim; ?>" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 text-slate-700">
                </div>
                <div class="flex gap-2 w-full md:w-auto">
                    <button type="submit" class="btn-3d bg-slate-800 hover:bg-slate-900 text-white px-5 py-2.5 rounded-lg text-xs h-10 w-full md:w-auto">Visualizar</button>
                    <button type="button" onclick="abrirModalTransferencia()" class="btn-3d bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-lg text-xs h-10 flex items-center justify-center gap-1.5 whitespace-nowrap w-full md:w-auto">
                        <i class="fa-solid fa-money-bill-transfer"></i> Transferência
                    </button>
                </div>
            </form>
        </div>

        <!-- Painel de Saldos Consolidados do Período -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 select-none">
            <div class="bg-white p-5 rounded-xl border border-slate-200 flex justify-between items-center shadow-sm">
                <div>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Saldo Inicial no Período</span>
                    <h3 class="text-xl font-black mt-0.5 <?php echo $saldo_inicial_periodo >= 0 ? 'text-slate-800':'text-red-600'; ?>">
                        R$ <?php echo number_format($saldo_inicial_periodo, 2, ',', '.'); ?>
                    </h3>
                </div>
                <i class="fa-solid fa-clock-rotate-left text-2xl text-slate-300"></i>
            </div>
            
            <?php 
                // Calcula o saldo final somando as variações ocorridas no período
                $variacao_periodo = array_sum(array_column($extrato, 'VALOR'));
                $saldo_final_periodo = $saldo_inicial_periodo + $variacao_periodo;
            ?>
            <div class="bg-white p-5 rounded-xl border border-slate-200 flex justify-between items-center shadow-sm">
                <div>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Saldo Final Conciliado</span>
                    <h3 class="text-xl font-black mt-0.5 <?php echo $saldo_final_periodo >= 0 ? 'text-emerald-600':'text-red-600'; ?>">
                        R$ <?php echo number_format($saldo_final_periodo, 2, ',', '.'); ?>
                    </h3>
                </div>
                <i class="fa-solid fa-vault text-2xl text-slate-300"></i>
            </div>
        </div>

        <!-- Tabela do Extrato Cronológico -->
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 text-xs font-bold uppercase tracking-wider">
                            <th class="py-4 px-6 w-32">Data Mov.</th>
                            <th class="py-4 px-6">Histórico Contábil / Descrição</th>
                            <th class="py-4 px-6 text-center w-28">Origem</th>
                            <th class="py-4 px-6 text-right w-44">Valor</th>
                            <th class="py-4 px-6 text-right w-44">Saldo Disponível</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <!-- Primeira Linha: Saldo de Abertura -->
                        <tr class="bg-slate-50/50 italic text-xs text-slate-500 font-medium">
                            <td class="py-3 px-6"><?php echo date('d/m/Y', strtotime($data_ini)); ?></td>
                            <td class="py-3 px-6 text-slate-400">SALDO ANTERIOR TRANSPORTADO DO CAIXA</td>
                            <td class="py-3 px-6 text-center">---</td>
                            <td class="py-3 px-6 text-right">---</td>
                            <td class="py-3 px-6 text-right font-bold text-slate-600">R$ <?php echo number_format($saldo_inicial_periodo, 2, ',', '.'); ?></td>
                        </tr>

                        <?php 
                        $saldo_acumulado_rodape = $saldo_inicial_periodo;
                        if(!empty($extrato)): foreach($extrato as $lin): 
                            $saldo_acumulado_rodape += floatval($lin['VALOR']);
                            $is_entrada = floatval($lin['VALOR']) >= 0;
                        ?>
                            <tr class="hover:bg-slate-50/40 transition-colors">
                                <td class="py-4 px-6 font-mono text-xs text-slate-600"><?php echo date('d/m/Y', strtotime($lin['DATA'])); ?></td>
                                <td class="py-4 px-6 font-semibold text-slate-800"><?php echo htmlspecialchars($lin['HISTORICO']); ?></td>
                                <td class="py-4 px-6 text-center text-[10px] font-bold uppercase tracking-wider">
                                    <span class="px-2 py-0.5 rounded bg-slate-100 border text-slate-500"><?php echo $lin['ORIGEM']; ?></span>
                                </td>
                                <td class="py-4 px-6 text-right font-bold <?php echo $is_entrada ? 'text-emerald-600':'text-red-500'; ?>">
                                    <?php echo $is_entrada ? '+' : ''; ?>R$ <?php echo number_format(abs($lin['VALOR']), 2, ',', '.'); ?>
                                </td>
                                <td class="py-4 px-6 text-right font-black text-slate-700">
                                    R$ <?php echo number_format($saldo_acumulado_rodape, 2, ',', '.'); ?>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="5" class="py-12 text-center text-slate-400 italic">Nenhum fluxo financeiro registrado neste intervalo de datas.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL DE LANÇAMENTO DE TRANSFERÊNCIA ENTRE CAIXAS -->
    <div id="modalTransferencia" class="fixed inset-0 bg-slate-900 bg-opacity-50 hidden backdrop-blur-sm justify-center items-center z-50">
        <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-md w-full mx-4 border">
            <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-money-bill-transfer text-indigo-600"></i> Nova Transferência Bancária
            </h3>
            
            <form id="form_tf" action="" method="POST" onsubmit="return validarBancosTransferencia()" class="space-y-4 text-xs font-bold">
                <input type="hidden" name="action_transferencia" value="1">
                
                <div>
                    <label class="block text-slate-600 uppercase mb-1">Data da Transferência</label>
                    <input type="date" name="data_tf" required value="<?php echo date('Y-m-d'); ?>" class="w-full p-2.5 border rounded-lg text-sm font-medium">
                </div>

                <div>
                    <label class="block text-slate-600 uppercase mb-1">Caixa / Banco de Origem (Saída)</label>
                    <select id="banco_origem" name="banco_origem" required class="w-full p-2.5 bg-white border rounded-lg text-sm font-medium">
                        <option value="">-- Selecione a Origem --</option>
                        <?php foreach($bancos as $b) echo "<option value='{$b['ID_BANCO']}'>{$b['DESC_BANCO']}</option>"; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-slate-600 uppercase mb-1">Caixa / Banco de Destino (Entrada)</label>
                    <select id="banco_destino" name="banco_destino" required class="w-full p-2.5 bg-white border rounded-lg text-sm font-medium">
                        <option value="">-- Selecione o Destino --</option>
                        <?php foreach($bancos as $b) echo "<option value='{$b['ID_BANCO']}'>{$b['DESC_BANCO']}</option>"; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-slate-600 uppercase mb-1">Valor da Transferência</label>
                    <input type="text" id="valor_tf" name="valor_tf" required class="w-full p-2.5 border rounded-lg text-right text-base text-slate-900 bg-amber-50 border-amber-300 font-black">
                </div>

                <div>
                    <label class="block text-slate-600 uppercase mb-1">Observações / Justificativa</label>
                    <input type="text" name="observacao" placeholder="Ex: Ajuste de saldo entre contas da imobiliária" class="w-full p-2.5 border rounded-lg font-medium text-sm">
                </div>

                <div class="flex justify-end gap-2 pt-4 border-t">
                    <button type="button" onclick="fecharModalTransferencia()" class="bg-slate-200 text-slate-700 px-4 py-2 rounded-lg text-xs">Cancelar</button>
                    <button type="submit" class="btn-3d bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg text-xs">Salvar Transferência</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModalTransferencia() { document.getElementById('modalTransferencia').style.display = 'flex'; }
        function fecharModalTransferencia() { document.getElementById('modalTransferencia').style.display = 'none'; }

        IMask(document.getElementById('valor_tf'), { mask: Number, scale: 2, signed: false, thousandsSeparator: '.', padFractionalZeros: true, radix: ',' });

        function validarBancosTransferencia() {
            const orig = document.getElementById('banco_origem').value;
            const dest = document.getElementById('banco_destino').value;

            if (orig === dest && orig !== "") {
                alert('Operação Negada! O caixa de destino não pode ser igual ao caixa de origem.');
                return false; 
            }
            return true;
        }
    </script>
</body>
</html>