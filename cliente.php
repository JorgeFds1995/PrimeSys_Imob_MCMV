<?php
session_start();
require_once 'Database.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$db = new Database();

// 1. CARREGA LISTAS DINÂMICAS PARA AS COMBOBOXES
$lista_corretores = $db->Query("SELECT id_usuario, nome FROM usuarios ORDER BY nome ASC");
$lista_profissoes = $db->Query("SELECT profissao FROM profissoes ORDER BY profissao ASC");

// Inicialização de variáveis para edição
$id_cliente = isset($_GET['id']) ? intval($_GET['id']) : null;
$cliente_atual = null;
$erro_duplicidade = '';

if ($id_cliente) {
    $sql = "SELECT * FROM clientes WHERE id_cliente = $id_cliente LIMIT 1";
    $resultado = $db->Query($sql);
    if (!empty($resultado)) {
        $cliente_atual = $resultado[0];
    }
}

// 2. BUSCA EMPREENDIMENTOS (Ativos ou o que já está associado ao cliente antigo)
$id_emp_vinc = isset($cliente_atual['id_empreendimento']) ? intval($cliente_atual['id_empreendimento']) : 0;
$sql_emp = "SELECT id_empreendimento, nome_empreendimento, valor FROM empreendimentos WHERE ativo = 1";
if ($id_emp_vinc > 0) {
    $sql_emp .= " OR id_empreendimento = $id_emp_vinc";
}
$sql_emp .= " ORDER BY nome_empreendimento ASC";
$lista_empreendimentos = $db->Query($sql_emp);

