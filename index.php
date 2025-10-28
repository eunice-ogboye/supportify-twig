<?php
require_once 'vendor/autoload.php';

session_start();

// Initialize Twig
$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader, [
    'cache' => false, // Set to 'cache' directory in production
    'debug' => true,
]);

// Simple routing
$page = $_GET['page'] ?? 'landing';

// Mock user session (in production, use proper authentication)
if (!isset($_SESSION['user']) && in_array($page, ['dashboard', 'tickets'])) {
    header('Location: index.php?page=login');
    exit;
}

// Mock tickets data (in production, use database)
if (!isset($_SESSION['tickets'])) {
    $_SESSION['tickets'] = [
        [
            'id' => 1,
            'title' => 'ngsfhegfh',
            'description' => 'sdmfgsgrfwjrhf',
            'status' => 'Open'
        ],
        [
            'id' => 2,
            'title' => 'sdfvsmhdfs',
            'description' => 'dfskdyfu',
            'status' => 'Open'
        ]
    ];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'login':
            $_SESSION['user'] = [
                'name' => 'User',
                'email' => $_POST['email'] ?? ''
            ];
            header('Location: index.php?page=dashboard');
            exit;
            
        case 'register':
            $_SESSION['user'] = [
                'name' => $_POST['name'] ?? 'User',
                'email' => $_POST['email'] ?? ''
            ];
            header('Location: index.php?page=dashboard');
            exit;
            
        case 'logout':
            session_destroy();
            header('Location: index.php?page=landing');
            exit;
            
        case 'create_ticket':
            $newTicket = [
                'id' => count($_SESSION['tickets']) + 1,
                'title' => $_POST['title'] ?? '',
                'description' => $_POST['description'] ?? '',
                'status' => $_POST['status'] ?? 'Open'
            ];
            $_SESSION['tickets'][] = $newTicket;
            header('Location: index.php?page=tickets');
            exit;
            
        case 'delete_ticket':
            $ticketId = (int)$_POST['ticket_id'];
            $_SESSION['tickets'] = array_filter($_SESSION['tickets'], function($ticket) use ($ticketId) {
                return $ticket['id'] !== $ticketId;
            });
            $_SESSION['tickets'] = array_values($_SESSION['tickets']);
            header('Location: index.php?page=tickets');
            exit;
            
        case 'update_ticket':
            $ticketId = (int)$_POST['ticket_id'];
            foreach ($_SESSION['tickets'] as &$ticket) {
                if ($ticket['id'] === $ticketId) {
                    $ticket['title'] = $_POST['title'] ?? $ticket['title'];
                    $ticket['description'] = $_POST['description'] ?? $ticket['description'];
                    $ticket['status'] = $_POST['status'] ?? $ticket['status'];
                    break;
                }
            }
            header('Location: index.php?page=tickets');
            exit;
    }
}

// Calculate ticket stats
$stats = [
    'total' => count($_SESSION['tickets']),
    'open' => count(array_filter($_SESSION['tickets'], fn($t) => $t['status'] === 'Open')),
    'ongoing' => count(array_filter($_SESSION['tickets'], fn($t) => $t['status'] === 'Ongoing')),
    'resolved' => count(array_filter($_SESSION['tickets'], fn($t) => $t['status'] === 'Resolved')),
];

// Get ticket for editing
$editTicket = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($_SESSION['tickets'] as $ticket) {
        if ($ticket['id'] === $editId) {
            $editTicket = $ticket;
            break;
        }
    }
}

// Render the appropriate template
try {
    switch ($page) {
        case 'landing':
            echo $twig->render('landing.twig');
            break;
        case 'login':
            echo $twig->render('login.twig');
            break;
        case 'register':
            echo $twig->render('register.twig');
            break;
        case 'dashboard':
            echo $twig->render('dashboard.twig', [
                'user' => $_SESSION['user'],
                'stats' => $stats
            ]);
            break;
        case 'tickets':
            echo $twig->render('tickets.twig', [
                'user' => $_SESSION['user'],
                'tickets' => $_SESSION['tickets'],
                'editTicket' => $editTicket
            ]);
            break;
        default:
            header('HTTP/1.0 404 Not Found');
            echo '404 - Page Not Found';
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}