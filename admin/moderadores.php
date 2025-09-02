<?php
include '../includes/session.php';
include '../conexao.php';
include '../includes/notiflix.php';

$usuarioId = $_SESSION['usuario_id'];
$admin = ($stmt = $pdo->prepare("SELECT admin FROM usuarios WHERE id = ?"))->execute([$usuarioId]) ? $stmt->fetchColumn() : null;

if ($admin != 1) {
    $_SESSION['message'] = ['type' => 'warning', 'text' => 'Você não é um administrador!'];
    header("Location: /");
    exit;
}

// Buscar todos os usuários que podem ser moderadores (não são admin e não são moderadores)
$usuarios_disponiveis = $pdo->query("SELECT id, nome, email FROM usuarios WHERE admin = 0 AND moderador = 0 ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

// Buscar moderadores atuais
$moderadores = $pdo->query("SELECT id, nome, email, created_at FROM usuarios WHERE moderador = 1 ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

// Adicionar moderador
if (isset($_POST['adicionar_moderador'])) {
    $usuario_id = $_POST['usuario_id'];
    
    $stmt = $pdo->prepare("UPDATE usuarios SET moderador = 1 WHERE id = ?");
    if ($stmt->execute([$usuario_id])) {
        $_SESSION['success'] = 'Moderador adicionado com sucesso!';
    } else {
        $_SESSION['failure'] = 'Erro ao adicionar moderador!';
    }
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

// Remover moderador
if (isset($_POST['remover_moderador'])) {
    $usuario_id = $_POST['usuario_id'];
    
    $stmt = $pdo->prepare("UPDATE usuarios SET moderador = 0 WHERE id = ?");
    if ($stmt->execute([$usuario_id])) {
        $_SESSION['success'] = 'Moderador removido com sucesso!';
    } else {
        $_SESSION['failure'] = 'Erro ao remover moderador!';
    }
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

$nome = ($stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ?"))->execute([$usuarioId]) ? $stmt->fetchColumn() : null;
$nome = $nome ? explode(' ', $nome)[0] : null;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gestão de Moderadores</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/notiflix@3.2.8/dist/notiflix-aio-3.2.8.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/notiflix@3.2.8/src/notiflix.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #000000;
            color: #ffffff;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 320px;
            height: 100vh;
            background: linear-gradient(145deg, #0a0a0a 0%, #141414 25%, #1a1a1a 50%, #0f0f0f 100%);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(34, 197, 94, 0.2);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            box-shadow: 0 0 50px rgba(34, 197, 94, 0.1), inset 1px 0 0 rgba(255, 255, 255, 0.05);
        }
        
        .sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 20%, rgba(34, 197, 94, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(16, 185, 129, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 60%, rgba(59, 130, 246, 0.05) 0%, transparent 50%);
            opacity: 0.8;
            pointer-events: none;
        }
        
        .sidebar.hidden {
            transform: translateX(-100%);
        }
        
        .sidebar-header {
            position: relative;
            padding: 2.5rem 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.1) 0%, transparent 100%);
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            position: relative;
            z-index: 2;
        }
        
        .logo-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #ffffff;
            box-shadow: 0 8px 20px rgba(34, 197, 94, 0.3), 0 4px 8px rgba(0, 0, 0, 0.2);
            position: relative;
        }
        
        .logo-text {
            display: flex;
            flex-direction: column;
        }
        
        .logo-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.2;
        }
        
        .nav-menu {
            padding: 2rem 0;
            position: relative;
        }
        
        .nav-section {
            margin-bottom: 2rem;
        }
        
        .nav-section-title {
            padding: 0 2rem 0.75rem 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            padding: 1rem 2rem;
            color: #a1a1aa;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            margin: 0.25rem 1rem;
            border-radius: 12px;
            font-weight: 500;
        }
        
        .nav-item:hover,
        .nav-item.active {
            color: #ffffff;
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.15) 0%, rgba(34, 197, 94, 0.05) 100%);
            border: 1px solid rgba(34, 197, 94, 0.2);
            transform: translateX(4px);
            box-shadow: 0 4px 20px rgba(34, 197, 94, 0.1);
        }
        
        .nav-icon {
            width: 24px;
            height: 24px;
            margin-right: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            position: relative;
        }
        
        .nav-text {
            font-size: 0.95rem;
            flex: 1;
        }
        
        .main-content {
            margin-left: 320px;
            min-height: 100vh;
            transition: margin-left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: 
                radial-gradient(circle at 10% 20%, rgba(34, 197, 94, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(16, 185, 129, 0.02) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(59, 130, 246, 0.01) 0%, transparent 50%);
        }
        
        .header {
            position: sticky;
            top: 0;
            background: rgba(0, 0, 0, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1.5rem 2.5rem;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .menu-toggle {
            display: none;
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(34, 197, 94, 0.05));
            border: 1px solid rgba(34, 197, 94, 0.2);
            color: #22c55e;
            padding: 0.75rem;
            border-radius: 12px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #ffffff;
            font-size: 1rem;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
        }
        
        .page-content {
            padding: 2.5rem;
        }
        
        .welcome-section {
            margin-bottom: 3rem;
        }
        
        .welcome-title {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
            background: linear-gradient(135deg, #ffffff 0%, #fff 50%, #fff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
        }
        
        .welcome-subtitle {
            font-size: 1.25rem;
            color: #6b7280;
            font-weight: 400;
        }
        
        .content-container {
            background: linear-gradient(135deg, rgba(20, 20, 20, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 3rem;
            backdrop-filter: blur(20px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .content-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: white;
            margin-bottom: 2.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .content-title i {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.2) 0%, rgba(34, 197, 94, 0.1) 100%);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #22c55e;
            font-size: 1.25rem;
        }
        
        .form-group {
            margin-bottom: 2rem;
        }
        
        .form-label {
            display: block;
            color: #e5e7eb;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-label i {
            color: #22c55e;
            font-size: 0.875rem;
        }
        
        .form-select {
            width: 100%;
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            color: white;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .form-select:focus {
            outline: none;
            border-color: rgba(34, 197, 94, 0.5);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
            background: rgba(0, 0, 0, 0.6);
        }
        
        .form-select option {
            background: #1f2937;
            color: white;
            padding: 0.75rem;
        }
        
        .submit-button {
            width: 100%;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            border: none;
            padding: 1.25rem 2rem;
            border-radius: 16px;
            font-size: 1.125rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            box-shadow: 0 8px 25px rgba(34, 197, 94, 0.3);
        }
        
        .submit-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(34, 197, 94, 0.4);
        }
        
        .moderadores-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .moderador-card {
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .moderador-card:hover {
            border-color: rgba(34, 197, 94, 0.3);
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
        }
        
        .moderador-info {
            margin-bottom: 1.5rem;
        }
        
        .moderador-nome {
            font-size: 1.25rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.5rem;
        }
        
        .moderador-email {
            color: #9ca3af;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .moderador-data {
            color: #6b7280;
            font-size: 0.8rem;
        }
        
        .moderador-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-remove {
            flex: 1;
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
            padding: 0.75rem 1rem;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-remove:hover {
            background: rgba(239, 68, 68, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.2);
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6b7280;
        }
        
        .empty-icon {
            width: 120px;
            height: 120px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #4b5563;
            margin: 0 auto 2rem;
        }
        
        .empty-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #9ca3af;
            margin-bottom: 0.75rem;
        }
        
        .empty-subtitle {
            font-size: 1rem;
            color: #6b7280;
        }
        
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                width: 300px;
                z-index: 1001;
            }
            
            .sidebar:not(.hidden) {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .moderadores-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 1rem;
            }
            
            .page-content {
                padding: 1.5rem;
            }
            
            .welcome-title {
                font-size: 2.25rem;
            }
            
            .content-container {
                padding: 2rem;
            }
            
            .content-title {
                font-size: 1.5rem;
            }
            
            .sidebar {
                width: 280px;
            }
        }
        
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            backdrop-filter: blur(4px);
        }
        
        .overlay.active {
            opacity: 1;
            visibility: visible;
        }
    </style>
</head>
<body>
    <?php if (isset($_SESSION['success'])): ?>
        <script>
            Notiflix.Notify.success('<?= $_SESSION['success'] ?>');
        </script>
        <?php unset($_SESSION['success']); ?>
    <?php elseif (isset($_SESSION['failure'])): ?>
        <script>
            Notiflix.Notify.failure('<?= $_SESSION['failure'] ?>');
        </script>
        <?php unset($_SESSION['failure']); ?>
    <?php endif; ?>

    <div class="overlay" id="overlay"></div>
    
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="#" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-bolt"></i>
                </div>
                <div class="logo-text">
                    <div class="logo-title">Dashboard</div>
                </div>
            </a>
        </div>
        
        <nav class="nav-menu">
            <div class="nav-section">
                <div class="nav-section-title">Principal</div>
                <a href="index.php" class="nav-item">
                    <div class="nav-icon"><i class="fas fa-chart-pie"></i></div>
                    <div class="nav-text">Dashboard</div>
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Gestão</div>
                <a href="usuarios.php" class="nav-item">
                    <div class="nav-icon"><i class="fas fa-user"></i></div>
                    <div class="nav-text">Usuários</div>
                </a>
                <a href="afiliados.php" class="nav-item">
                    <div class="nav-icon"><i class="fas fa-user-plus"></i></div>
                    <div class="nav-text">Afiliados</div>
                </a>
                <a href="depositos.php" class="nav-item">
                    <div class="nav-icon"><i class="fas fa-credit-card"></i></div>
                    <div class="nav-text">Depósitos</div>
                </a>
                <a href="saques.php" class="nav-item">
                    <div class="nav-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="nav-text">Saques</div>
                </a>
                <a href="moderadores.php" class="nav-item active">
                    <div class="nav-icon"><i class="fas fa-user-shield"></i></div>
                    <div class="nav-text">Moderadores</div>
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Sistema</div>
                <a href="config.php" class="nav-item">
                    <div class="nav-icon"><i class="fas fa-cogs"></i></div>
                    <div class="nav-text">Configurações</div>
                </a>
                <a href="gateway.php" class="nav-item">
                    <div class="nav-icon"><i class="fas fa-dollar-sign"></i></div>
                    <div class="nav-text">Gateway</div>
                </a>
                <a href="banners.php" class="nav-item">
                    <div class="nav-icon"><i class="fas fa-images"></i></div>
                    <div class="nav-text">Banners</div>
                </a>
                <a href="cartelas.php" class="nav-item">
                    <div class="nav-icon"><i class="fas fa-diamond"></i></div>
                    <div class="nav-text">Raspadinhas</div>
                </a>
                <a href="../logout" class="nav-item">
                    <div class="nav-icon"><i class="fas fa-sign-out-alt"></i></div>
                    <div class="nav-text">Sair</div>
                </a>
            </div>
        </nav>
    </aside>
    
    <main class="main-content" id="mainContent">
        <header class="header">
            <div class="header-content">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
                
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <span style="color: #a1a1aa; font-size: 0.9rem; display: none;">Bem-vindo, <?= htmlspecialchars($nome) ?></span>
                    <div class="user-avatar">
                        <?= strtoupper(substr($nome, 0, 1)) ?>
                    </div>
                </div>
            </div>
        </header>
        
        <div class="page-content">
            <section class="welcome-section">
                <h2 class="welcome-title">Gestão de Moderadores</h2>
                <p class="welcome-subtitle">Gerencie os moderadores que podem administrar afiliados específicos</p>
            </section>
            
            <div class="content-container">
                <h2 class="content-title">
                    <i class="fas fa-user-plus"></i>
                    Adicionar Novo Moderador
                </h2>
                
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user"></i>
                            Selecionar Usuário
                        </label>
                        <select name="usuario_id" class="form-select" required>
                            <option value="">Escolha um usuário para ser moderador</option>
                            <?php foreach ($usuarios_disponiveis as $usuario): ?>
                                <option value="<?= $usuario['id'] ?>">
                                    <?= htmlspecialchars($usuario['nome']) ?> - <?= htmlspecialchars($usuario['email']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" name="adicionar_moderador" class="submit-button">
                        <i class="fas fa-user-shield"></i>
                        Adicionar Moderador
                    </button>
                </form>
            </div>
            
            <div class="content-container">
                <h2 class="content-title">
                    <i class="fas fa-users-cog"></i>
                    Moderadores Ativos
                    <span style="background: rgba(34, 197, 94, 0.2); color: #22c55e; padding: 0.25rem 0.75rem; border-radius: 6px; font-size: 0.8rem; margin-left: auto;">
                        <?= count($moderadores) ?> moderador<?= count($moderadores) != 1 ? 'es' : '' ?>
                    </span>
                </h2>
                
                <?php if (empty($moderadores)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="empty-title">Nenhum moderador cadastrado</div>
                        <div class="empty-subtitle">Adicione moderadores para gerenciar afiliados específicos</div>
                    </div>
                <?php else: ?>
                    <div class="moderadores-grid">
                        <?php foreach ($moderadores as $moderador): ?>
                            <div class="moderador-card">
                                <div class="moderador-info">
                                    <div class="moderador-nome"><?= htmlspecialchars($moderador['nome']) ?></div>
                                    <div class="moderador-email"><?= htmlspecialchars($moderador['email']) ?></div>
                                    <div class="moderador-data">
                                        <i class="fas fa-calendar"></i>
                                        Moderador desde: <?= date('d/m/Y', strtotime($moderador['created_at'])) ?>
                                    </div>
                                </div>
                                
                                <div class="moderador-actions">
                                    <form method="POST" style="flex: 1;" onsubmit="return confirm('Tem certeza que deseja remover este moderador?')">
                                        <input type="hidden" name="usuario_id" value="<?= $moderador['id'] ?>">
                                        <button type="submit" name="remover_moderador" class="btn-remove">
                                            <i class="fas fa-user-minus"></i>
                                            Remover
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        
        menuToggle.addEventListener('click', () => {
            const isHidden = sidebar.classList.contains('hidden');
            
            if (isHidden) {
                sidebar.classList.remove('hidden');
                overlay.classList.add('active');
            } else {
                sidebar.classList.add('hidden');
                overlay.classList.add('active');
            }
        });
        
        overlay.addEventListener('click', () => {
            sidebar.classList.add('hidden');
            overlay.classList.remove('active');
        });
        
        window.addEventListener('resize', () => {
            if (window.innerWidth <= 1024) {
                sidebar.classList.add('hidden');
                overlay.classList.remove('active');
            } else {
                sidebar.classList.remove('hidden');
                overlay.classList.remove('active');
            }
        });
        
        document.addEventListener('DOMContentLoaded', () => {
            console.log('%c👮‍♂️ Gestão de Moderadores carregada!', 'color: #22c55e; font-size: 16px; font-weight: bold;');
            
            if (window.innerWidth <= 1024) {
                sidebar.classList.add('hidden');
            }
            
            const containers = document.querySelectorAll('.content-container');
            containers.forEach((container, index) => {
                container.style.opacity = '0';
                container.style.transform = 'translateY(30px)';
                setTimeout(() => {
                    container.style.transition = 'all 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
                    container.style.opacity = '1';
                    container.style.transform = 'translateY(0)';
                }, 200 + (index * 150));
            });
        });
    </script>
</body>
</html>