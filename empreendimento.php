<?php
session_start();
require_once 'Database.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$db = new Database();

// 1. CARREGA AS INCORPORADORAS/CONSTRUTORAS PARA A SELEÇÃO
$sql_incorporadoras = "SELECT id_construtora, nome_fantasia FROM construtoras ORDER BY nome_fantasia ASC";
$lista_incorporadoras = $db->Query($sql_incorporadoras);

// Inicialização de variáveis para edição
$id_empreendimento = isset($_GET['id']) ? intval($_GET['id']) : null;
$empreendimento_atual = null;

if ($id_empreendimento) {
    $sql = "SELECT * FROM empreendimentos WHERE id_empreendimento = $id_empreendimento LIMIT 1";
    $resultado = $db->Query($sql);
    if (!empty($resultado)) {
        $empreendimento_atual = $resultado[0];
    }
}

// Processamento do Formulário (Salvar / Atualizar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_incorporadora = intval($_POST['id_incorporadora']);
    $nome_empreendimento = str_replace("'", "''", trim($_POST['nome_empreendimento']));
    $endereco = str_replace("'", "''", trim($_POST['endereco']));
    $tipo = str_replace("'", "''", $_POST['tipo']);
    $tipo_imovel = str_replace("'", "''", $_POST['tipo_imovel']);
    $observacao = str_replace("'", "''", trim($_POST['observacao']));
    
    // Tratamento de valores decimais vindos das máscaras (ex: 1.500,00 -> 1500.00)
    $area = !empty($_POST['area']) ? str_replace(['.', ','], ['', '.'], $_POST['area']) : 0.00;
    $area_terreno = !empty($_POST['area_terreno']) ? str_replace(['.', ','], ['', '.'], $_POST['area_terreno']) : 0.00;
    $valor = !empty($_POST['valor']) ? str_replace(['.', ','], ['', '.'], $_POST['valor']) : 0.00;
    $percentual_comissao = !empty($_POST['percentual_comissao']) ? str_replace(',', '.', $_POST['percentual_comissao']) : 0.00;

    // Campos Booleanos (Amenidades e Status)
    $pet_place = isset($_POST['pet_place']) ? 1 : 0;
    $garagem = isset($_POST['garagem']) ? 1 : 0;
    $salao_festas = isset($_POST['salao_festas']) ? 1 : 0;
    $area_lazer = isset($_POST['area_lazer']) ? 1 : 0;
    $espaco_gourmet = isset($_POST['espaco_gourmet']) ? 1 : 0;
    $playground = isset($_POST['playground']) ? 1 : 0;
    $portaria = isset($_POST['portaria']) ? 1 : 0;
    $portaria_presencial = isset($_POST['portaria_presencial']) ? 1 : 0;
    $portaria_online = isset($_POST['portaria_online']) ? 1 : 0;
    $elevador = isset($_POST['elevador']) ? 1 : 0;
    $piscina = isset($_POST['piscina']) ? 1 : 0;
    $vaga_carro_eletrico = isset($_POST['vaga_carro_eletrico']) ? 1 : 0;
    $academia = isset($_POST['academia']) ? 1 : 0;
    $quadra_areia = isset($_POST['quadra_areia']) ? 1 : 0;
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    $id_edicao = !empty($_POST['id_empreendimento']) ? intval($_POST['id_empreendimento']) : null;

    if ($id_edicao) {
        // --- MODO EDIÇÃO ---
        $sql = "UPDATE empreendimentos SET 
                    id_incorporadora = $id_incorporadora, nome_empreendimento = '$nome_empreendimento', 
                    endereco = '$endereco', area = $area, area_terreno = $area_terreno, valor = $valor, 
                    percentual_comissao = $percentual_comissao, pet_place = $pet_place, garagem = $garagem, 
                    salao_festas = $salao_festas, area_lazer = $area_lazer, espaco_gourmet = $espaco_gourmet, 
                    playground = $playground, portaria = $portaria, portaria_presencial = $portaria_presencial, 
                    portaria_online = $portaria_online, elevador = $elevador, piscina = $piscina, 
                    vaga_carro_eletrico = $vaga_carro_eletrico, academia = $academia, quadra_areia = $quadra_areia, 
                    tipo = '$tipo', tipo_imovel = '$tipo_imovel', observacao = '$observacao', ativo = $ativo 
                WHERE id_empreendimento = $id_edicao";
        
        $db->Query($sql);
        header("Location: empreendimento.php?id=$id_edicao&msg=sucesso");
        exit;
    } else {
        // --- MODO CADASTRO ---
        $sql = "INSERT INTO empreendimentos (id_incorporadora, nome_empreendimento, endereco, area, area_terreno, valor, percentual_comissao, pet_place, garagem, salao_festas, area_lazer, espaco_gourmet, playground, portaria, portaria_presencial, portaria_online, elevador, piscina, vaga_carro_eletrico, academia, quadra_areia, tipo, tipo_imovel, observacao, ativo) 
                VALUES ($id_incorporadora, '$nome_empreendimento', '$endereco', $area, $area_terreno, $valor, $percentual_comissao, $pet_place, $garagem, $salao_festas, $area_lazer, $espaco_gourmet, $playground, $portaria, $portaria_presencial, $portaria_online, $elevador, $piscina, $vaga_carro_eletrico, $academia, $quadra_areia, '$tipo', '$tipo_imovel', '$observacao', $ativo)";
        
        $db->Query($sql);
        header("Location: empreendimentos_lista.php?msg=cadastrado");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Empreendimento - PrimeSys Imob</title>
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
                <span>Empreendimento atualizado com sucesso!</span>
            </div>
        <?php endif; ?>

        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">
                    <i class="fa-solid fa-building-user text-indigo-600 mr-2"></i>
                    <?php echo $empreendimento_atual ? 'Editar Empreendimento' : 'Novo Empreendimento'; ?>
                </h1>
                <p class="text-sm text-slate-500 mt-1">Gerencie os detalhes estruturais, valores, comissões e diferenciais do projeto.</p>
            </div>
            <a href="empreendimentos_lista.php" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                <i class="fa-solid fa-arrow-left"></i> Voltar para a lista
            </a>
        </div>

        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?><?php echo $empreendimento_atual ? '?id='.$id_empreendimento : ''; ?>" method="POST" class="space-y-6">
            <?php if($empreendimento_atual): ?>
                <input type="hidden" name="id_empreendimento" value="<?php echo $empreendimento_atual['id_empreendimento']; ?>">
            <?php endif; ?>

            <div class="bg-white p-8 rounded-2xl shadow-xl border border-slate-200 space-y-6">
                
                <div class="flex justify-between items-center bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <div>
                        <span class="block text-sm font-bold text-slate-700">Disponibilidade do Empreendimento</span>
                        <span class="text-xs text-slate-400">Empreendimentos inativos ficam ocultos no painel principal de vendas.</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="ativo" class="sr-only peer" <?php echo (!isset($empreendimento_atual) || $empreendimento_atual['ativo']) ? 'checked' : ''; ?>>
                        <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                    </label>
                </div>

                <h2 class="text-xl font-bold text-indigo-600 border-b pb-2"><i class="fa-solid fa-info-circle mr-2"></i>Dados Gerais</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nome do Empreendimento</label>
                        <input type="text" name="nome_empreendimento" required value="<?php echo $empreendimento_atual['nome_empreendimento'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Incorporadora / Construtora</label>
                        <select name="id_incorporadora" required class="w-full p-2.5 bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800">
                            <option value="">-- Selecione a Construtora --</option>
                            <?php 
                            if(!empty($lista_incorporadoras)){
                                foreach($lista_incorporadoras as $inc){
                                    $selected = (isset($empreendimento_atual['id_incorporadora']) && $empreendimento_atual['id_incorporadora'] == $inc['id_construtora']) ? 'selected' : '';
                                    echo "<option value='{$inc['id_construtora']}' {$selected}>{$inc['nome_fantasia']}</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Tipo de Local</label>
                            <select name="tipo" required class="w-full p-2.5 bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800">
                                <option value="Condomínio Fechado" <?php echo (isset($empreendimento_atual['tipo']) && $empreendimento_atual['tipo'] == 'Condomínio Fechado') ? 'selected' : ''; ?>>Condomínio Fechado</option>
                                <option value="Bairro Aberto" <?php echo (isset($empreendimento_atual['tipo']) && $empreendimento_atual['tipo'] == 'Bairro Aberto') ? 'selected' : ''; ?>>Bairro Aberto</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Tipo Imóvel</label>
                            <select name="tipo_imovel" required class="w-full p-2.5 bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800">
                                <option value="Casa" <?php echo (isset($empreendimento_atual['tipo_imovel']) && $empreendimento_atual['tipo_imovel'] == 'Casa') ? 'selected' : ''; ?>>Casa</option>
                                <option value="Apartamento" <?php echo (isset($empreendimento_atual['tipo_imovel']) && $empreendimento_atual['tipo_imovel'] == 'Apartamento') ? 'selected' : ''; ?>>Apartamento</option>
                            </select>
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Endereço da Obra / Empreendimento</label>
                        <input type="text" name="endereco" value="<?php echo $empreendimento_atual['endereco'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800">
                    </div>
                </div>

                <h2 class="text-xl font-bold text-indigo-600 border-b pb-2 pt-2"><i class="fa-solid fa-coins mr-2"></i>Valores e Dimensões</h2>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Valor do Imóvel (A partir de)</label>
                        <div class="relative">
                            <span class="absolute left-3 top-2.5 text-slate-400 font-bold text-sm">R$</span>
                            <input type="text" id="valor" name="valor" value="<?php echo isset($empreendimento_atual['valor']) ? number_format($empreendimento_atual['valor'], 2, ',', '.') : ''; ?>" class="w-full p-2.5 pl-9 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800 text-right">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Comissão Repassada (%)</label>
                        <div class="relative">
                            <input type="text" id="comissao" name="percentual_comissao" value="<?php echo isset($empreendimento_atual['percentual_comissao']) ? number_format($empreendimento_atual['percentual_comissao'], 2, ',', '') : '0,00'; ?>" class="w-full p-2.5 pr-8 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800 text-right">
                            <span class="absolute right-3 top-2.5 text-slate-400 font-bold text-sm">%</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Área Privativa / Útil</label>
                        <div class="relative">
                            <input type="text" id="area" name="area" value="<?php echo isset($empreendimento_atual['area']) ? number_format($empreendimento_atual['area'], 2, ',', '.') : ''; ?>" class="w-full p-2.5 pr-10 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800 text-right">
                            <span class="absolute right-3 top-2.5 text-slate-400 font-bold text-sm">m²</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Área do Terreno</label>
                        <div class="relative">
                            <input type="text" id="area_terreno" name="area_terreno" value="<?php echo isset($empreendimento_atual['area_terreno']) ? number_format($empreendimento_atual['area_terreno'], 2, ',', '.') : ''; ?>" class="w-full p-2.5 pr-10 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800 text-right">
                            <span class="absolute right-3 top-2.5 text-slate-400 font-bold text-sm">m²</span>
                        </div>
                    </div>
                </div>

                <h2 class="text-xl font-bold text-indigo-600 border-b pb-2 pt-2"><i class="fa-solid fa-dumbbell mr-2"></i>Amenidades & Lazer (Diferenciais)</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 bg-slate-50 p-5 rounded-xl border border-slate-200">
                    
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="piscina" class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500" <?php echo isset($empreendimento_atual['piscina']) && $empreendimento_atual['piscina'] ? 'checked' : ''; ?>>
                        <span class="text-sm text-slate-700 font-medium"><i class="fa-solid fa-person-swimming text-indigo-500 mr-1 w-4"></i> Piscina</span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="academia" class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500" <?php echo isset($empreendimento_atual['academia']) && $empreendimento_atual['academia'] ? 'checked' : ''; ?>>
                        <span class="text-sm text-slate-700 font-medium"><i class="fa-solid fa-dumbbell text-indigo-500 mr-1 w-4"></i> Academia</span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="salao_festas" class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500" <?php echo isset($empreendimento_atual['salao_festas']) && $empreendimento_atual['salao_festas'] ? 'checked' : ''; ?>>
                        <span class="text-sm text-slate-700 font-medium"><i class="fa-solid fa-champagne-glasses text-indigo-500 mr-1 w-4"></i> Salão Festas</span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="espaco_gourmet" class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500" <?php echo isset($empreendimento_atual['espaco_gourmet']) && $empreendimento_atual['espaco_gourmet'] ? 'checked' : ''; ?>>
                        <span class="text-sm text-slate-700 font-medium"><i class="fa-solid fa-fire-burner text-indigo-500 mr-1 w-4"></i> Espaço Gourmet</span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="area_lazer" class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500" <?php echo isset($empreendimento_atual['area_lazer']) && $empreendimento_atual['area_lazer'] ? 'checked' : ''; ?>>
                        <span class="text-sm text-slate-700 font-medium"><i class="fa-solid fa-icons text-indigo-500 mr-1 w-4"></i> Área de Lazer</span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="playground" class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500" <?php echo isset($empreendimento_atual['playground']) && $empreendimento_atual['playground'] ? 'checked' : ''; ?>>
                        <span class="text-sm text-slate-700 font-medium"><i class="fa-solid fa-child-reaching text-indigo-500 mr-1 w-4"></i> Playground</span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="quadra_areia" class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500" <?php echo isset($empreendimento_atual['quadra_areia']) && $empreendimento_atual['quadra_areia'] ? 'checked' : ''; ?>>
                        <span class="text-sm text-slate-700 font-medium"><i class="fa-solid fa-volleyball text-indigo-500 mr-1 w-4"></i> Quadra Areia</span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="pet_place" class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500" <?php echo isset($empreendimento_atual['pet_place']) && $empreendimento_atual['pet_place'] ? 'checked' : ''; ?>>
                        <span class="text-sm text-slate-700 font-medium"><i class="fa-solid fa-dog text-indigo-500 mr-1 w-4"></i> Pet Place</span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="garagem" class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500" <?php echo isset($empreendimento_atual['garagem']) && $empreendimento_atual['garagem'] ? 'checked' : ''; ?>>
                        <span class="text-sm text-slate-700 font-medium"><i class="fa-solid fa-car text-indigo-500 mr-1 w-4"></i> Vaga Garagem</span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="vaga_carro_eletrico" class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500" <?php echo isset($empreendimento_atual['vaga_carro_eletrico']) && $empreendimento_atual['vaga_carro_eletrico'] ? 'checked' : ''; ?>>
                        <span class="text-sm text-slate-700 font-medium"><i class="fa-solid fa-charging-station text-indigo-500 mr-1 w-4"></i> Vaga Elétrica</span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="elevador" class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500" <?php echo isset($empreendimento_atual['elevador']) && $empreendimento_atual['elevador'] ? 'checked' : ''; ?>>
                        <span class="text-sm text-slate-700 font-medium"><i class="fa-solid fa-elevator text-indigo-500 mr-1 w-4"></i> Elevador</span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="portaria" class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500" <?php echo isset($empreendimento_atual['portaria']) && $empreendimento_atual['portaria'] ? 'checked' : ''; ?>>
                        <span class="text-sm text-slate-700 font-medium"><i class="fa-solid fa-door-closed text-indigo-500 mr-1 w-4"></i> Possui Portaria</span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="portaria_presencial" class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500" <?php echo isset($empreendimento_atual['portaria_presencial']) && $empreendimento_atual['portaria_presencial'] ? 'checked' : ''; ?>>
                        <span class="text-sm text-slate-700 font-medium"><i class="fa-solid fa-user-shield text-indigo-500 mr-1 w-4"></i> P. Presencial</span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="portaria_online" class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500" <?php echo isset($empreendimento_atual['portaria_online']) && $empreendimento_atual['portaria_online'] ? 'checked' : ''; ?>>
                        <span class="text-sm text-slate-700 font-medium"><i class="fa-solid fa-headset text-indigo-500 mr-1 w-4"></i> P. Virtual / Remota</span>
                    </label>
                </div>

                <h2 class="text-xl font-bold text-indigo-600 border-b pb-2 pt-2"><i class="fa-solid fa-align-left mr-2"></i>Observações Descritivas</h2>
                <div>
                    <textarea name="observacao" rows="4" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-slate-800" placeholder="Insira detalhes adicionais do empreendimento, previsões de entrega ou metas de campanha..."><?php echo $empreendimento_atual['observacao'] ?? ''; ?></textarea>
                </div>

                <div class="pt-4 border-t flex justify-end">
                    <button type="submit" class="btn-3d bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold px-8 py-3 rounded-xl shadow-lg flex items-center gap-2">
                        <i class="fas fa-save"></i> Gravar Empreendimento
                    </button>
                </div>

            </div>
        </form>
    </div>

    <script>
        IMask(document.getElementById('valor'), {
            mask: Number, scale: 2, signed: false, thousandsSeparator: '.', padFractionalZeros: true, radix: ','
        });
        IMask(document.getElementById('area'), {
            mask: Number, scale: 2, signed: false, thousandsSeparator: '.', padFractionalZeros: true, radix: ','
        });
        IMask(document.getElementById('area_terreno'), {
            mask: Number, scale: 2, signed: false, thousandsSeparator: '.', padFractionalZeros: true, radix: ','
        });
        IMask(document.getElementById('comissao'), {
            mask: Number, scale: 2, signed: false, thousandsSeparator: '', padFractionalZeros: true, radix: ','
        });
    </script>
</body>
</html>