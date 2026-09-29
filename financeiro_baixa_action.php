<?php
session_start();
require_once 'Database.php';

if (!isset($_SESSION['usuario_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Acesso negado.');
}

$db = new Database();

$id_mov           = intval($_POST['id_mov']);
$data_baixa       = $_POST['data_baixa'];
$id_banco         = intval($_POST['id_banco']);
$observacao_baixa = str_replace("'", "''", trim($_POST['observacao_baixa']));

// Limpeza das máscaras decimais antes da persistência
$fun_clean = function($val) { return !empty($val) ? str_replace(['.', ','], ['', '.'], $val) : 0.00; };
$desconto    = $fun_clean($_POST['desconto']);
$juros       = $fun_clean($_POST['juros']);
$valor_baixa = $fun_clean($_POST['valor_baixa']);

$sql = "UPDATE FINANCEIRO_MOV SET 
            DESCONTO = $desconto, 
            JUROS = $juros, 
            VALOR_BAIXA = $valor_baixa, 
            DATA_BAIXA = '$data_baixa', 
            OBSERVACAO_BAIXA = '$observacao_baixa', 
            ID_BANCO = $id_banco 
        WHERE ID_FINANCEIRO_MOV = $id_mov";

$db->Query($sql);
header("Location: financeiro_lista.php");
exit;