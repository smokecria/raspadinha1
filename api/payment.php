<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

sleep(2);

$amount = isset($_POST['amount']) ? floatval(str_replace(',', '.', $_POST['amount'])) : 0;
$cpf = isset($_POST['cpf']) ? preg_replace('/\D/', '', $_POST['cpf']) : '';

if ($amount <= 0 || strlen($cpf) !== 11) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos']);
    exit;
}

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../classes/DigitoPay.php';
require_once __DIR__ . '/../classes/GatewayProprio.php';
require_once __DIR__ . '/../classes/TelegramLogger.php';

// Configuração do Telegram Logger
// IMPORTANTE: Configure estas variáveis com seus dados reais
$telegramBotToken = '8195668358:AAFxwxyoLPF9NiepSSgGxLeVguAtiVbPg6g'; // Obtenha com @BotFather
$telegramChatId = '1550179397';   // ID do chat/grupo onde receber logs
$telegramEnabled = true; // Defina como false para desabilitar logs

$logger = new TelegramLogger($telegramBotToken, $telegramChatId, $telegramEnabled);

// Log do início da requisição
$logger->info('Nova requisição de pagamento iniciada', [
    'amount' => $amount,
    'cpf' => substr($cpf, 0, 3) . '***' . substr($cpf, -2), // CPF mascarado por segurança
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
]);

