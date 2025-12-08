<?php
require_once 'db_config.php';
session_start();

// Function to get authenticated Firebase Storage URL
function getAuthenticatedStorageUrl($path) {
    try {
        // Get the correct Firebase Storage bucket
        $bucket = get_storage_bucket();
        
        // Get the object
        $object = $bucket->object($path);
        
        // Generate a signed URL (valid for 1 hour)
        $signedUrl = $object->signedUrl(
            new DateTime('+1 hour'),
            [
                'version' => 'v4'
            ]
        );
        
        return $signedUrl;
        
    } catch (Exception $e) {
        error_log("Error generating signed URL: " . $e->getMessage());
        return null;
    }
}

// Proof image proxy to handle Firebase Storage authentication
if (isset($_GET['path']) && isset($_GET['user'])) {
    $proofPath = $_GET['path'];
    $userId = $_GET['user'];
    
    // Verify user has permission to view this proof
    // Basic security check - you might want to add more
    if (empty($proofPath) || empty($userId)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid parameters']);
        exit;
    }
    
    // Try to get authenticated URL first
    $signedUrl = getAuthenticatedStorageUrl($proofPath);
    
    if ($signedUrl) {
        // Redirect to the signed URL
        header('Location: ' . $signedUrl);
        exit;
    }
    
    // Fallback: Try direct access with authentication headers
    $url = get_storage_url($proofPath);
    
    // Create a curl request with proper headers
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'iBantay-Server/1.0');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // Add Firebase authentication if available
    try {
        $token = firestore_rest_token();
        if ($token) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token
            ]);
        }
    } catch (Exception $e) {
        error_log("Error getting Firebase token: " . $e->getMessage());
    }
    
    $imageData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    
    if ($httpCode == 200 && $imageData) {
        // Return the image with proper headers
        header('Content-Type: ' . ($contentType ?: 'image/jpeg'));
        header('Content-Length: ' . strlen($imageData));
        header('Cache-Control: private, max-age=3600');
        echo $imageData;
        exit;
    } else {
        // Return error
        http_response_code($httpCode ?: 404);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Image not accessible', 
            'code' => $httpCode,
            'path' => $proofPath,
            'url' => $url
        ]);
        exit;
    }
}

// If no parameters, show error
http_response_code(400);
header('Content-Type: application/json');
echo json_encode(['error' => 'Missing parameters']);
?>
