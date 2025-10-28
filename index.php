<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'vendor/autoload.php';

session_start();

// Initialize Twig
$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader, [
    'cache' => false,
    'debug' => true,
]);

// Simple routing
$page = $_GET['page'] ?? 'landing';

// Initialize users storage (in production, use database)
if (!isset($_SESSION['users'])) {
    $_SESSION['users'] = [];
}

// Initialize tickets storage
if (!isset($_SESSION['tickets'])) {
    $_SESSION['tickets'] = [];
}

// Migrate old tickets without user_id (add user_id to existing tickets)
foreach ($_SESSION['tickets'] as &$ticket) {
    if (!isset($ticket['user_id'])) {
        // Assign to current user or set to null for orphaned tickets
        $ticket['user_id'] = isset($_SESSION['user']) ? $_SESSION['user']['id'] : null;
    }
}
unset($ticket); // Break reference

// Error and success messages
$error = $_SESSION['error'] ?? null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);

// Protected pages - redirect to login if not authenticated
$protectedPages = ['dashboard', 'tickets'];
if (in_array($page, $protectedPages) && !isset($_SESSION['user'])) {
    $_SESSION['error'] = 'Please login to access this page';
    header('Location: index.php?page=login');
    exit;
}

// Redirect to dashboard if already logged in and trying to access auth pages
if (isset($_SESSION['user']) && in_array($page, ['login', 'register'])) {
    header('Location: index.php?page=dashboard');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'register':
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            // Validation
            $errors = [];
            
            if (empty($name)) {
                $errors[] = 'Full name is required';
            } elseif (strlen($name) < 2) {
                $errors[] = 'Name must be at least 2 characters';
            }
            
            if (empty($email)) {
                $errors[] = 'Email is required';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format';
            } else {
                // Check if email already exists
                foreach ($_SESSION['users'] as $user) {
                    if ($user['email'] === $email) {
                        $errors[] = 'Email already registered. Please login instead';
                        break;
                    }
                }
            }
            
            if (empty($password)) {
                $errors[] = 'Password is required';
            } elseif (strlen($password) < 6) {
                $errors[] = 'Password must be at least 6 characters';
            }
            
            if ($password !== $confirmPassword) {
                $errors[] = 'Passwords do not match';
            }
            
            if (!empty($errors)) {
                $_SESSION['error'] = implode('<br>', $errors);
                $_SESSION['form_data'] = ['name' => $name, 'email' => $email];
                header('Location: index.php?page=register');
                exit;
            }
            
            // Create user
            $newUser = [
                'id' => count($_SESSION['users']) + 1,
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $_SESSION['users'][] = $newUser;
            $_SESSION['user'] = [
                'id' => $newUser['id'],
                'name' => $newUser['name'],
                'email' => $newUser['email']
            ];
            
            unset($_SESSION['form_data']);
            $_SESSION['success'] = 'Registration successful! Welcome to Supportly';
            header('Location: index.php?page=dashboard');
            exit;
            
        case 'login':
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            // Validation
            $errors = [];
            
            if (empty($email)) {
                $errors[] = 'Email is required';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format';
            }
            
            if (empty($password)) {
                $errors[] = 'Password is required';
            }
            
            if (!empty($errors)) {
                $_SESSION['error'] = implode('<br>', $errors);
                $_SESSION['form_data'] = ['email' => $email];
                header('Location: index.php?page=login');
                exit;
            }
            
            // Find user
            $userFound = null;
            foreach ($_SESSION['users'] as $user) {
                if ($user['email'] === $email) {
                    $userFound = $user;
                    break;
                }
            }
            
            if (!$userFound || !password_verify($password, $userFound['password'])) {
                $_SESSION['error'] = 'Invalid email or password';
                $_SESSION['form_data'] = ['email' => $email];
                header('Location: index.php?page=login');
                exit;
            }
            
            // Login successful
            $_SESSION['user'] = [
                'id' => $userFound['id'],
                'name' => $userFound['name'],
                'email' => $userFound['email']
            ];
            
            unset($_SESSION['form_data']);
            $_SESSION['success'] = 'Welcome back, ' . $userFound['name'] . '!';
            header('Location: index.php?page=dashboard');
            exit;
            
        case 'logout':
            $userName = $_SESSION['user']['name'] ?? 'User';
            session_destroy();
            session_start();
            $_SESSION['success'] = 'Goodbye, ' . $userName . '! You have been logged out';
            header('Location: index.php?page=landing');
            exit;
            
        case 'create_ticket':
            if (!isset($_SESSION['user'])) {
                $_SESSION['error'] = 'Please login to create tickets';
                header('Location: index.php?page=login');
                exit;
            }
            
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $status = $_POST['status'] ?? 'Open';
            
            $errors = [];
            
            if (empty($title)) {
                $errors[] = 'Ticket title is required';
            } elseif (strlen($title) < 3) {
                $errors[] = 'Title must be at least 3 characters';
            }
            
            if (!in_array($status, ['Open', 'Ongoing', 'Resolved'])) {
                $errors[] = 'Invalid status selected';
            }
            
            if (!empty($errors)) {
                $_SESSION['error'] = implode('<br>', $errors);
                header('Location: index.php?page=tickets');
                exit;
            }
            
            $newTicket = [
                'id' => count($_SESSION['tickets']) + 1,
                'user_id' => $_SESSION['user']['id'],
                'title' => $title,
                'description' => $description,
                'status' => $status,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $_SESSION['tickets'][] = $newTicket;
            $_SESSION['success'] = 'Ticket created successfully!';
            header('Location: index.php?page=tickets');
            exit;
            
        case 'delete_ticket':
            if (!isset($_SESSION['user'])) {
                $_SESSION['error'] = 'Please login to delete tickets';
                header('Location: index.php?page=login');
                exit;
            }
            
            $ticketId = (int)$_POST['ticket_id'];
            $deleted = false;
            
            foreach ($_SESSION['tickets'] as $key => $ticket) {
                // Check if ticket has user_id before comparing
                if ($ticket['id'] === $ticketId && 
                    isset($ticket['user_id']) && 
                    $ticket['user_id'] === $_SESSION['user']['id']) {
                    unset($_SESSION['tickets'][$key]);
                    $_SESSION['tickets'] = array_values($_SESSION['tickets']);
                    $deleted = true;
                    break;
                }
            }
            
            if ($deleted) {
                $_SESSION['success'] = 'Ticket deleted successfully!';
            } else {
                $_SESSION['error'] = 'Ticket not found or unauthorized';
            }
            
            header('Location: index.php?page=tickets');
            exit;
            
        case 'update_ticket':
            if (!isset($_SESSION['user'])) {
                $_SESSION['error'] = 'Please login to update tickets';
                header('Location: index.php?page=login');
                exit;
            }
            
            $ticketId = (int)$_POST['ticket_id'];
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $status = $_POST['status'] ?? 'Open';
            
            $errors = [];
            
            if (empty($title)) {
                $errors[] = 'Ticket title is required';
            } elseif (strlen($title) < 3) {
                $errors[] = 'Title must be at least 3 characters';
            }
            
            if (!in_array($status, ['Open', 'Ongoing', 'Resolved'])) {
                $errors[] = 'Invalid status selected';
            }
            
            if (!empty($errors)) {
                $_SESSION['error'] = implode('<br>', $errors);
                header('Location: index.php?page=tickets&edit=' . $ticketId);
                exit;
            }
            
            $updated = false;
            foreach ($_SESSION['tickets'] as &$ticket) {
                if ($ticket['id'] === $ticketId && $ticket['user_id'] === $_SESSION['user']['id']) {
                    $ticket['title'] = $title;
                    $ticket['description'] = $description;
                    $ticket['status'] = $status;
                    $ticket['updated_at'] = date('Y-m-d H:i:s');
                    $updated = true;
                    break;
                }
            }
            
            if ($updated) {
                $_SESSION['success'] = 'Ticket updated successfully!';
            } else {
                $_SESSION['error'] = 'Ticket not found or unauthorized';
            }
            
            header('Location: index.php?page=tickets');
            exit;
    }
}

// Get user's tickets only
$userTickets = [];
if (isset($_SESSION['user'])) {
    foreach ($_SESSION['tickets'] as $ticket) {
        // Check if ticket has user_id and it matches current user
        if (isset($ticket['user_id']) && $ticket['user_id'] === $_SESSION['user']['id']) {
            $userTickets[] = $ticket;
        }
    }
}

// Calculate ticket stats for logged-in user
$stats = [
    'total' => count($userTickets),
    'open' => count(array_filter($userTickets, fn($t) => $t['status'] === 'Open')),
    'ongoing' => count(array_filter($userTickets, fn($t) => $t['status'] === 'Ongoing')),
    'resolved' => count(array_filter($userTickets, fn($t) => $t['status'] === 'Resolved')),
];

// Get ticket for editing (ensure user owns it)
$editTicket = null;
if (isset($_GET['edit']) && isset($_SESSION['user'])) {
    $editId = (int)$_GET['edit'];
    foreach ($userTickets as $ticket) {
        if ($ticket['id'] === $editId) {
            $editTicket = $ticket;
            break;
        }
    }
}

// Get form data from session (for repopulating forms after errors)
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

// Render the appropriate template
try {
    switch ($page) {
        case 'landing':
            echo $twig->render('landing.twig', [
                'success' => $success
            ]);
            break;
        case 'login':
            echo $twig->render('login.twig', [
                'error' => $error,
                'success' => $success,
                'formData' => $formData
            ]);
            break;
        case 'register':
            echo $twig->render('register.twig', [
                'error' => $error,
                'success' => $success,
                'formData' => $formData
            ]);
            break;
        case 'dashboard':
            echo $twig->render('dashboard.twig', [
                'user' => $_SESSION['user'],
                'stats' => $stats,
                'success' => $success
            ]);
            break;
        case 'tickets':
            echo $twig->render('tickets.twig', [
                'user' => $_SESSION['user'],
                'tickets' => $userTickets,
                'editTicket' => $editTicket,
                'error' => $error,
                'success' => $success
            ]);
            break;
        default:
            header('HTTP/1.0 404 Not Found');
            echo '404 - Page Not Found';
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}