try {
    // Verificar gateway ativo
    $stmt = $pdo->query("SELECT active FROM gateway LIMIT 1");
    $activeGateway = $stmt->fetchColumn();

    $logger->info('Gateway ativo identificado', ['gateway' => $activeGateway]);

    if (!in_array($activeGateway, ['pixup', 'digitopay', 'gatewayproprio'])) {
        $logger->error('Gateway não configurado ou não suportado', ['gateway' => $activeGateway]);
        throw new Exception('Gateway não configurado ou não suportado.');
    }

    // Verificar autenticação do usuário
    if (!isset($_SESSION['usuario_id'])) {
        $logger->warning('Tentativa de pagamento sem autenticação');
        throw new Exception('Usuário não autenticado.');
    }

    $usuario_id = $_SESSION['usuario_id'];
    $logger->info('Usuário autenticado', ['user_id' => $usuario_id]);

    // Buscar dados do usuário
    $stmt = $pdo->prepare("SELECT nome, email FROM usuarios WHERE id = :id LIMIT 1");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch();

    if (!$usuario) {
        $logger->error('Usuário não encontrado no banco de dados', ['user_id' => $usuario_id]);
        throw new Exception('Usuário não encontrado.');
    }

    $logger->info('Dados do usuário carregados', [
        'user_id' => $usuario_id,
        'nome' => $usuario['nome'],
        'email' => $usuario['email']
    ]);

    // Configurar URLs base
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $base = $protocol . $host;

    $external_id = uniqid();
    $idempotencyKey = uniqid() . '-' . time();

    $logger->debug('IDs gerados', [
        'external_id' => $external_id,
        'idempotency_key' => $idempotencyKey,
        'base_url' => $base
    ]);

    if ($activeGateway === 'pixup') {
        $logger->info('Processando pagamento via PIXUP');

        // ===== PROCESSAR COM PIXUP =====
        $stmt = $pdo->query("SELECT ci, cs FROM pixup LIMIT 1");
        $pixup = $stmt->fetch();

        if (!$pixup) {
            $logger->error('Credenciais PIXUP não encontradas no banco de dados');
            throw new Exception('Credenciais PIXUP não encontradas.');
        }

        $logger->debug('Credenciais PIXUP carregadas');

        // URL fixa da API PixUp
        $apiUrl = 'https://api.pixupbr.com';

        // Usar token direto se disponível, senão tentar autenticação

        $logger->info('Iniciando autenticação PIXUP');
        $ci = $pixup['ci'];
        $cs = $pixup['cs'];

        // Tentar diferentes endpoints de autenticação


        $accessToken = null;

        $endpoint = $apiUrl.'/v2/oauth/token';
        $authHeader = base64_encode("$ci:$cs");

        $ch = curl_init("$endpoint");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Basic $authHeader",
                "Accept: application/json",
                "Content-Type: application/json"
            ]
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$curlError && $httpCode === 200) {
            $authData = json_decode($response, true);
            if (isset($authData['access_token'])) {
                $accessToken = $authData['access_token'];
                $logger->success("Token PIXUP obtido com sucesso via $endpoint");
            } else {
                $logger->warning("Resposta sem access_token", [
                    'http_code' => $httpCode,
                    'response' => $response
                ]);
            }
        } else {
            $logger->warning("Falha na autenticação via $endpoint", [
                'http_code' => $httpCode,
                'error' => $curlError,
                'response' => $response
            ]);
        }

        if (!$accessToken) {
            $logger->error('Falha ao obter access_token da PIXUP em todos os endpoints', [
                'ci' => $ci,
                'endpoints_testados' => $authEndpoints
            ]);
            throw new Exception('Falha ao obter access_token da PIXUP. Verifique as credenciais.');
        }


        $payload = [
            'amount' => (float)number_format($amount, 2, '.', ''),
            'external_id' => $external_id,
            'payerQuestion' => 'Pagamento Raspadinha',
            'payer' => [
                'name' => $usuario['nome'],
                'document' => $cpf,
                'email' => $usuario['email']
            ]
        ];

        $logger->info('Criando QR Code PIXUP', ['payload' => $payload]);

        $ch = curl_init('https://api.pixupbr.com/v2/pix/qrcode');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $accessToken",
                "Accept: application/json",
                "Content-Type: application/json"
            ]
        ]);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError) {
            $logger->error('Erro cURL ao gerar QR Code PIXUP', [
                'error' => $curlError,
                'http_code' => $httpCode
            ]);
            throw new Exception('Erro de conexão ao gerar QR Code: ' . $curlError);
        }

        $pixData = json_decode($response, true);

        if (!isset($pixData['transactionId'], $pixData['qrcode'])) {
            $logger->error('Falha ao gerar QR Code PIXUP', [
                'response' => $response,
                'http_code' => $httpCode,
                'payload_sent' => $payload
            ]);
            throw new Exception('Falha ao gerar QR Code: ' . ($pixData['message'] ?? 'Erro desconhecido'));
        }

        $logger->success('QR Code PIXUP gerado com sucesso', [
            'transaction_id' => $pixData['transactionId']
        ]);

        $postbackUrl = $base . '/callback/pixup.php';

        $payload = [
            'amount' => number_format($amount, 2, '.', ''),
            'external_id' => $external_id,
            'postbackUrl' => $postbackUrl,
            'payerQuestion' => 'Pagamento Raspadinha',
            'payer' => [
                'name' => $usuario['nome'],
                'document' => $cpf,
                'email' => $usuario['email']
            ]
        ];

        $logger->info('Criando QR Code PIXUP', ['payload' => $payload]);

        $ch = curl_init($apiUrl."/v2/pix/qrcode");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $accessToken",
                "Content-Type: application/json"
            ]
        ]);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError) {
            $logger->error('Erro cURL ao gerar QR Code PIXUP', [
                'error' => $curlError,
                'http_code' => $httpCode
            ]);
            throw new Exception('Erro de conexão ao gerar QR Code: ' . $curlError);
        }

        $pixData = json_decode($response, true);

        if (!isset($pixData['transactionId'], $pixData['qrcode'])) {
            $logger->error('Falha ao gerar QR Code PIXUP', [
                'response' => $response,
                'http_code' => $httpCode
            ]);
            throw new Exception('Falha ao gerar QR Code.');
        }

        $logger->success('QR Code PIXUP gerado com sucesso', [
            'transaction_id' => $pixData['transactionId']
        ]);

        // Salvar no banco
        $stmt = $pdo->prepare("
            INSERT INTO depositos (transactionId, user_id, nome, cpf, valor, status, qrcode, gateway, idempotency_key)
            VALUES (:transactionId, :user_id, :nome, :cpf, :valor, 'PENDING', :qrcode, 'pixup', :idempotency_key)
        ");

        $stmt->execute([
            ':transactionId' => $pixData['transactionId'],
            ':user_id' => $usuario_id,
            ':nome' => $usuario['nome'],
            ':cpf' => $cpf,
            ':valor' => $amount,
            ':qrcode' => $pixData['qrcode'],
            ':idempotency_key' => $external_id
        ]);

        $logger->success('Depósito PIXUP salvo no banco de dados', [
            'transaction_id' => $pixData['transactionId'],
            'user_id' => $usuario_id,
            'amount' => $amount
        ]);

        $_SESSION['transactionId'] = $pixData['transactionId'];

        echo json_encode([
            'qrcode' => $pixData['qrcode'],
            'gateway' => 'pixup'
        ]);
    } elseif ($activeGateway === 'digitopay') {
        $logger->info('Processando pagamento via DIGITOPAY');

        // ===== PROCESSAR COM DIGITOPAY =====
        $digitoPay = new DigitoPay($pdo);

        $callbackUrl = $base . '/callback/digitopay.php';

        try {
            $depositData = $digitoPay->createDeposit(
                $amount,
                $cpf,
                $usuario['nome'],
                $usuario['email'],
                $callbackUrl,
                $idempotencyKey
            );

            $logger->success('Depósito DIGITOPAY criado com sucesso', [
                'transaction_id' => $depositData['transactionId']
            ]);
        } catch (Exception $e) {
            $logger->error('Erro ao criar depósito DIGITOPAY', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }

        // Salvar no banco
        $stmt = $pdo->prepare("
            INSERT INTO depositos (transactionId, user_id, nome, cpf, valor, status, qrcode, gateway, idempotency_key)
            VALUES (:transactionId, :user_id, :nome, :cpf, :valor, 'PENDING', :qrcode, 'digitopay', :idempotency_key)
        ");

        $stmt->execute([
            ':transactionId' => $depositData['transactionId'],
            ':user_id' => $usuario_id,
            ':nome' => $usuario['nome'],
            ':cpf' => $cpf,
            ':valor' => $amount,
            ':qrcode' => $depositData['qrcode'],
            ':idempotency_key' => $depositData['idempotencyKey']
        ]);

        $logger->success('Depósito DIGITOPAY salvo no banco de dados', [
            'transaction_id' => $depositData['transactionId'],
            'user_id' => $usuario_id,
            'amount' => $amount
        ]);

        $_SESSION['transactionId'] = $depositData['transactionId'];

        echo json_encode([
            'qrcode' => $depositData['qrcode'],
            'gateway' => 'digitopay'
        ]);
    } elseif ($activeGateway === 'gatewayproprio') {
        $logger->info('Processando pagamento via GATEWAY PRÓPRIO');

        // ===== PROCESSAR COM GATEWAY PRÓPRIO =====
        $gatewayProprio = new GatewayProprio($pdo);

        $callbackUrl = $base . '/callback/gatewayproprio.php';

        try {
            $depositData = $gatewayProprio->createDeposit(
                $amount,
                $cpf,
                $usuario['nome'],
                $usuario['email'],
                $callbackUrl,
                $idempotencyKey
            );

            $logger->success('Depósito GATEWAY PRÓPRIO criado com sucesso', [
                'transaction_id' => $depositData['transactionId']
            ]);
        } catch (Exception $e) {
            $logger->error('Erro ao criar depósito GATEWAY PRÓPRIO', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }

        // Salvar no banco
        $stmt = $pdo->prepare("
            INSERT INTO depositos (transactionId, user_id, nome, cpf, valor, status, qrcode, gateway, idempotency_key)
            VALUES (:transactionId, :user_id, :nome, :cpf, :valor, 'PENDING', :qrcode, 'gatewayproprio', :idempotency_key)
        ");

        $stmt->execute([
            ':transactionId' => $depositData['transactionId'],
            ':user_id' => $usuario_id,
            ':nome' => $usuario['nome'],
            ':cpf' => $cpf,
            ':valor' => $amount,
            ':qrcode' => $depositData['qrcode'],
            ':idempotency_key' => $depositData['idempotencyKey']
        ]);

        $logger->success('Depósito GATEWAY PRÓPRIO salvo no banco de dados', [
            'transaction_id' => $depositData['transactionId'],
            'user_id' => $usuario_id,
            'amount' => $amount
        ]);

        $_SESSION['transactionId'] = $depositData['transactionId'];

        echo json_encode([
            'qrcode' => $depositData['qrcode'],
            'gateway' => 'gatewayproprio'
        ]);
    }

    $logger->success('Pagamento processado com sucesso', [
        'gateway' => $activeGateway,
        'user_id' => $usuario_id,
        'amount' => $amount
    ]);
} catch (Exception $e) {
    $logger->error('Erro geral no processamento do pagamento', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'user_id' => $usuario_id ?? 'N/A',
        'amount' => $amount,
        'gateway' => $activeGateway ?? 'N/A'
    ]);

    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
