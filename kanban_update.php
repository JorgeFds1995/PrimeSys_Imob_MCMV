<?php
session_start();
require_once 'Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'erro' => 'Requisição inválida ou não autorizada.']);
    exit;
}

$db = new Database();

$id_cliente = isset($_POST['id_cliente']) ? intval($_POST['id_cliente']) : 0;
$status_lead = isset($_POST['status_lead']) ? trim($_POST['status_lead']) : '';

if ($id_cliente === 0 || empty($status_lead)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Parâmetros ausentes.']);
    exit;
}

$data_hoje = date('Y-m-d');

// 1. REGRA 1: Se o status for 'Venda Fechada', força o preenchimento automático da data_venda
$clausula_data_venda = "";
if ($status_lead === 'Venda Fechada') {
    $clausula_data_venda = ", data_venda = '$data_hoje'";
}

// 2. EXECUTA O UPDATE DO STATUS DO LEAD NO BANCO
$sql_update = "UPDATE clientes SET 
                status_lead = '$status_lead', 
                data_ultima_mov = '$data_hoje'
                $clausula_data_venda 
              WHERE id_cliente = $id_cliente";

$linhas_afetadas = $db->Query($sql_update);

// 3. REGRA 2: Se o status for movido para 'Nota Anexada', dispara o gatilho contábil da comissão
if ($status_lead === 'Nota Anexada') {
    
    // Verifica se já não há lançamento de comissão existente para esse cliente
    $check = $db->Query("SELECT id_comissao FROM comissao_imob WHERE id_cliente = $id_cliente LIMIT 1");
    
    if (empty($check)) {
        // Busca os dados do cliente e as referências financeiras das tabelas conectadas
        $cli_dados = $db->Query("SELECT id_empreendimento, id_corretor, valor_venda FROM clientes WHERE id_cliente = $id_cliente LIMIT 1");
        
        if (!empty($cli_dados) && !empty($cli_dados[0]['id_empreendimento'])) {
            $id_emp  = intval($cli_dados[0]['id_empreendimento']);
            $id_corr = intval($cli_dados[0]['id_corretor']);
            $vlr_venda = floatval($cli_dados[0]['valor_venda']);
            
            // Busca dados do empreendimento (construtora e percentual)
            $dados_emp = $db->Query("SELECT id_incorporadora, percentual_comissao FROM empreendimentos WHERE id_empreendimento = $id_emp LIMIT 1");
            // Busca comissão cadastrada do corretor
            $dados_corr = $db->Query("SELECT percentual_comissao FROM usuarios WHERE id_usuario = $id_corr LIMIT 1");
            
            if (!empty($dados_emp) && !empty($dados_corr)) {
                $id_const    = intval($dados_emp[0]['id_incorporadora']);
                $pct_imob    = floatval($dados_emp[0]['percentual_comissao']);
                $pct_corretor = floatval($dados_corr[0]['percentual_comissao']);
                
                $v_imob      = ($vlr_venda * $pct_imob) / 100;
                $v_corretor  = ($v_imob * $pct_corretor) / 100;
                
                $sql_comissao = "INSERT INTO comissao_imob (id_empreendimento, id_construtora, id_cliente, id_corretor, data_emissao, data_vencimento, valor_geral_venda, valor_comissao_imob, valor_comissao_corretor) 
                                 VALUES ($id_emp, $id_const, $id_cliente, $id_corr, '$data_hoje', '$data_hoje', $vlr_venda, $v_imob, $v_corretor)";
                $db->Query($sql_comissao);
            }
        }
    }
}

// Retorna resposta de sucesso estruturada para o JavaScript
echo json_encode(['sucesso' => true]);
exit;