<?php
require_once 'database.php';

$db = new database();

// 1. BUSCA A LISTA DE BANCOS PARA A COMBOBOX
$sql_bancos = "SELECT cod, descricao FROM instituicoes_financeiras ORDER BY descricao ASC";
$lista_bancos = $db->Query($sql_bancos);

// Inicialização de variáveis para edição
$id_usuario = isset($_GET['id']) ? intval($_GET['id']) : null;
$usuario_atual = null;

if ($id_usuario) {
    $sql = "SELECT * FROM usuarios WHERE id_usuario = $id_usuario LIMIT 1";
    $resultado = $db->Query($sql);
    if (!empty($resultado)) {
        $usuario_atual = $resultado[0];
    }
}

// Processamento do Formulário (Salvar / Atualizar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Escapa aspas simples para evitar quebra de sintaxe SQL na sua classe Database
    $login = str_replace("'", "''", trim($_POST['login']));
    $nome = str_replace("'", "''", trim($_POST['nome']));
    $telefone = str_replace("'", "''", trim($_POST['telefone']));
    $endereco = str_replace("'", "''", trim($_POST['endereco']));
    $email = str_replace("'", "''", trim($_POST['email']));
    $conta = str_replace("'", "''", trim($_POST['conta']));
    $agencia = str_replace("'", "''", trim($_POST['agencia']));
    $tipo_pix = str_replace("'", "''", trim($_POST['tipo_pix']));
    $chave_pix = str_replace("'", "''", trim($_POST['chave_pix']));
    
    // Captura o banco selecionado na Combobox
    $banco_selecionado = $_POST['banco_combobox'] ?? '';
    $banco_cod = 'NULL';
    $banco_desc = '';
    
    if (!empty($banco_selecionado)) {
        $partes_banco = explode('|', $banco_selecionado);
        $banco_cod = intval($partes_banco[0]);
        $banco_desc = str_replace("'", "''", $partes_banco[1]);
    }
    
    $corretor = isset($_POST['corretor']) ? 1 : 0;
    $financeiro = isset($_POST['financeiro']) ? 1 : 0;
    $master_user = isset($_POST['master_user']) ? 1 : 0;
    
    $percentual_comissao = !empty($_POST['percentual_comissao']) ? str_replace(',', '.', $_POST['percentual_comissao']) : 0.00;
    $id_edicao = !empty($_POST['id_usuario']) ? intval($_POST['id_usuario']) : null;

    // TRATAMENTO DA FOTO USANDO HEXADECIMAL (Evita o erro de gravação no SQL)
    $foto_sql_clause = "";
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $conteudo_foto = file_get_contents($_FILES['foto']['tmp_name']);
        $foto_hex = bin2hex($conteudo_foto);
        // Usamos UNHEX para o MySQL reconstruir o binário perfeitamente sem quebrar a string
        $foto_sql_clause = ", foto = UNHEX('$foto_hex')";
    }

    if ($id_edicao) {
        // --- MODO EDIÇÃO ---
        $sql = "UPDATE usuarios SET 
                    login = '$login', 
                    nome = '$nome', 
                    telefone = '$telefone', 
                    endereco = '$endereco', 
                    email = '$email', 
                    banco_cod = $banco_cod, 
                    banco_desc = '$banco_desc', 
                    conta = '$conta', 
                    agencia = '$agencia', 
                    tipo_pix = '$tipo_pix',
                    chave_pix = '$chave_pix'
                    $foto_sql_clause,
                    corretor = $corretor, 
                    financeiro = $financeiro, 
                    master_user = $master_user, 
                    percentual_comissao = $percentual_comissao
                WHERE id_usuario = $id_edicao";
        
        if (!empty($_POST['nova_senha'])) {
            $nova_senha_hash = password_hash($_POST['nova_senha'], PASSWORD_DEFAULT);
            $sql_senha = "UPDATE usuarios SET senha = '$nova_senha_hash' WHERE id_usuario = $id_edicao";
            $db->Query($sql_senha);
        }

        $db->Query($sql);
        header("Location: usuario.php?id=$id_edicao&msg=sucesso");
        exit;
    } else {
        // --- MODO CADASTRO ---
        $senha_original = $_POST['senha'] ?? '123456';
        $senha_hash = password_hash($senha_original, PASSWORD_DEFAULT);

        $coluna_foto = "";
        $valor_foto = "";
        if (!empty($foto_sql_clause)) {
            $coluna_foto = ", foto";
            $valor_foto = ", UNHEX('$foto_hex')";
        }

        $sql = "INSERT INTO usuarios (login, senha, nome, telefone, endereco, email $coluna_foto, banco_cod, banco_desc, conta, agencia, tipo_pix, chave_pix, corretor, financeiro, master_user, percentual_comissao) 
                VALUES ('$login', '$senha_hash', '$nome', '$telefone', '$endereco', '$email' $valor_foto, $banco_cod, '$banco_desc', '$conta', '$agencia', '$tipo_pix', '$chave_pix',$corretor, $financeiro, $master_user, $percentual_comissao)";
        
        $db->Query($sql);
        header("Location: usuario.php?msg=cadastrado");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Usuário - PrimeSys Imob</title>
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
                <span>Operação realizada com sucesso!</span>
            </div>
        <?php endif; ?>

        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">
                    <i class="fa-solid fa-user-gear text-indigo-600 mr-2"></i>
                    <?php echo $usuario_atual ? 'Editar Usuário Corretor' : 'Novo Usuário'; ?>
                </h1>
                <p class="text-sm text-slate-500 mt-1">Gerencie as permissões, comissões e dados bancários do integrante do sistema.</p>
            </div>
            <?php if($usuario_atual): ?>
                <a href="usuario.php" class="btn-3d bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg flex items-center gap-2">
                    <i class="fas fa-plus"></i> Novo Cadastro
                </a>
            <?php endif; ?>
        </div>

        <form action="usuario.php" method="POST" enctype="multipart/form-data" class="space-y-6">
            <?php if($usuario_atual): ?>
                <input type="hidden" name="id_usuario" value="<?php echo $usuario_atual['id_usuario']; ?>">
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <div class="bg-white p-6 rounded-2xl shadow-xl border border-slate-200 flex flex-col items-center">
                    
                    <div class="relative w-40 h-40 group mb-6">
                        <div class="w-full h-full rounded-full overflow-hidden ring-4 ring-indigo-500 ring-offset-4 shadow-inner bg-slate-200 flex items-center justify-center">
                            <?php 
                            if (!empty($usuario_atual['foto'])) {
                                echo '<img id="avatar-preview" src="data:image/jpeg;base64,'.base64_encode($usuario_atual['foto']).'" class="w-full h-full object-cover" alt="Foto">';
                            } else {
                                echo '<img id="avatar-preview" src="https://cdn-icons-png.flaticon.com/512/149/149071.png" class="w-full h-full object-cover opacity-60" alt="Padrão">';
                            }
                            ?>
                        </div>
                        <label for="foto" class="absolute bottom-1 right-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-full p-3 shadow-lg cursor-pointer transition-all hover:scale-110 flex items-center justify-center btn-3d">
                            <i class="fas fa-camera text-sm"></i>
                            <input type="file" id="foto" name="foto" accept="image/*" class="hidden" onchange="previewImagem(event)">
                        </label>
                    </div>

                    <h3 class="text-lg font-bold text-slate-700 mb-4">Acessos & Níveis</h3>
                    
                    <div class="w-full space-y-3 bg-slate-50 p-4 rounded-xl border border-slate-100">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="corretor" class="w-5 h-5 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500" <?php echo isset($usuario_atual['corretor']) && $usuario_atual['corretor'] ? 'checked' : ''; ?>>
                            <span class="text-sm font-semibold text-slate-700"><i class="fa-solid fa-house-chimney text-emerald-500 mr-1"></i> É Corretor</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="financeiro" class="w-5 h-5 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500" <?php echo isset($usuario_atual['financeiro']) && $usuario_atual['financeiro'] ? 'checked' : ''; ?>>
                            <span class="text-sm font-semibold text-slate-700"><i class="fa-solid fa-coins text-amber-500 mr-1"></i> Acesso Financeiro</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="master_user" class="w-5 h-5 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500" <?php echo isset($usuario_atual['master_user']) && $usuario_atual['master_user'] ? 'checked' : ''; ?>>
                            <span class="text-sm font-semibold text-slate-700"><i class="fa-solid fa-shield-halved text-red-500 mr-1"></i> Usuário Master</span>
                        </label>
                    </div>

                    <?php if($usuario_atual): ?>
                        <button type="button" onclick="abrirModalSenha()" class="w-full mt-6 btn-3d bg-amber-500 hover:bg-amber-600 text-white font-bold py-2.5 px-4 rounded-xl flex items-center justify-center gap-2 text-sm shadow">
                            <i class="fas fa-key"></i> Alterar Senha do Usuário
                        </button>
                    <?php endif; ?>
                </div>

                <div class="bg-white p-8 rounded-2xl shadow-xl border border-slate-200 lg:col-span-2 space-y-6">
                    
                    <h2 class="text-xl font-bold text-indigo-600 border-b pb-2"><i class="fas fa-id-card mr-2"></i>Dados Pessoais</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nome Completo</label>
                            <input type="text" name="nome" required value="<?php echo $usuario_atual['nome'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Login de Sistema</label>
                            <input type="text" name="login" required value="<?php echo $usuario_atual['login'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">E-mail</label>
                            <input type="email" name="email" value="<?php echo $usuario_atual['email'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Telefone / WhatsApp</label>
                            <input type="text" id="telefone" name="telefone" value="<?php echo $usuario_atual['telefone'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="(00) 00000-0000">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Endereço Residencial</label>
                            <input type="text" name="endereco" value="<?php echo $usuario_atual['endereco'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                    </div>

                    <?php if(!$usuario_atual): ?>
                        <h2 class="text-xl font-bold text-indigo-600 border-b pb-2 pt-2"><i class="fas fa-lock mr-2"></i>Segurança Inicial</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Definir Senha</label>
                                <input type="password" name="senha" required class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            </div>
                        </div>
                    <?php endif; ?>

                    <h2 class="text-xl font-bold text-indigo-600 border-b pb-2 pt-2"><i class="fas fa-piggy-bank mr-2"></i>Dados de Comissão e Faturamento</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Selecione a Instituição Financeira</label>
                            <select name="banco_combobox" class="w-full p-2.5 bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                <option value="">-- Escolha o Banco --</option>
                                <?php 
                                if (!empty($lista_bancos)) {
                                    foreach ($lista_bancos as $banco) {
                                        // Comparamos para manter selecionado em caso de edição
                                        $selected = (isset($usuario_atual['banco_cod']) && $usuario_atual['banco_cod'] == $banco['cod']) ? 'selected' : '';
                                        // Passamos o Código e a Descrição juntos separados por um pipe '|' para processar no POST
                                        echo "<option value='{$banco['cod']}|{$banco['descricao']}' {$selected}>[{$banco['cod']}] {$banco['descricao']}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Comissão (%)</label>
                            <div class="relative">
                                <input type="text" id="comissao" name="percentual_comissao" value="<?php echo isset($usuario_atual['percentual_comissao']) ? number_format($usuario_atual['percentual_comissao'], 2, ',', '') : '0,00'; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none text-right pr-8">
                                <span class="absolute right-3 top-3 text-slate-400 font-bold text-sm">%</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Agência</label>
                            <input type="text" name="agencia" value="<?php echo $usuario_atual['agencia'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Conta Corrente</label>
                            <input type="text" name="conta" value="<?php echo $usuario_atual['conta'] ?? ''; ?>" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Tipo de Chave PIX</label>
                            <select id="tipo_pix" name="tipo_pix" class="w-full p-2.5 bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none" onchange="ajustarMascaraPix()">
                                <option value="" <?php echo empty($usuario_atual['tipo_pix']) ? 'selected' : ''; ?>>Selecione...</option>
                                <option value="CPF" <?php echo (isset($usuario_atual['tipo_pix']) && $usuario_atual['tipo_pix'] == 'CPF') ? 'selected' : ''; ?>>CPF</option>
                                <option value="CNPJ" <?php echo (isset($usuario_atual['tipo_pix']) && $usuario_atual['tipo_pix'] == 'CNPJ') ? 'selected' : ''; ?>>CNPJ</option>
                                <option value="Celular" <?php echo (isset($usuario_atual['tipo_pix']) && $usuario_atual['tipo_pix'] == 'Celular') ? 'selected' : ''; ?>>Celular</option>
                                <option value="E-mail" <?php echo (isset($usuario_atual['tipo_pix']) && $usuario_atual['tipo_pix'] == 'E-mail') ? 'selected' : ''; ?>>E-mail</option>
                                <option value="Chave" <?php echo (isset($usuario_atual['tipo_pix']) && $usuario_atual['tipo_pix'] == 'Chave') ? 'selected' : ''; ?>>Chave Aleatória</option>
                            </select>
                        </div>

                        <div class="md:col-span-3">
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1"><i class="fa-solid fa-qrcode mr-1 text-indigo-500"></i> Chave PIX</label>
                            <input type="text" id="chave_pix" name="chave_pix" value="<?php echo $usuario_atual['chave_pix'] ?? ''; ?>" placeholder="Insira a chave de acordo com o tipo selecionado" class="w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>

                    </div>

                    <div class="pt-4 border-t flex justify-end">
                        <button type="submit" class="btn-3d bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold px-8 py-3 rounded-xl shadow-lg flex items-center gap-2">
                            <i class="fas fa-save"></i> Gravar Informações
                        </button>
                    </div>

                </div>

            </div>

            <div id="modalSenha" class="fixed inset-0 bg-slate-900 bg-opacity-50 hidden backdrop-blur-sm justify-center items-center z-50">
                <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-sm w-full mx-4 border border-slate-100 transform transition-all">
                    <div class="flex items-center gap-3 text-amber-500 mb-4">
                        <i class="fas fa-shield-halved text-2xl"></i>
                        <h3 class="text-lg font-bold text-slate-800">Alteração Segura de Senha</h3>
                    </div>
                    <p class="text-xs text-slate-500 mb-4">A nova senha passará pela criptografia nativa segura do servidor.</p>
                    
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Nova Senha</label>
                            <input type="password" id="modal_nova_senha" name="nova_senha" class="w-full p-2.5 border border-slate-300 rounded-lg focus:outline-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Confirme a Nova Senha</label>
                            <input type="password" id="modal_confirma_senha" class="w-full p-2.5 border border-slate-300 rounded-lg focus:outline-indigo-500">
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 mt-6">
                        <button type="button" onclick="fecharModalSenha()" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold px-4 py-2 rounded-lg text-sm transition-colors">Cancelar</button>
                        <button type="button" onclick="confirmarNovaSenha()" class="btn-3d bg-emerald-500 hover:bg-emerald-600 text-white font-bold px-4 py-2 rounded-lg text-sm">Aplicar Senha</button>
                    </div>
                </div>
            </div>

        </form>
    </div>

    <script>
        // Máscaras de Entrada
        IMask(document.getElementById('telefone'), {
            mask: [{ mask: '(00) 0000-0000' }, { mask: '(00) 00000-0000' }]
        });

        IMask(document.getElementById('comissao'), {
            mask: Number, scale: 2, signed: false, thousandsSeparator: '', padFractionalZeros: true, radix: ','
        });

        // Controle dinâmico inteligente de máscaras do PIX baseado na seleção do tipo
        let pixMask = null;
        function ajustarMascaraPix() {
            const tipo = document.getElementById('tipo_pix').value;
            const campoChave = document.getElementById('chave_pix');
            
            if (pixMask) pixMask.destroy(); // Reseta máscara anterior

            if (tipo === 'CPF') {
                pixMask = IMask(campoChave, { mask: '000.000.000-00' });
            } else if (tipo === 'CNPJ') {
                pixMask = IMask(campoChave, { mask: '00.000.000/0000-00' });
            } else if (tipo === 'Celular') {
                pixMask = IMask(campoChave, { mask: '+55 (00) 00000-0000' });
            } else {
                campoChave.placeholder = "Insira a chave PIX";
            }
        }
        // Executa ao carregar a página caso já tenha dados salvos
        document.addEventListener("DOMContentLoaded", ajustarMascaraPix);

        function previewImagem(event) {
            const reader = new FileReader();
            reader.onload = function(){
                const output = document.getElementById('avatar-preview');
                output.src = reader.result;
                output.classList.remove('opacity-60');
            };
            reader.readAsDataURL(event.target.files[0]);
        }

        function abrirModalSenha() { document.getElementById('modalSenha').style.display = 'flex'; }
        function fecharModalSenha() {
            document.getElementById('modalSenha').style.display = 'none';
            document.getElementById('modal_nova_senha').value = '';
            document.getElementById('modal_confirma_senha').value = '';
        }

        function confirmarNovaSenha() {
            const senha = document.getElementById('modal_nova_senha').value;
            const confirma = document.getElementById('modal_confirma_senha').value;

            if(senha.trim() === "") { alert("A senha não pode estar em branco."); return; }
            if(senha !== confirma) { alert("As senhas informadas não coincidem."); return; }

            alert("Senha definida! Para consolidar no banco de dados, clique em 'Gravar Informações' no rodapé.");
            document.getElementById('modalSenha').style.display = 'none';
        }
        
        // Adicione isso dentro da tag <script> existente no final do arquivo usuario.php
         document.addEventListener("DOMContentLoaded", function() {
         const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('abrirModal') === '1') {
               abrirModalSenha();
            }
         });
    </script>
</body>
</html>