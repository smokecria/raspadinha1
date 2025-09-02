<?php
// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['message'] = ['type' => 'warning', 'text' => 'Você precisa estar logado!'];
    header("Location: /login");
    exit;
}

$usuarioId = $_SESSION['usuario_id'];

// Buscar informações do usuário
$stmt = $pdo->prepare("SELECT admin, moderador, nome FROM usuarios WHERE id = ?");
$stmt->execute([$usuarioId]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_info) {
    $_SESSION['message'] = ['type' => 'failure', 'text' => 'Usuário não encontrado!'];
    header("Location: /logout");
    exit;
}

$isAdmin = ($user_info['admin'] == 1);
$isModerador = ($user_info['moderador'] == 1);
$nome = $user_info['nome'] ? explode(' ', $user_info['nome'])[0] : null;

// Verificar se tem permissão para acessar o admin
if (!$isAdmin && !$isModerador) {
    $_SESSION['message'] = ['type' => 'warning', 'text' => 'Você não tem permissão para acessar esta área!'];
    header("Location: /");
    exit;
}

// Função para verificar se o moderador pode acessar um usuário específico
function canModeratorAccess($pdo, $moderadorId, $targetUserId) {
    // Admin pode acessar tudo
    $stmt = $pdo->prepare("SELECT admin FROM usuarios WHERE id = ?");
    $stmt->execute([$moderadorId]);
    if ($stmt->fetchColumn() == 1) {
        return true;
    }
    
    // Verificar se o usuário alvo é afiliado direto do moderador
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE id = ? AND indicacao = ?");
    $stmt->execute([$targetUserId, $moderadorId]);
    if ($stmt->fetchColumn() > 0) {
        return true;
    }
    
    // Verificar se o usuário alvo é sub-afiliado (afiliado de um afiliado do moderador)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM usuarios u1
        JOIN usuarios u2 ON u1.indicacao = u2.id
        WHERE u1.id = ? AND u2.indicacao = ?
    ");
    $stmt->execute([$targetUserId, $moderadorId]);
    if ($stmt->fetchColumn() > 0) {
        return true;
    }
    
    return false;
}

// Função para obter IDs dos afiliados e sub-afiliados de um moderador
function getModeratorAffiliateIds($pdo, $moderadorId) {
    $affiliateIds = [];
    
    // Afiliados diretos
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE indicacao = ?");
    $stmt->execute([$moderadorId]);
    $directAffiliates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $affiliateIds = array_merge($affiliateIds, $directAffiliates);
    
    // Sub-afiliados (afiliados dos afiliados)
    foreach ($directAffiliates as $affiliateId) {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE indicacao = ?");
        $stmt->execute([$affiliateId]);
        $subAffiliates = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $affiliateIds = array_merge($affiliateIds, $subAffiliates);
    }
    
    return $affiliateIds;
}

// Função para registrar log de ação do moderador
function logModeratorAction($pdo, $moderadorId, $acao, $detalhes = null, $usuarioAfetadoId = null, $valor = null) {
    $stmt = $pdo->prepare("
        INSERT INTO logs_moderadores (moderador_id, acao, detalhes, usuario_afetado_id, valor) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$moderadorId, $acao, $detalhes, $usuarioAfetadoId, $valor]);
}
?>