<?php
header('Content-Type: text/html; charset=utf-8');

// Handle POST request untuk send requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'send_requests') {
        $url = $_POST['url'];
        $method = $_POST['method'];
        $jumlah = intval($_POST['jumlah']);
        $delay = intval($_POST['delay']);
        $data = $_POST['data'];
        $use_random_ua = isset($_POST['use_random_ua']);
        
        // Limit jumlah requests
        $max_requests = 100000;
        if ($jumlah > $max_requests) {
            $jumlah = $max_requests;
        }
        
        $success_count = 0;
        $failed_count = 0;
        $total_response_time = 0;
        
        // User Agents premium
        $user_agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:121.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Edge/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (iPad; CPU OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36'
        ];
        
        for ($i = 1; $i <= $jumlah; $i++) {
            $startTime = microtime(true);
            
            // Select random user agent
            $current_ua = $use_random_ua ? $user_agents[array_rand($user_agents)] : 'Premium-Bomber/2.0';
            
            // Send request TANPA PROXY (lebih reliable)
            $result = sendPremiumRequest($url, $method, $data, $current_ua);
            
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);
            
            $status_ok = $result['status'] >= 200 && $result['status'] < 400;
            if ($status_ok) {
                $success_count++;
            } else {
                $failed_count++;
            }
            
            $total_response_time += $responseTime;
            
            $result_data = [
                'request' => $i,
                'url' => $url,
                'method' => $method,
                'status' => $result['status'],
                'status_text' => $result['status_text'],
                'response_time' => $responseTime,
                'response' => $result['response'],
                'user_agent' => $current_ua,
                'timestamp' => date('H:i:s'),
                'success' => $status_ok,
                'size' => $result['size']
            ];
            
            // Kirim progress SETIAP REQUEST (bukan setiap 2)
            echo json_encode([
                'type' => 'progress',
                'current' => $i,
                'total' => $jumlah,
                'success' => $success_count,
                'failed' => $failed_count,
                'avg_time' => $i > 0 ? round($total_response_time / $i, 2) : 0,
                'result' => $result_data
            ]);
            echo "\n";
            ob_flush();
            flush();
            
            // Delay antara requests
            if ($i < $jumlah && $delay > 0) {
                usleep($delay * 1000);
            }
            
            // Break jika connection closed
            if (connection_aborted()) {
                break;
            }
        }
        
        echo json_encode([
            'type' => 'complete',
            'total_requests' => $jumlah,
            'successful' => $success_count,
            'failed' => $failed_count,
            'average_time' => $jumlah > 0 ? round($total_response_time / $jumlah, 2) : 0
        ]);
        exit;
    }
}

function sendPremiumRequest($url, $method, $data = '', $user_agent = '') {
    $ch = curl_init();
    
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15, // Timeout lebih pendek
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HEADER => true,
        CURLOPT_USERAGENT => $user_agent,
        CURLOPT_ENCODING => 'gzip, deflate',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Accept-Encoding: gzip, deflate',
            'Cache-Control: no-cache',
            'Connection: keep-alive'
        ]
    ];
    
    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = $data;
        $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
    }
    
    curl_setopt_array($ch, $options);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    $downloadSize = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
    
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return [
            'status' => 0,
            'status_text' => 'CURL_ERROR',
            'response' => $error,
            'size' => 0
        ];
    }
    
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    curl_close($ch);
    
    // Get status text
    $status_text = getHttpStatusText($httpCode);
    
    return [
        'status' => $httpCode,
        'status_text' => $status_text,
        'response' => substr($body, 0, 200), // Limit response length
        'size' => $downloadSize
    ];
}

