<?php
// Simple test to confirm backend is working
header('Content-Type: application/json');
echo json_encode([
    'message' => 'Backend is running 🚀',
    'status' => 'healthy',
    'timestamp' => date('Y-m-d H:i:s'),
    'version' => '1.0.0',
    'environment' => $_ENV['ENVIRONMENT'] ?? 'development'
]);
?>
