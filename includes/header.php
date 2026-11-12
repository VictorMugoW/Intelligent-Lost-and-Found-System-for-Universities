<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Intelligent Lost and Found System' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="/intelligent-lost-and-found/assets/css/style.css" rel="stylesheet">
    
    <!-- Custom Styles for the Samsung-style Toast -->
    <style>
        .custom-toast-container {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: center;
            pointer-events: none;
        }
        .custom-toast {
            background: rgba(15, 23, 42, 0.95); /* Dark glass */
            backdrop-filter: blur(10px);
            color: white;
            padding: 12px 24px;
            border-radius: 50px; /* Pill shape */
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            font-weight: 500;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
            animation: dropDown 0.4s ease-out forwards;
            pointer-events: auto;
        }
        .custom-toast.hiding {
            animation: slideUp 0.3s ease-in forwards;
        }
        
        /* Different colors for different statuses */
        .custom-toast.toast-success i { color: #10B981; }
        .custom-toast.toast-danger i { color: #EF4444; }
        .custom-toast.toast-warning i { color: #F59E0B; }
        .custom-toast.toast-info i { color: #3B82F6; }

        @keyframes dropDown {
            0% { opacity: 0; transform: translateY(-50px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideUp {
            0% { opacity: 1; transform: translateY(0); }
            100% { opacity: 0; transform: translateY(-50px); }
        }
    </style>
</head>
<body>

    <!-- TOAST NOTIFICATION CONTAINER (Centered at top) -->
    <div class="custom-toast-container" id="toastContainer"></div>