function getHttpStatusText($code) {
    $status_codes = [
        200 => 'OK',
        201 => 'Created',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        304 => 'Not Modified',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable'
    ];
    
    return $status_codes[$code] ?? 'Unknown';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚡ PREMIUM REQUEST BOMBER - ULTRA EDITION</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0066ff;
            --primary-glow: #0066ff;
            --secondary: #00ff88;
            --accent: #ff0080;
            --dark: #0a0a0a;
            --darker: #000000;
            --card-bg: rgba(255, 255, 255, 0.05);
            --card-border: rgba(255, 255, 255, 0.1);
            --text-primary: #ffffff;
            --text-secondary: #a0a0a0;
            --success: #00ff88;
            --danger: #ff4444;
            --warning: #ffaa00;
            --info: #00a8ff;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', 'SF Pro Display', system-ui, sans-serif;
            background: linear-gradient(135deg, var(--darker) 0%, #001233 50%, var(--dark) 100%);
            color: var(--text-primary);
            min-height: 100vh;
            overflow-x: hidden;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header Styles */
        .header {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, var(--primary-glow) 0%, transparent 70%);
            opacity: 0.1;
            z-index: -1;
        }
        
        .title {
            font-size: 3.5rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 50%, var(--accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
            text-shadow: 0 0 50px rgba(0, 102, 255, 0.5);
            letter-spacing: -1px;
        }
        
        .subtitle {
            font-size: 1.3rem;
            color: var(--text-secondary);
            margin-bottom: 20px;
        }
        
        /* Dashboard Grid */
        .dashboard {
            display: grid;
            grid-template-columns: 400px 1fr;
            grid-template-rows: auto auto;
            gap: 25px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 1024px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
        }
        
        /* Card Styles */
        .card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--primary), transparent);
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--card-border);
        }
        
        .card-header i {
            font-size: 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .card-header h2 {
            font-size: 1.4rem;
            font-weight: 700;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-input {
            width: 100%;
            padding: 16px 20px;
            background: rgba(255, 255, 255, 0.08);
            border: 2px solid rgba(255, 255, 255, 0.15);
            border-radius: 16px;
            color: var(--text-primary);
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 102, 255, 0.2);
            background: rgba(255, 255, 255, 0.12);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .checkbox-group {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 12px 18px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        
        .checkbox-label:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--primary);
        }
        
        .checkbox-label input[type="checkbox"] {
            width: auto;
        }
        
        /* Button Styles */
        .btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-glow) 100%);
            color: white;
            border: none;
            padding: 20px 40px;
            border-radius: 16px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 102, 255, 0.4);
        }
        
        .btn:active {
            transform: translateY(-1px);
        }
        
        .btn-stop {
            background: linear-gradient(135deg, var(--danger) 0%, #cc0000 100%);
        }
        
        .btn-stop:hover {
            box-shadow: 0 10px 30px rgba(255, 68, 68, 0.4);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 25px 0;
        }
        
        .stat-card {
            background: rgba(0, 102, 255, 0.1);
            padding: 20px;
            border-radius: 16px;
            text-align: center;
            border: 1px solid rgba(0, 102, 255, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .stat-value {
            font-size: 2.2rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.8rem;
            opacity: 0.8;
            margin-top: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Progress Styles */
        .progress-container {
            margin: 25px 0;
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 4px;
            transition: width 0.5s ease;
            position: relative;
        }
        
        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 20px;
            background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.3) 100%);
            animation: shimmer 2s infinite;
        }
        
        /* Gauge Styles */
        .gauge-container {
            position: relative;
            width: 180px;
            height: 180px;
            margin: 20px auto;
        }
        
        .gauge {
            width: 100%;
            height: 100%;
        }
        
        .gauge-value {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.8rem;
            font-weight: 900;
            text-align: center;
        }
        
        .gauge-label {
            position: absolute;
            bottom: 20%;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.8rem;
            opacity: 0.7;
        }
        
        /* Results Styles */
        .results-container {
            max-height: 600px;
            overflow-y: auto;
            margin-top: 20px;
        }
        
        .result-item {
            padding: 20px;
            margin-bottom: 12px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            border-left: 4px solid var(--primary);
            animation: slideIn 0.4s ease;
            position: relative;
            overflow: hidden;
        }
        
        .result-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
        }
        
        .result-item.success {
            border-left-color: var(--success);
            background: rgba(0, 255, 136, 0.08);
        }
        
        .result-item.error {
            border-left-color: var(--danger);
            background: rgba(255, 68, 68, 0.08);
        }
        
        .result-item.warning {
            border-left-color: var(--warning);
            background: rgba(255, 170, 0, 0.08);
        }
        
        .result-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .result-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        
        .result-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .badge-success { background: rgba(0, 255, 136, 0.2); color: var(--success); }
        .badge-error { background: rgba(255, 68, 68, 0.2); color: var(--danger); }
        .badge-warning { background: rgba(255, 170, 0, 0.2); color: var(--warning); }
        .badge-info { background: rgba(0, 168, 255, 0.2); color: var(--info); }
        
        .result-details {
            font-size: 0.9rem;
            opacity: 0.9;
            line-height: 1.5;
        }
        
        /* Status Indicator */
        .status-indicator {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
        }
        
        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--success);
            position: relative;
        }
        
        .status-dot::after {
            content: '';
            position: absolute;
            top: -4px;
            left: -4px;
            right: -4px;
            bottom: -4px;
            border-radius: 50%;
            background: var(--success);
            opacity: 0.4;
            animation: pulse 2s infinite;
        }
        
        .status-dot.offline {
            background: var(--danger);
        }
        
        .status-dot.offline::after {
            background: var(--danger);
        }
        
        /* Animations */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.4; }
            50% { transform: scale(1.5); opacity: 0.2; }
            100% { transform: scale(1); opacity: 0.4; }
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, var(--primary-glow), var(--secondary));
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .title { font-size: 2.5rem; }
            .subtitle { font-size: 1.1rem; }
            .form-row { grid-template-columns: 1fr; }
            .checkbox-group { flex-direction: column; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="title">⚡ PREMIUM REQUEST BOMBER</h1>
            <p class="subtitle">Ultimate HTTP Request Tool with Real-time Analytics</p>
        </div>
        
        <div class="dashboard">
            <!-- Control Panel -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-sliders-h"></i>
                    <h2>CONTROL PANEL</h2>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-target"></i> TARGET URL
                    </label>
                    <input type="url" id="url" class="form-input" value="https://httpbin.org/get" placeholder="https://example.com/api" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-code"></i> METHOD
                        </label>
                        <select id="method" class="form-input">
                            <option value="GET">GET</option>
                            <option value="POST">POST</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-hashtag"></i> REQUESTS
                        </label>
                        <input type="number" id="jumlah" class="form-input" min="1" max="100000" value="50">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-clock"></i> DELAY (ms)
                        </label>
                        <input type="number" id="delay" class="form-input" min="0" max="10000" value="200">
                    </div>
                </div>
                
                <div class="form-group" id="postDataGroup" style="display: none;">
                    <label class="form-label">
                        <i class="fas fa-database"></i> POST DATA
                    </label>
                    <textarea id="data" class="form-input" placeholder='{"key": "value"}' rows="3"></textarea>
                </div>
                
                <div class="checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="use_random_ua" checked>
                        <i class="fas fa-user-secret"></i> Random User Agent
                    </label>
                </div>
                
                <button class="btn" onclick="startAttack()">
                    <i class="fas fa-rocket"></i> LAUNCH ATTACK
                </button>
                <button class="btn btn-stop" onclick="stopAttack()" style="display: none; margin-top: 15px;">
                    <i class="fas fa-stop"></i> STOP ATTACK
                </button>
            </div>
            
            <!-- Metrics Panel -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-line"></i>
                    <h2>LIVE METRICS</h2>
                </div>
                
                <div class="status-indicator">
                    <div class="status-dot" id="statusDot"></div>
                    <div>
                        <div style="font-weight: 600;">Status: <span id="statusText">Ready</span></div>
                        <div style="font-size: 0.8rem; opacity: 0.7;" id="statusSubtext">Waiting for launch command</div>
                    </div>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value" id="statRequests">0</div>
                        <div class="stat-label">Requests Sent</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="statSuccess">0</div>
                        <div class="stat-label">Successful</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="statFailed">0</div>
                        <div class="stat-label">Failed</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="statAvgTime">0ms</div>
                        <div class="stat-label">Avg Response</div>
                    </div>
                </div>
                
                <div class="progress-container">
                    <div class="progress-header">
                        <span>Attack Progress</span>
                        <span id="progressText">0%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill" style="width: 0%"></div>
                    </div>
                </div>
                
                <div class="gauge-container">
                    <div class="gauge">
                        <svg viewBox="0 0 100 100" style="width: 100%; height: 100%;">
                            <path d="M 10,50 A 40,40 0 1,1 90,50" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="8"/>
                            <path id="gaugeFill" d="M 10,50 A 40,40 0 1,1 90,50" fill="none" stroke="url(#gaugeGradient)" stroke-width="8" stroke-dasharray="251.2" stroke-dashoffset="251.2"/>
                            <defs>
                                <linearGradient id="gaugeGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                    <stop offset="0%" stop-color="#0066ff"/>
                                    <stop offset="100%" stop-color="#00ff88"/>
                                </linearGradient>
                            </defs>
                        </svg>
                    </div>
                    <div class="gauge-value" id="gaugeValue">0ms</div>
                    <div class="gauge-label">Response Time</div>
                </div>
            </div>
            
            <!-- Results Panel -->
            <div class="card" style="grid-column: 1 / -1;">
                <div class="card-header">
                    <i class="fas fa-list"></i>
                    <h2>REAL-TIME RESULTS <span id="resultsCount" style="font-size: 1rem; opacity: 0.7;">(0 requests)</span></h2>
                </div>
                <div class="results-container" id="resultsContainer">
                    <div style="text-align: center; padding: 60px 20px; opacity: 0.5;">
                        <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.3;"></i>
                        <p style="font-size: 1.1rem;">No requests yet. Launch attack to see real-time results!</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let isAttacking = false;
        let currentRequest = 0;
        let totalRequests = 0;
        let successCount = 0;
        let failedCount = 0;
        let totalResponseTime = 0;
        
        // Toggle POST data field
        document.getElementById('method').addEventListener('change', function() {
            document.getElementById('postDataGroup').style.display = 
                this.value === 'POST' ? 'block' : 'none';
        });
        
        function startAttack() {
            if (isAttacking) return;
            
            const url = document.getElementById('url').value;
            const method = document.getElementById('method').value;
            const jumlah = parseInt(document.getElementById('jumlah').value);
            const delay = parseInt(document.getElementById('delay').value);
            const data = document.getElementById('data').value;
            const use_random_ua = document.getElementById('use_random_ua').checked;
            
            if (!url) {
                alert('Please enter a target URL');
                return;
            }
            
            if (jumlah < 1 || jumlah > 100000) {
                alert('Request count must be between 1 and 100,000');
                return;
            }
            
            isAttacking = true;
            currentRequest = 0;
            totalRequests = jumlah;
            successCount = 0;
            failedCount = 0;
            totalResponseTime = 0;
            
            // Reset UI
            document.getElementById('resultsContainer').innerHTML = '';
            document.getElementById('statusDot').classList.remove('offline');
            document.getElementById('statusText').textContent = 'Attacking...';
            document.getElementById('statusSubtext').textContent = `Sending ${jumlah} requests to ${url}`;
            document.querySelector('.btn-stop').style.display = 'block';
            document.querySelector('.btn').style.display = 'none';
            
            updateStats();
            updateGauge(0);
            
            // Start attack
            sendAdvancedAttack(url, method, jumlah, delay, data, use_random_ua);
        }
        
        function stopAttack() {
            isAttacking = false;
            document.getElementById('statusText').textContent = 'Stopped';
            document.getElementById('statusSubtext').textContent = 'Attack terminated by user';
            document.getElementById('statusDot').classList.add('offline');
            document.querySelector('.btn-stop').style.display = 'none';
            document.querySelector('.btn').style.display = 'block';
            addResult('🛑 Attack stopped by user', 'warning');
        }
        
        function sendAdvancedAttack(url, method, jumlah, delay, data, use_random_ua) {
            const formData = new FormData();
            formData.append('action', 'send_requests');
            formData.append('url', url);
            formData.append('method', method);
            formData.append('jumlah', jumlah);
            formData.append('delay', delay);
            formData.append('data', data);
            formData.append('use_random_ua', use_random_ua);
            
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                
                function read() {
                    return reader.read().then(({done, value}) => {
                        if (done) {
                            isAttacking = false;
                            document.getElementById('statusText').textContent = 'Complete';
                            document.getElementById('statusSubtext').textContent = 'All requests completed';
                            document.getElementById('statusDot').classList.add('offline');
                            document.querySelector('.btn-stop').style.display = 'none';
                            document.querySelector('.btn').style.display = 'block';
                            return;
                        }
                        
                        const text = decoder.decode(value);
                        const lines = text.trim().split('\n');
                        
                        lines.forEach(line => {
                            if (line) {
                                try {
                                    const data = JSON.parse(line);
                                    if (data.type === 'progress') {
                                        handleProgress(data);
                                    } else if (data.type === 'complete') {
                                        handleComplete(data);
                                    }
                                } catch (e) {
                                    console.error('Parse error:', e);
                                }
                            }
                        });
                        
                        return read();
                    });
                }
                
                return read();
            })
            .catch(error => {
                isAttacking = false;
                document.getElementById('statusText').textContent = 'Error';
                document.getElementById('statusSubtext').textContent = 'Network connection failed';
                document.getElementById('statusDot').classList.add('offline');
                document.querySelector('.btn-stop').style.display = 'none';
                document.querySelector('.btn').style.display = 'block';
                addResult('❌ Network error: ' + error, 'error');
            });
        }
        
        function handleProgress(data) {
            currentRequest = data.current;
            successCount = data.success;
            failedCount = data.failed;
            totalResponseTime = data.avg_time * data.current;
            
            updateStats();
            updateGauge(data.avg_time);
            
            // Add result to list untuk SETIAP REQUEST
            if (data.result) {
                addRequestResult(data.result);
            }
        }
        
        function handleComplete(data) {
            addResult(`🎉 Attack completed! ${data.successful} successful, ${data.failed} failed. Average response: ${data.average_time}ms`, 'success');
        }
        
        function addRequestResult(result) {
            const isSuccess = result.success;
            const itemClass = isSuccess ? 'success' : 'error';
            const statusIcon = isSuccess ? '✅' : '❌';
            const statusText = isSuccess ? 'SUCCESS' : 'FAILED';
            const badgeClass = isSuccess ? 'badge-success' : 'badge-error';
            
            // Format response time color
            let timeColor = '#00ff88';
            if (result.response_time > 500) timeColor = '#ffaa00';
            if (result.response_time > 1000) timeColor = '#ff4444';
            
            const resultHTML = `
                <div class="result-item ${itemClass}">
                    <div class="result-header">
                        <div style="flex: 1;">
                            <strong>#${result.request}</strong> • 
                            <span class="result-badge ${badgeClass}">${statusIcon} ${statusText}</span> • 
                            <span style="color: ${timeColor}">⏱️ ${result.response_time}ms</span>
                        </div>
                        <div style="opacity: 0.7; font-size: 0.9rem;">${result.timestamp}</div>
                    </div>
                    
                    <div class="result-meta">
                        <span class="result-badge badge-info">📍 ${result.status} ${result.status_text}</span>
                        <span class="result-badge">🌐 ${result.method}</span>
                        <span class="result-badge">📦 ${result.size} bytes</span>
                    </div>
                    
                    ${result.response ? `
                    <div class="result-details">
                        <strong>Response:</strong> ${result.response}
                    </div>
                    ` : ''}
                </div>
            `;
            
            const resultsContainer = document.getElementById('resultsContainer');
            
            // Remove placeholder jika ada
            if (resultsContainer.children.length === 1 && resultsContainer.children[0].style.textAlign) {
                resultsContainer.innerHTML = '';
            }
            
            resultsContainer.insertAdjacentHTML('afterbegin', resultHTML);
            document.getElementById('resultsCount').textContent = `(${currentRequest} requests)`;
            
            // Limit results to 50 items untuk performance
            if (resultsContainer.children.length > 50) {
                resultsContainer.removeChild(resultsContainer.lastChild);
            }
        }
        
        function addResult(content, type = '') {
            const div = document.createElement('div');
            div.className = `result-item ${type}`;
            div.innerHTML = content;
            document.getElementById('resultsContainer').insertAdjacentElement('afterbegin', div);
        }
        
        function updateStats() {
            document.getElementById('statRequests').textContent = currentRequest;
            document.getElementById('statSuccess').textContent = successCount;
            document.getElementById('statFailed').textContent = failedCount;
            
            const avgTime = currentRequest > 0 ? Math.round(totalResponseTime / currentRequest) : 0;
            document.getElementById('statAvgTime').textContent = avgTime + 'ms';
            
            const progress = totalRequests > 0 ? (currentRequest / totalRequests) * 100 : 0;
            document.getElementById('progressFill').style.width = progress + '%';
            document.getElementById('progressText').textContent = Math.round(progress) + '%';
        }
        
        function updateGauge(responseTime) {
            const gauge = document.getElementById('gaugeFill');
            const gaugeValue = document.getElementById('gaugeValue');
            
            // Calculate gauge value (0-251.2 dashoffset)
            let speed = Math.min(responseTime, 2000); // Cap at 2000ms
            let gaugePercent = 1 - (speed / 2000);
            let dashoffset = 251.2 * (1 - gaugePercent);
            
            gauge.style.strokeDashoffset = dashoffset;
            gaugeValue.textContent = Math.round(responseTime) + 'ms';
            
            // Change color based on response time
            if (responseTime < 200) {
                gaugeValue.style.color = '#00ff88';
            } else if (responseTime < 500) {
                gaugeValue.style.color = '#ffaa00';
            } else {
                gaugeValue.style.color = '#ff4444';
            }
        }
        
        // Initialize
        document.getElementById('method').dispatchEvent(new Event('change'));
        updateGauge(0);
    </script>
</body>
</html>