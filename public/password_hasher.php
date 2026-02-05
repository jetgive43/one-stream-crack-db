<?php
/**
 * Password Hasher Tool
 * Simple tool to hash/encrypt passwords using various algorithms
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Handle hash request
if (isset($_POST['hash_password'])) {
    header('Content-Type: application/json');
    
    $password = $_POST['password'] ?? '';
    $algorithm = $_POST['algorithm'] ?? 'bcrypt';
    $rounds = (int)($_POST['rounds'] ?? 10);
    
    if (empty($password)) {
        echo json_encode(['error' => 'Password is required']);
        exit;
    }
    
    $hashed = '';
    $info = [];
    
    try {
        switch ($algorithm) {
            case 'bcrypt':
                $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => $rounds]);
                $info = [
                    'algorithm' => 'bcrypt',
                    'cost' => $rounds,
                    'length' => strlen($hashed)
                ];
                break;
                
            case 'argon2i':
                if (defined('PASSWORD_ARGON2I')) {
                    $hashed = password_hash($password, PASSWORD_ARGON2I, [
                        'memory_cost' => 65536,
                        'time_cost' => 4,
                        'threads' => 3
                    ]);
                    $info = [
                        'algorithm' => 'argon2i',
                        'length' => strlen($hashed)
                    ];
                } else {
                    throw new Exception('Argon2i is not available in this PHP version');
                }
                break;
                
            case 'argon2id':
                if (defined('PASSWORD_ARGON2ID')) {
                    $hashed = password_hash($password, PASSWORD_ARGON2ID, [
                        'memory_cost' => 65536,
                        'time_cost' => 4,
                        'threads' => 3
                    ]);
                    $info = [
                        'algorithm' => 'argon2id',
                        'length' => strlen($hashed)
                    ];
                } else {
                    throw new Exception('Argon2id is not available in this PHP version');
                }
                break;
                
            case 'md5':
                $hashed = md5($password);
                $info = [
                    'algorithm' => 'md5',
                    'length' => strlen($hashed),
                    'warning' => 'MD5 is not secure for password hashing'
                ];
                break;
                
            case 'sha1':
                $hashed = sha1($password);
                $info = [
                    'algorithm' => 'sha1',
                    'length' => strlen($hashed),
                    'warning' => 'SHA1 is not secure for password hashing'
                ];
                break;
                
            case 'sha256':
                $hashed = hash('sha256', $password);
                $info = [
                    'algorithm' => 'sha256',
                    'length' => strlen($hashed),
                    'warning' => 'SHA256 without salt is not recommended for password hashing'
                ];
                break;
                
            default:
                throw new Exception('Unknown algorithm');
        }
        
        echo json_encode([
            'success' => true,
            'hashed' => $hashed,
            'info' => $info
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Verify password
if (isset($_POST['verify_password'])) {
    header('Content-Type: application/json');
    
    $password = $_POST['password'] ?? '';
    $hash = $_POST['hash'] ?? '';
    
    if (empty($password) || empty($hash)) {
        echo json_encode(['error' => 'Password and hash are required']);
        exit;
    }
    
    $verified = password_verify($password, $hash);
    
    echo json_encode([
        'success' => true,
        'verified' => $verified,
        'message' => $verified ? 'Password matches!' : 'Password does not match!'
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Hasher Tool</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 600px;
            width: 100%;
            padding: 30px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        input[type="text"],
        input[type="password"],
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .algorithm-info {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .algorithm-info > div {
            flex: 1;
        }
        
        .rounds-input {
            width: 100px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            margin-bottom: 10px;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #f5f5f5;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .result-box {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 2px solid #e0e0e0;
            display: none;
        }
        
        .result-box.show {
            display: block;
        }
        
        .result-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .result-value {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            word-break: break-all;
            background: white;
            padding: 12px;
            border-radius: 4px;
            border: 1px solid #ddd;
            margin-bottom: 15px;
        }
        
        .copy-btn {
            background: #27ae60;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .copy-btn:hover {
            background: #229954;
        }
        
        .copy-btn.copied {
            background: #2ecc71;
        }
        
        .info-box {
            padding: 12px;
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            border-radius: 4px;
            margin-top: 15px;
            font-size: 13px;
            color: #1976d2;
        }
        
        .warning-box {
            padding: 12px;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 4px;
            margin-top: 15px;
            font-size: 13px;
            color: #856404;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Password Hasher Tool</h1>
        <p class="subtitle">Hash and verify passwords securely</p>
        
        <div class="tabs">
            <button class="tab active" onclick="switchTab('hash')">Hash Password</button>
            <button class="tab" onclick="switchTab('verify')">Verify Password</button>
        </div>
        
        <!-- Hash Tab -->
        <div id="hashTab" class="tab-content active">
            <form id="hashForm">
                <div class="form-group">
                    <label>Password to Hash</label>
                    <input type="password" id="passwordInput" name="password" placeholder="Enter password" required autofocus>
                </div>
                
                <div class="algorithm-info">
                    <div class="form-group">
                        <label>Algorithm</label>
                        <select id="algorithmSelect" name="algorithm">
                            <option value="bcrypt" selected>BCrypt (Recommended)</option>
                            <option value="argon2id">Argon2ID (Most Secure)</option>
                            <option value="argon2i">Argon2I</option>
                            <option value="sha256">SHA-256</option>
                            <option value="sha1">SHA-1</option>
                            <option value="md5">MD5</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="roundsGroup">
                        <label>BCrypt Cost (Rounds)</label>
                        <input type="number" id="roundsInput" name="rounds" value="10" min="4" max="31" class="rounds-input">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Generate Hash</button>
            </form>
            
            <div id="hashResult" class="result-box">
                <div class="result-label">Hashed Password:</div>
                <div class="result-value" id="hashOutput"></div>
                <button class="copy-btn" onclick="copyToClipboard('hashOutput')">Copy Hash</button>
                <div id="hashInfo" class="info-box" style="display: none;"></div>
                <div id="hashWarning" class="warning-box" style="display: none;"></div>
            </div>
        </div>
        
        <!-- Verify Tab -->
        <div id="verifyTab" class="tab-content">
            <form id="verifyForm">
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" id="verifyPasswordInput" name="password" placeholder="Enter password" required>
                </div>
                
                <div class="form-group">
                    <label>Hash to Verify</label>
                    <input type="text" id="verifyHashInput" name="hash" placeholder="Paste the hash here" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Verify Password</button>
            </form>
            
            <div id="verifyResult" class="result-box">
                <div id="verifyMessage"></div>
            </div>
        </div>
    </div>
    
    <script>
        function switchTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            if (tab === 'hash') {
                document.querySelector('.tab:first-child').classList.add('active');
                document.getElementById('hashTab').classList.add('active');
            } else {
                document.querySelector('.tab:last-child').classList.add('active');
                document.getElementById('verifyTab').classList.add('active');
            }
        }
        
        document.getElementById('algorithmSelect').addEventListener('change', function() {
            const roundsGroup = document.getElementById('roundsGroup');
            if (this.value === 'bcrypt') {
                roundsGroup.style.display = 'block';
            } else {
                roundsGroup.style.display = 'none';
            }
        });
        
        document.getElementById('hashForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('hash_password', '1');
            
            const resultBox = document.getElementById('hashResult');
            resultBox.innerHTML = '<div class="loading">Hashing password...</div>';
            resultBox.classList.add('show');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    resultBox.innerHTML = `<div class="warning-box">Error: ${data.error}</div>`;
                    return;
                }
                
                resultBox.innerHTML = `
                    <div class="result-label">Hashed Password:</div>
                    <div class="result-value" id="hashOutput">${data.hashed}</div>
                    <button class="copy-btn" onclick="copyToClipboard('hashOutput')">Copy Hash</button>
                    ${data.info.warning ? `<div class="warning-box">⚠️ ${data.info.warning}</div>` : ''}
                    <div class="info-box">
                        Algorithm: ${data.info.algorithm} | Length: ${data.info.length} characters
                        ${data.info.cost ? ` | Cost: ${data.info.cost}` : ''}
                    </div>
                `;
            })
            .catch(err => {
                resultBox.innerHTML = `<div class="warning-box">Error: ${err.message}</div>`;
            });
        });
        
        document.getElementById('verifyForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('verify_password', '1');
            
            const resultBox = document.getElementById('verifyResult');
            resultBox.innerHTML = '<div class="loading">Verifying password...</div>';
            resultBox.classList.add('show');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    resultBox.innerHTML = `<div class="warning-box">Error: ${data.error}</div>`;
                    return;
                }
                
                const className = data.verified ? 'info-box' : 'warning-box';
                const icon = data.verified ? '✅' : '❌';
                resultBox.innerHTML = `<div class="${className}">${icon} ${data.message}</div>`;
            })
            .catch(err => {
                resultBox.innerHTML = `<div class="warning-box">Error: ${err.message}</div>`;
            });
        });
        
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            const text = element.textContent;
            
            navigator.clipboard.writeText(text).then(() => {
                const btn = event.target;
                const originalText = btn.textContent;
                btn.textContent = 'Copied!';
                btn.classList.add('copied');
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.classList.remove('copied');
                }, 2000);
            }).catch(err => {
                alert('Failed to copy to clipboard');
            });
        }
    </script>
</body>
</html>
