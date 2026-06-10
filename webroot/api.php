<?php
// API endpoint for QuiteKill app selection interface

header('Content-Type: application/json');

// Get the selected packages from ignore.txt file
function getIgnoredPackages() {
    $file = '/data/adb/modules/QuiteKill/ignore.txt';
    if (!file_exists($file)) {
        return [];
    }
    
    $packages = [];
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line) && strpos($line, '#') !== 0) {
            $packages[] = $line;
        }
    }
    return $packages;
}

// Handle API requests
$requestMethod = $_SERVER['REQUEST_METHOD'];

if ($requestMethod === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action === 'apps') {
        // Get list of all installed apps with their status
        exec('pm list packages -3 | cut -d: -f2', $output, $returnCode);
        
        $allApps = [];
        $ignoredPackages = getIgnoredPackages();
        
        foreach ($output as $pkg) {
            if (empty(trim($pkg))) continue;
            
            // Get app name from package manager
            $appName = 'Unknown';
            exec("pm list apps -e \"$pkg\" 2>/dev/null | head -1", $appOutput, $appReturn);
            
            // Try to get human-readable name using dumpsys package
            $nameCmd = "dumpsys package $pkg 2>/dev/null | grep 'mName=' | sed 's/.*mName=\\([^ ]*\\).*/\\1/'";
            exec($nameCmd, $nameOutput, $nameReturn);
            
            if (!empty(trim($nameOutput[0]))) {
                $appName = trim($nameOutput[0]);
            }
            
            // Check if app is running
            $isRunning = false;
            exec("pidof \"$pkg\" 2>/dev/null", $pidOutput, $pidReturn);
            if (!empty(trim($pidOutput[0]))) {
                $isRunning = true;
            }
            
            $allApps[] = [
                'package' => $pkg,
                'name' => $appName ?: $pkg,
                'installed' => true,
                'isRunning' => $isRunning
            ];
        }
        
        echo json_encode($allApps);
    } elseif ($action === 'ignored') {
        // Get currently ignored packages
        echo json_encode(array_values($ignoredPackages));
    }
} else if ($requestMethod === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!empty($data)) {
            $action = $data['action'] ?? null;
            
            if ($action === 'save-selection') {
                // Save selected packages to ignore.txt
                if (isset($data['packages']) && is_array($data['packages'])) {
                    $selectedPackages = array_values(array_unique($data['packages']));
                    
                    // Sort and write to file
                    sort($selectedPackages);
                    $content = implode("\n", $selectedPackages) . "\n";
                    
                    file_put_contents('/data/adb/modules/QuiteKill/ignore.txt', $content);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Saved ' . count($selectedPackages) . ' packages'
                    ]);
                } else {
                    echo json_encode(['error' => 'No packages provided']);
                }
            } elseif ($action === 'get-ignored') {
                $ignored = getIgnoredPackages();
                echo json_encode(array_values($ignored));
            }
        } else {
            echo json_encode(['error' => 'Invalid JSON input']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Unsupported content type']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
