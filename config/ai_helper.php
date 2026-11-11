<?php
// config/ai_helper.php

function generateAISummary($hotspot_stats, $context_stats) {
    $url = 'http://localhost:11434/api/generate';
    
    // A stricter prompt to prevent looping
    $prompt = "You are an AI assistant for a University Lost and Found system. 
    Here are the top loss locations: $hotspot_stats. 
    Here are the top reasons items are lost: $context_stats. 
    Write exactly THREE bullet points summarizing these findings for the University Administration. 
    Each bullet point must be a single, concise sentence suggesting an action (e.g., orientation, signage, awareness). 
    Do NOT write an introduction. Do NOT write a conclusion. Just the three bullet points.";
    
    $data = [
        'model' => 'phi3',
        'prompt' => $prompt,
        'stream' => false,
        'options' => [
            'temperature' => 0.2, // Lower temperature = less creative/looping
            'num_predict' => 150  // Limit the length so it doesn't ramble
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); 

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && $response) {
        $json = json_decode($response, true);
        return $json['response'] ?? 'AI failed to generate a response.';
    } else {
        return 'Error: Could not connect to the local AI. Is Ollama running?';
    }
}
?>