// Processamento do Formulário (Salvar / Atualizar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitização e Captura de Strings
    $nome = str_replace("'", "''", trim($_POST['nome']));
    $rg = str_replace("'", "''", trim($_POST['rg']));
    $endereco = str_replace("'", "''", trim($_POST['endereco']));
    $email = str_replace("'", "''", trim($_POST['email']));
    $nacionalidade = str_replace("'", "''", trim($_POST['nacionalidade']));
    $profissao = str_replace("'", "''", $_POST['profissao']);
    $empresa = str_replace("'", "''", trim($_POST['empresa']));
    $status_lead = str_replace("'", "''", $_POST['status_lead']);
    $origem_lead = str_replace("'", "''", $_POST['origem_lead']);
    $observacao = str_replace("'", "''", trim($_POST['observacao']));
    $entrada_parcelada = str_replace("'", "''", $_POST['entrada_parcelada']);
    
    // Proponentes Secundários
    $proponente2 = str_replace("'", "''", trim($_POST['proponente2']));
    $proponente3 = str_replace("'", "''", trim($_POST['proponente3']));

    // Chaves Estrangeiras (IDs)
    $id_corretor = !empty($_POST['id_corretor']) ? intval($_POST['id_corretor']) : 'NULL';
    $id_empreendimento = !empty($_POST['id_empreendimento']) ? intval($_POST['id_empreendimento']) : 'NULL';

    // Limpeza de Máscaras em CPFs e Telefones
    $cpf = preg_replace('/\D/', '', $_POST['cpf']);
    $cpf2 = preg_replace('/\D/', '', $_POST['cpf2']);
    $cpf3 = preg_replace('/\D/', '', $_POST['cpf3']);
    $telefone1 = preg_replace('/\D/', '', $_POST['telefone1']);
    $telefone2 = preg_replace('/\D/', '', $_POST['telefone2']);

    $id_edicao = !empty($_POST['id_cliente']) ? intval($_POST['id_cliente']) : null;

    // VALIDAÇÃO DE DUPLICIDADE (CPF ou Telefone1)
    $condicao_edicao = $id_edicao ? " AND id_cliente != $id_edicao" : "";
    
    // Verifica apenas se os campos informados não estiverem vazios
    $sql_valida = "SELECT c.*, u.nome as nome_corretor_existente 
                   FROM clientes c 
                   LEFT JOIN usuarios u ON c.id_corretor = u.id_usuario 
                   WHERE (1=0";
    
    if (!empty($cpf)) {
        $sql_valida .= " OR c.cpf = '$cpf'";
    }
    if (!empty($telefone1)) {
        $sql_valida .= " OR c.telefone1 = '$telefone1'";
    }
    $sql_valida .= ") $condicao_edicao LIMIT 1";

    $duplicado = $db->Query($sql_valida);

    if (!empty($duplicado)) {
        $nome_corretor_resp = !empty($duplicado[0]['nome_corretor_existente']) ? $duplicado[0]['nome_corretor_existente'] : 'outro corretor';
        $erro_duplicidade = "O lead já existe com outro corretor (" . $nome_corretor_resp . ").";
    } else {

        // Tratamento de Datas (Converte para o formato MySQL YYYY-MM-DD ou salva NULL)
        $data_nasc = !empty($_POST['data_nasc']) ? "'".$_POST['data_nasc']."'" : 'NULL';
        $data_venda = !empty($_POST['data_venda']) ? "'".$_POST['data_venda']."'" : 'NULL';
        $data_desligamento = !empty($_POST['data_desligamento']) ? "'".$_POST['data_desligamento']."'" : 'NULL';
        $data_encerramento = !empty($_POST['data_encerramento']) ? "'".$_POST['data_encerramento']."'" : 'NULL';
        
        // Datas automáticas de controle
        $data_hoje = date('Y-m-d');
        $data_ultima_mov = "'$data_hoje'";

        // Tratamento de Campos Numéricos/Decimais
        $fun_num = function($val) { return !empty($val) ? str_replace(['.', ','], ['', '.'], $val) : 0.00; };
        $renda = $fun_num($_POST['renda']); $renda2 = $fun_num($_POST['renda2']); $renda3 = $fun_num($_POST['renda3']);
        $valor_imovel = $fun_num($_POST['valor_imovel']); $valor_venda = $fun_num($_POST['valor_venda']);
        $desconto_construtora = $fun_num($_POST['desconto_construtora']); $desconto_federal = $fun_num($_POST['desconto_federal']);
        $desconto_estadual = $fun_num($_POST['desconto_estadual']); $fgts = $fun_num($_POST['fgts']);
        $outros_descontos = $fun_num($_POST['outros_descontos']); $valor_entrada = $fun_num($_POST['valor_entrada']);
        $valor_financiar = $fun_num($_POST['valor_financiar']); $valor_parcela = $fun_num($_POST['valor_parcela']);
        $vgv = $fun_num($_POST['vgv']); $vlr_parcela_entrada = $fun_num($_POST['vlr_parcela_entrada']);

        // Inteiros Simples
        $parcelas_entrada = !empty($_POST['parcelas_entrada']) ? intval($_POST['parcelas_entrada']) : 0;
        $qtd_parcelas = !empty($_POST['qtd_parcelas']) ? intval($_POST['qtd_parcelas']) : 0;

        // Booleans Checkboxes
        $ok_documentos = isset($_POST['ok_documentos']) ? 1 : 0;
        $ok_fiador = isset($_POST['ok_fiador']) ? 1 : 0;
        $ok_confissao_divida = isset($_POST['ok_confissao_divida']) ? 1 : 0;

        if ($id_edicao) {
            // --- MODO EDIÇÃO ---
            $sql = "UPDATE clientes SET 
                        nome='$nome', rg='$rg', cpf='$cpf', endereco='$endereco', telefone1='$telefone1', telefone2='$telefone2', 
                        email='$email', data_nasc=$data_nasc, nacionalidade='$nacionalidade', profissao='$profissao', renda=$renda, 
                        empresa='$empresa', proponente2='$proponente2', cpf2='$cpf2', renda2=$renda2, proponente3='$proponente3', 
                        cpf3='$cpf3', renda3=$renda3, id_corretor=$id_corretor, status_lead='$status_lead', origem_lead='$origem_lead', 
                        observacao='$observacao', id_empreendimento=$id_empreendimento, valor_imovel=$valor_imovel, valor_venda=$valor_venda, 
                        desconto_construtora=$desconto_construtora, desconto_federal=$desconto_federal, desconto_estadual=$desconto_estadual, 
                        fgts=$fgts, outros_descontos=$outros_descontos, valor_entrada=$valor_entrada, valor_financiar=$valor_financiar, 
                        valor_parcela=$valor_parcela, vgv=$vgv, entrada_parcelada='$entrada_parcelada', vlr_parcela_entrada=$vlr_parcela_entrada, 
                        parcelas_entrada=$parcelas_entrada, qtd_parcelas=$qtd_parcelas, data_venda=$data_venda, data_desligamento=$data_desligamento, 
                        data_encerramento=$data_encerramento, data_ultima_mov=$data_ultima_mov, ok_documentos=$ok_documentos, ok_fiador=$ok_fiador, 
                        ok_confissao_divida=$ok_confissao_divida 
                    WHERE id_cliente = $id_edicao";
            $db->Query($sql);
            
            // --- GATILHO DE COMISSÃO AUTOMÁTICA (Adicionar no processamento do POST de cliente.php) ---
            if ($status_lead === 'Nota Anexada') {
                // 1. Verifica se já não existe uma comissão gerada para este cliente para evitar duplicidade
                $id_cliente_verificar = $id_edicao ? $id_edicao : $db->Query("SELECT LAST_INSERT_ID() as id")[0]['id'];
                $check_comissao = $db->Query("SELECT id_comissao FROM comissao_imob WHERE id_cliente = $id_cliente_verificar LIMIT 1");
                
                if (empty($check_comissao) && !empty($id_empreendimento) && !empty($id_corretor)) {
                    // 2. Busca o percentual de comissão e a construtora do Empreendimento
                    $dados_emp = $db->Query("SELECT id_incorporadora, percentual_comissao FROM empreendimentos WHERE id_empreendimento = $id_empreendimento LIMIT 1");
                    
                    // 3. Busca o percentual de comissão do Corretor (tabela usuarios)
                    $dados_corr = $db->Query("SELECT percentual_comissao FROM usuarios WHERE id_usuario = $id_corretor LIMIT 1");
                    
                    if (!empty($dados_emp) && !empty($dados_corr)) {
                        $id_construtora = intval($dados_emp[0]['id_incorporadora']);
                        $pct_imob = floatval($dados_emp[0]['percentual_comissao']);
                        $pct_corretor = floatval($dados_corr[0]['percentual_comissao']);
                        
                        // 4. Cálculos Matemáticos Requeridos
                        $valor_geral_venda = $valor_venda; // Pega o valor fechado da venda digitado no cliente
                        $valor_comissao_imob = ($valor_geral_venda * $pct_imob) / 100;
                        $valor_comissao_corretor = ($valor_comissao_imob * $pct_corretor) / 100;
                        
                        $data_hoje = date('Y-m-d');
                        
                        // 5. Inserção na tabela comissao_imob
                        $sql_comissao = "INSERT INTO comissao_imob (
                                            id_empreendimento, id_construtora, id_cliente, id_corretor, 
                                            data_emissao, data_vencimento, valor_geral_venda, 
                                            valor_comissao_imob, valor_comissao_corretor
                                        ) VALUES (
                                            $id_empreendimento, $id_construtora, $id_cliente_verificar, $id_corretor, 
                                            '$data_hoje', '$data_hoje', $valor_geral_venda, 
                                            $valor_comissao_imob, $valor_comissao_corretor
                                        )";
                        $db->Query($sql_comissao);
                    }
                }
                
                // --- AUTOMAÇÃO DA DATA DE VENDA ATUAL (Adicionar no tratamento do POST em cliente.php) ---

                // Se o status du lead for alterado para 'Venda Fechada', força a data da venda a ser hoje
                if ($status_lead === 'Venda Fechada') {
                   $data_venda = "'" . date('Y-m-d') . "'";
                } else {
                   // Caso contrário, respeita o que foi preenchido ou mantém NULL se estiver vazio
                   $data_venda = !empty($_POST['data_venda']) ? "'" . $_POST['data_venda'] . "'" : 'NULL';
                }


            }
            
            header("Location: cliente.php?id=$id_edicao&msg=sucesso");
            exit;
        } else {
            // --- MODO CADASTRO ---
            
            // Se o status do lead for alterado para 'Venda Fechada', força a data da venda a ser hoje
            if ($status_lead === 'Venda Fechada') {
               $data_venda = "'" . date('Y-m-d') . "'";
            } else {
               // Caso contrário, respeita o que foi preenchido ou mantém NULL se estiver vazio
               $data_venda = !empty($_POST['data_venda']) ? "'" . $_POST['data_venda'] . "'" : 'NULL';
            }
            
            $sql = "INSERT INTO clientes (nome, rg, cpf, endereco, telefone1, telefone2, email, data_nasc, nacionalidade, profissao, renda, empresa, proponente2, cpf2, renda2, proponente3, cpf3, renda3, id_corretor, status_lead, origem_lead, observacao, id_empreendimento, valor_imovel, valor_venda, desconto_construtora, desconto_federal, desconto_estadual, fgts, outros_descontos, valor_entrada, valor_financiar, valor_parcela, vgv, entrada_parcelada, vlr_parcela_entrada, parcelas_entrada, qtd_parcelas, data_venda, data_cadastro, data_ultima_mov, ok_documentos, ok_fiador, ok_confissao_divida) 
                    VALUES ('$nome', '$rg', '$cpf', '$endereco', '$telefone1', '$telefone2', '$email', $data_nasc, '$nacionalidade', '$profissao', $renda, '$empresa', '$proponente2', '$cpf2', $renda2, '$proponente3', '$cpf3', $renda3, $id_corretor, '$status_lead', '$origem_lead', '$observacao', $id_empreendimento, $valor_imovel, $valor_venda, $desconto_construtora, $desconto_federal, $desconto_estadual, $fgts, $outros_descontos, $valor_entrada, $valor_financiar, $valor_parcela, $vgv, '$entrada_parcelada', $vlr_parcela_entrada, $parcelas_entrada, $qtd_parcelas, $data_venda, '$data_hoje', $data_ultima_mov, $ok_documentos, $ok_fiador, $ok_confissao_divida)";
            $db->Query($sql);
            
            // --- GATILHO DE COMISSÃO AUTOMÁTICA (Adicionar no processamento do POST de cliente.php) ---
            if ($status_lead === 'Nota Anexada') {
                // 1. Verifica se já não existe uma comissão gerada para este cliente para evitar duplicidade
                $id_cliente_verificar = $id_edicao ? $id_edicao : $db->Query("SELECT LAST_INSERT_ID() as id")[0]['id'];
                $check_comissao = $db->Query("SELECT id_comissao FROM comissao_imob WHERE id_cliente = $id_cliente_verificar LIMIT 1");
                
                if (empty($check_comissao) && !empty($id_empreendimento) && !empty($id_corretor)) {
                    // 2. Busca o percentual de comissão e a construtora do Empreendimento
                    $dados_emp = $db->Query("SELECT id_incorporadora, percentual_comissao FROM empreendimentos WHERE id_empreendimento = $id_empreendimento LIMIT 1");
                    
                    // 3. Busca o percentual de comissão do Corretor (tabela usuarios)
                    $dados_corr = $db->Query("SELECT percentual_comissao FROM usuarios WHERE id_usuario = $id_corretor LIMIT 1");
                    
                    if (!empty($dados_emp) && !empty($dados_corr)) {
                        $id_construtora = intval($dados_emp[0]['id_incorporadora']);
                        $pct_imob = floatval($dados_emp[0]['percentual_comissao']);
                        $pct_corretor = floatval($dados_corr[0]['percentual_comissao']);
                        
                        // 4. Cálculos Matemáticos Requeridos
                        $valor_geral_venda = $valor_venda; // Pega o valor fechado da venda digitado no cliente
                        $valor_comissao_imob = ($valor_geral_venda * $pct_imob) / 100;
                        $valor_comissao_corretor = ($valor_comissao_imob * $pct_corretor) / 100;
                        
                        $data_hoje = date('Y-m-d');
                        
                        // 5. Inserção na tabela comissao_imob
                        $sql_comissao = "INSERT INTO comissao_imob (
                                            id_empreendimento, id_construtora, id_cliente, id_corretor, 
                                            data_emissao, data_vencimento, valor_geral_venda, 
                                            valor_comissao_imob, valor_comissao_corretor
                                        ) VALUES (
                                            $id_empreendimento, $id_construtora, $id_cliente_verificar, $id_corretor, 
                                            '$data_hoje', '$data_hoje', $valor_geral_venda, 
                                            $valor_comissao_imob, $valor_comissao_corretor
                                        )";
                        $db->Query($sql_comissao);
                    }
                }
            }
            
            header("Location: clientes_lista.php?msg=cadastrado");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Lead/Cliente - PrimeSys Imob</title>
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
        
        <?php if(!empty($erro_duplicidade)): ?>
            <div class="mb-6 p-4 rounded-lg bg-red-500 text-white shadow-md flex items-center gap-2">
                <i class="fas fa-exclamation-triangle text-xl"></i>
                <span><?php echo htmlspecialchars($erro_duplicidade); ?></span>
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['msg'])): ?>
            <div class="mb-6 p-4 rounded-lg bg-green-500 text-white shadow-md flex items-center gap-2">
                <i class="fas fa-check-circle text-xl"></i>
                <span>Ficha do Lead/Cliente atualizada perfeitamente!</span>
            </div>
        <?php endif; ?>

        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">
                    <i class="fa-solid fa-address-book text-indigo-600 mr-2"></i>
                    <?php echo $cliente_atual ? 'Editar Lead / Cliente' : 'Novo Lead (Prospecção)'; ?>
                </h1>
                <p class="text-sm text-slate-500 mt-1">Gerencie o pipeline, composições de renda, simulações financeiras e vendas completas.</p>
            </div>
            <a href="clientes_lista.php" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                <i class="fa-solid fa-arrow-left"></i> Voltar para a lista
            </a>
        </div>

        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?><?php echo $cliente_atual ? '?id='.$id_cliente : ''; ?>" method="POST" class="space-y-6">
            <?php if($cliente_atual): ?>
                <input type="hidden" name="id_cliente" value="<?php echo $cliente_atual['id_cliente']; ?>">
            <?php endif; ?>

            <div class="bg-white p-8 rounded-2xl shadow-xl border border-slate-200 space-y-8">
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Status do Funil (Lead)</label>
                        <select name="status_lead" required class="w-full p-2.5 bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <?php 
                            $status_opcoes = ['Lead', 'Atendimento', 'Aguardando Documentos', 'Perseguição', 'Análise Documento', 'Aprovado', 'Reprovado', 'Condicionado', 'Restrição', 'BACEN', 'Rating', 'Negociação', 'Perdido', 'Venda Fechada', 'Desligamento', 'Nota Anexada', 'Comissão Recebida'];
                            foreach($status_opcoes as $op){
                                $sel = (isset($cliente_atual['status_lead']) && $cliente_atual['status_lead'] == $op) ? 'selected' : (($op=='Lead' && !$cliente_atual)?'selected':'');
                                echo "<option value='$op' $sel>$op</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Origem do Lead</label>
                        <select name="origem_lead" required class="w-full p-2.5 bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <option value="">-- Selecione a Origem --</option>
                            <?php 
                            $origem_opcoes = ['Campanha', 'Instagram', 'Marketplace', 'WhatsApp', 'Site', 'Google', 'Lista', 'Carta', 'Panfleto', 'Outdoor', 'Banner', 'Indicação'];
                            foreach($origem_opcoes as $op){
                                $sel = (isset($cliente_atual['origem_lead']) && $cliente_atual['origem_lead'] == $op) ? 'selected' : '';
                                echo "<option value='$op' $sel>$op</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Corretor Responsável</label>
                        <select name="id_corretor" required class="w-full p-2.5 bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <?php 
                            foreach($lista_corretores as $corretor){
                                // Se for novo cadastro, pré-seleciona automaticamente o id do usuário logado na sessão
                                $id_padrao = $cliente_atual ? $cliente_atual['id_corretor'] : $_SESSION['usuario_id'];
                                $sel = ($id_padrao == $corretor['id_usuario']) ? 'selected' : '';
                                echo "<option value='{$corretor['id_usuario']}' $sel>{$corretor['nome']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="flex items-center justify-around pt-4 md:pt-0 border-t md:border-t-0 md:border-l border-slate-200">
                        <label class="flex items-center gap-1.5 cursor-pointer text-xs font-bold text-slate-600 uppercase">
                            <input type="checkbox" name="ok_documentos" class="rounded text-indigo-600" <?php echo isset($cliente_atual['ok_documentos']) && $cliente_atual['ok_documentos'] ? 'checked' : ''; ?>> Doc OK
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer text-xs font-bold text-slate-600 uppercase">
                            <input type="checkbox" name="ok_fiador" class="rounded text-indigo-600" <?php echo isset($cliente_atual['ok_fiador']) && $cliente_atual['ok_fiador'] ? 'checked' : ''; ?>> Fiador OK
                        </label>
                    </div>
                </div>

                <h2 class="text-xl font-bold text-indigo-600 border-b pb-2"><i class="fa-solid fa-user mr-2"></i>Dados do Proponente Principal</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nome Completo</label>
                        <input type="text" name="nome" required value="<?php echo $cliente_atual['nome'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">CPF</label>
                        <input type="text" id="cpf" name="cpf" value="<?php echo $cliente_atual['cpf'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="000.000.000-00">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">RG</label>
                        <input type="text" name="rg" value="<?php echo $cliente_atual['rg'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Data de Nascimento</label>
                        <input type="date" name="data_nasc" value="<?php echo $cliente_atual['data_nasc'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nacionalidade</label>
                        <input type="text" name="nacionalidade" value="<?php echo $cliente_atual['nacionalidade'] ?? 'Brasileiro(a)'; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Telefone Principal</label>
                        <input type="text" id="telefone1" name="telefone1" value="<?php echo $cliente_atual['telefone1'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="(00) 00000-0000">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Telefone Recado / Secundário</label>
                        <input type="text" id="telefone2" name="telefone2" value="<?php echo $cliente_atual['telefone2'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="(00) 00000-0000">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">E-mail</label>
                        <input type="email" name="email" value="<?php echo $cliente_atual['email'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="cliente@provedor.com">
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Endereço Completo</label>
                        <input type="text" name="endereco" value="<?php echo $cliente_atual['endereco'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                </div>

                <h2 class="text-xl font-bold text-indigo-600 border-b pb-2 pt-2"><i class="fa-solid fa-users-rectangle mr-2"></i>Profissão & Análise de Renda Composta</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Profissão Proponente 1</label>
                        <select name="profissao" class="w-full p-2.5 bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <option value="">-- Selecione ou Deixe Vazio --</option>
                            <?php 
                            foreach($lista_profissoes as $prof){
                                $sel = (isset($cliente_atual['profissao']) && $cliente_atual['profissao'] == $prof['profissao']) ? 'selected' : '';
                                echo "<option value='{$prof['profissao']}' $sel>{$prof['profissao']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Empresa Atual</label>
                        <input type="text" name="empresa" value="<?php echo $cliente_atual['empresa'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Renda Comprovada 1</label>
                        <input type="text" id="renda" name="renda" value="<?php echo isset($cliente_atual['renda']) ? number_format($cliente_atual['renda'], 2, ',', '.') : ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-right font-semibold">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nome do Co-Proponente 2</label>
                        <input type="text" name="proponente2" value="<?php echo $cliente_atual['proponente2'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">CPF Proponente 2</label>
                        <input type="text" id="cpf2" name="cpf2" value="<?php echo $cliente_atual['cpf2'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="000.000.000-00">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Renda Proponente 2</label>
                        <input type="text" id="renda2" name="renda2" value="<?php echo isset($cliente_atual['renda2']) ? number_format($cliente_atual['renda2'], 2, ',', '.') : ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-right">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nome do Co-Proponente 3</label>
                        <input type="text" name="proponente3" value="<?php echo $cliente_atual['proponente3'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">CPF Proponente 3</label>
                        <input type="text" id="cpf3" name="cpf3" value="<?php echo $cliente_atual['cpf3'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="000.000.000-00">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Renda Proponente 3</label>
                        <input type="text" id="renda3" name="renda3" value="<?php echo isset($cliente_atual['renda3']) ? number_format($cliente_atual['renda3'], 2, ',', '.') : ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-right">
                    </div>
                </div>

                <h2 class="text-xl font-bold text-indigo-600 border-b pb-2 pt-2"><i class="fa-solid fa-calculator mr-2"></i>Simulação Financeira & Engenharia do Negócio</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Interesse em Empreendimento</label>
                        <select id="id_empreendimento" name="id_empreendimento" onchange="atualizarValorEmpreendimento()" class="w-full p-2.5 bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800 font-semibold">
                            <option value="" data-valor="0,00">-- Nenhum Escolhido --</option>
                            <?php 
                            foreach($lista_empreendimentos as $emp){
                                $sel = (isset($cliente_atual['id_empreendimento']) && $cliente_atual['id_empreendimento'] == $emp['id_empreendimento']) ? 'selected' : '';
                                echo "<option value='{$emp['id_empreendimento']}' data-valor='".number_format($emp['valor'], 2, ',', '.')."' $sel>{$emp['nome_empreendimento']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Valor de Tabela (Imóvel)</label>
                        <input type="text" id="valor_imovel" name="valor_imovel" value="<?php echo isset($cliente_atual['valor_imovel']) ? number_format($cliente_atual['valor_imovel'], 2, ',', '.') : ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-right font-mono text-slate-600" readonly>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Valor Fechado de Venda</label>
                        <input type="text" id="valor_venda" name="valor_venda" value="<?php echo isset($cliente_atual['valor_venda']) ? number_format($cliente_atual['valor_venda'], 2, ',', '.') : ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-right font-black text-slate-900 bg-amber-50 border-amber-300">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Subsídio Federal (MCMV)</label>
                        <input type="text" id="desconto_federal" name="desconto_federal" value="<?php echo isset($cliente_atual['desconto_federal']) ? number_format($cliente_atual['desconto_federal'], 2, ',', '.') : ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-right text-emerald-600">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Subsídio Estadual</label>
                        <input type="text" id="desconto_estadual" name="desconto_estadual" value="<?php echo isset($cliente_atual['desconto_estadual']) ? number_format($cliente_atual['desconto_estadual'], 2, ',', '.') : ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-right text-emerald-600">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Desconto Construtora</label>
                        <input type="text" id="desconto_construtora" name="desconto_construtora" value="<?php echo isset($cliente_atual['desconto_construtora']) ? number_format($cliente_atual['desconto_construtora'], 2, ',', '.') : ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-right">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Uso de Saldo FGTS</label>
                        <input type="text" id="fgts" name="fgts" value="<?php echo isset($cliente_atual['fgts']) ? number_format($cliente_atual['fgts'], 2, ',', '.') : ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-right text-blue-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Valor Total Entrada</label>
                        <input type="text" id="valor_entrada" name="valor_entrada" value="<?php echo isset($cliente_atual['valor_entrada']) ? number_format($cliente_atual['valor_entrada'], 2, ',', '.') : ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-right font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Entrada Parcelada?</label>
                        <select name="entrada_parcelada" class="w-full p-2.5 bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <option value="Não" <?php echo (isset($cliente_atual['entrada_parcelada']) && $cliente_atual['entrada_parcelada'] == 'Não') ? 'selected' : ''; ?>>Não</option>
                            <option value="Sim" <?php echo (isset($cliente_atual['entrada_parcelada']) && $cliente_atual['entrada_parcelada'] == 'Sim') ? 'selected' : ''; ?>>Sim</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Qtd Parc. Entrada</label>
                        <input type="number" name="parcelas_entrada" value="<?php echo $cliente_atual['parcelas_entrada'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-center">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Valor da Parc. Entrada</label>
                        <input type="text" id="vlr_parcela_entrada" name="vlr_parcela_entrada" value="<?php echo isset($cliente_atual['vlr_parcela_entrada']) ? number_format($cliente_atual['vlr_parcela_entrada'], 2, ',', '.') : ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-right">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Valor a Financiar (Banco)</label>
                        <input type="text" id="valor_financiar" name="valor_financiar" value="<?php echo isset($cliente_atual['valor_financiar']) ? number_format($cliente_atual['valor_financiar'], 2, ',', '.') : ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-right font-semibold text-indigo-700">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Prazo Financiamento (Meses)</label>
                        <input type="number" name="qtd_parcelas" value="<?php echo $cliente_atual['qtd_parcelas'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-center">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Vlr Estimado da Parcela</label>
                        <input type="text" id="valor_parcela" name="valor_parcela" value="<?php echo isset($cliente_atual['valor_parcela']) ? number_format($cliente_atual['valor_parcela'], 2, ',', '.') : ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-right">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">VGV Operação</label>
                        <input type="text" id="vgv" name="vgv" value="<?php echo isset($cliente_atual['vgv']) ? number_format($cliente_atual['vgv'], 2, ',', '.') : ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-right font-black">
                    </div>
                </div>

                <h2 class="text-xl font-bold text-indigo-600 border-b pb-2 pt-2"><i class="fa-solid fa-clock-history mr-2"></i>Histórico & Datas de Evolução</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Data do Fechamento (Venda)</label>
                        <input type="date" name="data_venda" value="<?php echo $cliente_atual['data_venda'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Data de Desligamento</label>
                        <input type="date" name="data_desligamento" value="<?php echo $cliente_atual['data_desligamento'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Data Encerramento / Arquivo</label>
                        <input type="date" name="data_Camp" name="data_Camp" value="<?php echo $cliente_atual['data_encerramento'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Histórico de Observações / Interações com o Lead</label>
                        <input type="text" name="observacao" value="<?php echo $cliente_atual['observacao'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="Registros de ligações, propostas enviadas ou motivos de perda do negócio.">
                    </div>
                </div>

                <div class="pt-4 border-t flex justify-end">
                    <button type="submit" class="btn-3d bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold px-8 py-3 rounded-xl shadow-lg flex items-center gap-2">
                        <i class="fas fa-save"></i> Gravar Ficha de Lead
                    </button>
                </div>

            </div>
        </form>
    </div>

    <script>
        // Função para espelhar o valor original do Empreendimento no "Valor do Imóvel" e "Valor de Venda"
        function atualizarValorEmpreendimento() {
            const select = document.getElementById('id_empreendimento');
            const opcaoSelecionada = select.options[select.selectedIndex];
            const valorOrigem = opcaoSelecionada.getAttribute('data-valor');
            
            // Atribui aos inputs correspondentes em tela
            document.getElementById('valor_imovel').value = valorOrigem;
            if(!<?php echo $cliente_atual ? 'true' : 'false'; ?>) {
                document.getElementById('valor_venda').value = valorOrigem;
            }
        }

        // Configuração de Máscaras nos múltiplos campos requeridos
        const setupMask = (id) => IMask(document.getElementById(id), { mask: Number, scale: 2, signed: false, thousandsSeparator: '.', padFractionalZeros: true, radix: ',' });
        const listDecimals = ['renda','renda2','renda3','valor_imovel','valor_venda','desconto_federal','desconto_estadual','desconto_construtora','fgts','valor_entrada','vlr_parcela_entrada','valor_financiar','valor_parcela','vgv'];
        listDecimals.forEach(id => setupMask(id));

        // Máscaras de Textos
        IMask(document.getElementById('cpf'), { mask: '000.000.000-00' });
        IMask(document.getElementById('cpf2'), { mask: '000.000.000-00' });
        IMask(document.getElementById('cpf3'), { mask: '000.000.000-00' });
        IMask(document.getElementById('telefone1'), { mask: [{ mask: '(00) 0000-0000' }, { mask: '(00) 00000-0000' }] });
        IMask(document.getElementById('telefone2'), { mask: [{ mask: '(00) 0000-0000' }, { mask: '(00) 00000-0000' }] });
    </script>
</body>
</html>