<?php
/**
 * Script de processamento de Leads com sanitização avançada e PHPMailer
 * PHP Version 8.0+
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Caso use Composer, descomente a linha abaixo:
// require '../vendor/autoload.php';

// Caso instale manualmente os arquivos do PHPMailer na pasta local php/:
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

/**
 * Função para enviar notificação de novo lead ao corretor responsável.
 */
function enviarEmailNovoLead($emailCorretor, $nomeCorretor) {
    if (empty($emailCorretor)) {
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug = 0;                                       
        $mail->isSMTP();                                            
        $mail->Host       = 'smtp.gmail.com';                       
        $mail->SMTPAuth   = true;                                   
        $mail->Username   = 'mcmvlanding@gmail.com';                
        $mail->Password   = 'mczm ezdv kjgw vrtm';                  
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         
        $mail->Port       = 587;                                    
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('alanisdaniele08@hotmail.com', 'Viva Lar CRM');
        $mail->addAddress($emailCorretor);
        
        $mail->isHTML(true);
        $mail->Subject = 'Viva Lar - Novo Lead!';
        
        $corpoMensagem = htmlspecialchars($nomeCorretor) . ", há um lead aguardando seu atendimento no CRM da Viva Lar. Boa sorte! 🚀🚀🚀";
        $mail->Body = "<p>" . nl2br($corpoMensagem) . "</p>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erro no disparo do PHPMailer para o corretor: {$mail->ErrorInfo}");
        return false;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['nome']) && isset($_POST['whatsapp'])) {

    // 1. SANITIZAÇÃO E VALIDAÇÃO DOS DADOS (Segurança contra XSS)
    $nome                = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS);
    $whatsapp            = filter_input(INPUT_POST, 'whatsapp', FILTER_SANITIZE_SPECIAL_CHARS);
    $cidade              = filter_input(INPUT_POST, 'cidade', FILTER_SANITIZE_SPECIAL_CHARS);
    $moradia             = filter_input(INPUT_POST, 'moradia', FILTER_SANITIZE_SPECIAL_CHARS);
    $trabalho            = filter_input(INPUT_POST, 'trabalho', FILTER_SANITIZE_SPECIAL_CHARS);
    $renda               = filter_input(INPUT_POST, 'renda', FILTER_SANITIZE_SPECIAL_CHARS);
    $tentou_financiamento = filter_input(INPUT_POST, 'tentou_financiamento', FILTER_SANITIZE_SPECIAL_CHARS);

    // Validação básica de campos obrigatórios no servidor
    if (!$nome || !$whatsapp || !$cidade || !$moradia || !$trabalho || !$renda || !$tentou_financiamento) {
        die("Erro: Todos os campos são obrigatórios para a análise.");
    }

    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug = 0;                                       
        $mail->isSMTP();                                            
        $mail->Host       = 'smtp.gmail.com';                       
        $mail->SMTPAuth   = true;                                   
        $mail->Username   = 'mcmvlanding@gmail.com';                
        $mail->Password   = 'mczm ezdv kjgw vrtm';                  
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         
        $mail->Port       = 587;                                    
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('alanisdaniele08@hotmail.com', 'Landing Page MCMV');
        $mail->addAddress('alanisdaniele08@hotmail.com'); // Destinatário principal
        
        $mail->isHTML(true);
        $mail->Subject = "Novo Lead MCMV - " . htmlspecialchars($nome);
        
        $mailBody = "
        <h2>Novo Perfil Cadastrado na Landing Page</h2>
        <p><strong>Nome:</strong> " . htmlspecialchars($nome) . "</p>
        <p><strong>WhatsApp:</strong> " . htmlspecialchars($whatsapp) . "</p>
        <p><strong>Cidade:</strong> " . htmlspecialchars($cidade) . "</p>
        <p><strong>Situação Atual de Moradia:</strong> " . htmlspecialchars($moradia) . "</p>
        <p><strong>Regime de Trabalho:</strong> " . htmlspecialchars($trabalho) . "</p>
        <p><strong>Renda Familiar Declarada:</strong> " . htmlspecialchars($renda) . "</p>
        <p><strong>Já tentou financiamento antes?:</strong> " . htmlspecialchars($tentou_financiamento) . "</p>
        <br>
        <hr>
        <p><small>Este e-mail foi gerado automaticamente a partir do formulário de conversão rápida.</small></p>
        ";

        $mail->Body = $mailBody;
        $mail->send();

        renderSucesso();

    } catch (Exception $e) {
        error_log("Erro no disparo do PHPMailer: {$mail->ErrorInfo}");
        echo "Desculpe, ocorreu um erro ao enviar suas informações. Por favor, tente novamente mais tarde.";
    }
} elseif ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Caso venha de outra rota POST que não seja a landing page
    // (Ignora para não interferir)
}

/**
 * Função interna para renderização da página de Sucesso requisitada no escopo.
 */
function renderSucesso() {
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informações Recebidas!</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .success-box {
            max-width: 500px;
            margin: 80px auto;
            background: #fff;
            padding: 40px 30px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            text-align: center;
        }
        .success-icon {
            font-size: 3.5rem;
            color: #27A844;
            margin-bottom: 20px;
        }
        .success-box h1 { color: #0A3A60; margin-bottom: 15px; font-size: 1.8rem; }
        .success-box p { color: #718096; margin-bottom: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-box">
            <div class="success-icon">✓</div>
            <h1>Recebi suas informações!</h1>
            <p>Vou analisar seu perfil inicial e entrar em contato pelo WhatsApp o mais breve possível.</p>
            <a href="../index.html" class="btn btn-primary btn-block">Voltar</a>
        </div>
    </div>
</body>
</html>
<?php
    exit();
}
?>