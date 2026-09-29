const express = require('express');
const mysql = require('mysql2/promise');
require('dotenv').config();

const app = express();
app.use(express.json()); // Permite que o servidor entenda JSON

// Configuração da conexão com o seu Banco de Dados MySQL
const dbConfig = {
    host: 'localhost',          // Mude para o host do seu banco se não for local
    user: 'primesys',           // Usuário do seu banco
    password: 'primesys201025', // Senha do seu banco
    database: 'primesys_imob'   // Nome do seu banco de dados
};

// Rota do Webhook: É aqui que a Z-API vai enviar os dados
app.post('/webhook-whatsapp', async (req, res) => {
    try {
        // 1. Extrair os dados usando a estrutura exata da Z-API
        const whatsapp_id = req.body.phone;       // Número do cliente (ex: 5511999999999)
        const nome        = req.body.senderName;  // Nome do perfil do cliente
        const telefone    = req.body.phone;       
        const texto       = req.body.text?.message; // Texto da mensagem recebida

        console.log(`Recebendo mensagem de ${nome} (${telefone}): ${texto}`);

        // Segurança: Se não for uma mensagem de texto (ex: se for uma imagem ou áudio),
        // responde 200 OK para a Z-API mas interrompe o fluxo para não dar erro
        if (!whatsapp_id || !texto) {
            return res.status(200).send('Ignorado: Não é uma mensagem de texto válida.');
        }

        // 2. Conectar ao Banco de Dados
        const connection = await mysql.createConnection(dbConfig);

        // 3. REGRA DE OURO: Verificar se o número já existe no banco
        const [rows] = await connection.execute(
            'SELECT id_cliente FROM clientes WHERE whatsapp_id = ?', 
            [whatsapp_id]
        );

        if (rows.length > 0) {
            await connection.end();
            return res.status(200).send('Contato já cadastrado. Ignorando.');
        }

        // 4. Descobrir a ORIGEM do lead analisando o texto da mensagem
        let origen_lead = 'WhatsApp';
        const mensagemMinuscula = texto.toLowerCase();
        if (mensagemMinuscula.includes('marketplace') || mensagemMinuscula.includes('facebook')) {
            origen_lead = 'Facebook Marketplace';
        } else if (mensagemMinuscula.includes('instagram') || mensagemMinuscula.includes('insta')) {
            origen_lead = 'Instagram';
        }

        // 5. Inserir no banco
        const queryInsert = `
            INSERT INTO clientes (whatsapp_id, nome, telefone1, origem_lead, status_lead) 
            VALUES (?, ?, ?, ?, 'Lead')
        `;
        
        await connection.execute(queryInsert, [whatsapp_id, nome, telefone, origen_lead]);
        await connection.end();

        return res.status(200).send('Novo lead capturado com sucesso!');

    } catch (error) {
        console.error('Erro no processamento interno:', error);
        return res.status(500).send('Erro interno do servidor.');
    }
});

// Inicia o servidor na porta 3000
app.listen(3000, () => {
    console.log('Servidor rodando e aguardando mensagens na porta 3000!');
});