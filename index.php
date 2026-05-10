<?php
// =============================================
// ELITE BOOKSTORE - FULLY FUNCTIONAL
// Fixed: Book addition, editing, file uploads
// =============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli("localhost", "root", "", "bookstore");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create uploads directory with proper permissions
if (!is_dir('uploads')) {
    mkdir('uploads', 0777, true);
}

// Fix books table - add missing columns if needed
$conn->query("ALTER TABLE books ADD COLUMN IF NOT EXISTS author VARCHAR(255) DEFAULT 'Unknown' AFTER name");
$conn->query("ALTER TABLE books ADD COLUMN IF NOT EXISTS category VARCHAR(100) DEFAULT 'General' AFTER author");
$conn->query("ALTER TABLE books ADD COLUMN IF NOT EXISTS video VARCHAR(255) AFTER description");
$conn->query("ALTER TABLE books ADD COLUMN IF NOT EXISTS cover_image VARCHAR(255) AFTER video");

// Create all necessary tables
$conn->query("CREATE TABLE IF NOT EXISTS books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    author VARCHAR(255) DEFAULT 'Unknown',
    category VARCHAR(100) DEFAULT 'General',
    price DECIMAL(10,2) NOT NULL,
    qty INT NOT NULL DEFAULT 0,
    description TEXT,
    video VARCHAR(255),
    cover_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    session_id VARCHAR(255) NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(50),
    total_amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    book_id INT NOT NULL,
    book_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Insert default admin if not exists
$admin_check = $conn->query("SELECT * FROM admin_users WHERE username='admin'");
if ($admin_check->num_rows == 0) {
    $hashed = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO admin_users (username, password) VALUES ('admin', '$hashed')");
}

$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$session_id = session_id();

// ========== ADMIN LOGIN HANDLER ==========
if (isset($_POST['admin_login'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
    $result = $conn->query("SELECT * FROM admin_users WHERE username='$username'");
    if ($result && $result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        if (password_verify($password, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            echo "<script>alert('Welcome Admin!'); window.location.href='?admin=1';</script>";
            exit;
        } else {
            $login_error = "Invalid password";
        }
    } else {
        $login_error = "Invalid username";
    }
}

if (isset($_GET['admin_logout'])) {
    session_destroy();
    echo "<script>window.location.href='?';</script>";
    exit;
}

// ========== ADMIN ADD BOOK - FIXED ==========
if (isset($_POST['add_book']) && $is_admin) {
    $name = $conn->real_escape_string($_POST['name']);
    $author = $conn->real_escape_string($_POST['author']);
    $category = $conn->real_escape_string($_POST['category']);
    $price = floatval($_POST['price']);
    $qty = intval($_POST['qty']);
    $desc = $conn->real_escape_string($_POST['desc']);
    
    // Handle video upload
    $video_name = '';
    if (isset($_FILES['video']) && $_FILES['video']['error'] == 0) {
        $allowed_video = ['video/mp4', 'video/webm', 'video/ogg'];
        if (in_array($_FILES['video']['type'], $allowed_video)) {
            $video_name = time() . "_video_" . preg_replace('/[^a-zA-Z0-9.]/', '_', $_FILES['video']['name']);
            move_uploaded_file($_FILES['video']['tmp_name'], "uploads/" . $video_name);
        }
    }
    
    // Handle cover image upload
    $cover_name = '';
    if (isset($_FILES['cover']) && $_FILES['cover']['error'] == 0) {
        $allowed_image = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (in_array($_FILES['cover']['type'], $allowed_image)) {
            $cover_name = time() . "_cover_" . preg_replace('/[^a-zA-Z0-9.]/', '_', $_FILES['cover']['name']);
            move_uploaded_file($_FILES['cover']['tmp_name'], "uploads/" . $cover_name);
        }
    }
    
    $sql = "INSERT INTO books (name, author, category, price, qty, description, video, cover_image) 
            VALUES ('$name', '$author', '$category', $price, $qty, '$desc', '$video_name', '$cover_name')";
    
    if ($conn->query($sql)) {
        echo "<script>alert('✅ Book added successfully!'); window.location.href='?admin=1';</script>";
    } else {
        echo "<script>alert('❌ Error: " . $conn->error . "');</script>";
    }
    exit;
}

// ========== ADMIN EDIT BOOK - FIXED ==========
if (isset($_POST['edit_book']) && $is_admin) {
    $id = intval($_POST['book_id']);
    $name = $conn->real_escape_string($_POST['name']);
    $author = $conn->real_escape_string($_POST['author']);
    $category = $conn->real_escape_string($_POST['category']);
    $price = floatval($_POST['price']);
    $qty = intval($_POST['qty']);
    $desc = $conn->real_escape_string($_POST['desc']);
    
    $update_query = "UPDATE books SET name='$name', author='$author', category='$category', price=$price, qty=$qty, description='$desc'";
    
    // Handle video upload
    if (isset($_FILES['video']) && $_FILES['video']['error'] == 0) {
        $allowed_video = ['video/mp4', 'video/webm', 'video/ogg'];
        if (in_array($_FILES['video']['type'], $allowed_video)) {
            // Delete old video
            $old = $conn->query("SELECT video FROM books WHERE id=$id");
            if ($old && $old->num_rows) {
                $old_data = $old->fetch_assoc();
                if ($old_data['video'] && file_exists("uploads/" . $old_data['video'])) {
                    unlink("uploads/" . $old_data['video']);
                }
            }
            $video_name = time() . "_video_" . preg_replace('/[^a-zA-Z0-9.]/', '_', $_FILES['video']['name']);
            move_uploaded_file($_FILES['video']['tmp_name'], "uploads/" . $video_name);
            $update_query .= ", video='$video_name'";
        }
    }
    
    // Handle cover upload
    if (isset($_FILES['cover']) && $_FILES['cover']['error'] == 0) {
        $allowed_image = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (in_array($_FILES['cover']['type'], $allowed_image)) {
            // Delete old cover
            $old = $conn->query("SELECT cover_image FROM books WHERE id=$id");
            if ($old && $old->num_rows) {
                $old_data = $old->fetch_assoc();
                if ($old_data['cover_image'] && file_exists("uploads/" . $old_data['cover_image'])) {
                    unlink("uploads/" . $old_data['cover_image']);
                }
            }
            $cover_name = time() . "_cover_" . preg_replace('/[^a-zA-Z0-9.]/', '_', $_FILES['cover']['name']);
            move_uploaded_file($_FILES['cover']['tmp_name'], "uploads/" . $cover_name);
            $update_query .= ", cover_image='$cover_name'";
        }
    }
    
    $update_query .= " WHERE id=$id";
    
    if ($conn->query($update_query)) {
        echo "<script>alert('✅ Book updated successfully!'); window.location.href='?admin=1';</script>";
    } else {
        echo "<script>alert('❌ Error: " . $conn->error . "');</script>";
    }
    exit;
}

// ========== ADMIN DELETE BOOK ==========
if (isset($_GET['delete_book']) && $is_admin) {
    $id = intval($_GET['delete_book']);
    // Delete associated files
    $result = $conn->query("SELECT video, cover_image FROM books WHERE id=$id");
    if ($result && $result->num_rows) {
        $row = $result->fetch_assoc();
        if ($row['video'] && file_exists("uploads/" . $row['video'])) unlink("uploads/" . $row['video']);
        if ($row['cover_image'] && file_exists("uploads/" . $row['cover_image'])) unlink("uploads/" . $row['cover_image']);
    }
    $conn->query("DELETE FROM books WHERE id=$id");
    echo "<script>alert('🗑️ Book deleted!'); window.location.href='?admin=1';</script>";
    exit;
}

// ========== CART OPERATIONS ==========
if (isset($_POST['add_to_cart'])) {
    $book_id = intval($_POST['book_id']);
    $quantity = max(1, intval($_POST['quantity']));
    $check = $conn->query("SELECT id FROM cart WHERE book_id=$book_id AND session_id='$session_id'");
    if ($check->num_rows > 0) {
        $conn->query("UPDATE cart SET quantity = quantity + $quantity WHERE book_id=$book_id AND session_id='$session_id'");
    } else {
        $conn->query("INSERT INTO cart (book_id, quantity, session_id) VALUES ($book_id, $quantity, '$session_id')");
    }
    echo "<script>alert('🛒 Added to cart!'); window.location.href='?cart=1';</script>";
    exit;
}

if (isset($_GET['remove_cart'])) {
    $cart_id = intval($_GET['remove_cart']);
    $conn->query("DELETE FROM cart WHERE id=$cart_id AND session_id='$session_id'");
    header("Location: ?cart=1");
    exit;
}

if (isset($_GET['clear_cart'])) {
    $conn->query("DELETE FROM cart WHERE session_id='$session_id'");
    header("Location: ?");
    exit;
}

if (isset($_POST['update_cart'])) {
    $cart_id = intval($_POST['cart_id']);
    $quantity = max(0, intval($_POST['quantity']));
    if ($quantity <= 0) {
        $conn->query("DELETE FROM cart WHERE id=$cart_id AND session_id='$session_id'");
    } else {
        $conn->query("UPDATE cart SET quantity=$quantity WHERE id=$cart_id AND session_id='$session_id'");
    }
    header("Location: ?cart=1");
    exit;
}

// ========== CHECKOUT ==========
if (isset($_POST['checkout'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    
    $cart_items = $conn->query("SELECT c.*, b.name, b.price, b.qty as stock FROM cart c JOIN books b ON c.book_id = b.id WHERE c.session_id='$session_id'");
    
    if ($cart_items->num_rows > 0) {
        $total = 0;
        $items = [];
        $valid = true;
        
        while ($item = $cart_items->fetch_assoc()) {
            if ($item['quantity'] > $item['stock']) {
                $valid = false;
                echo "<script>alert('Not enough stock for {$item['name']}!'); window.location.href='?cart=1';</script>";
                exit;
            }
            $total += $item['price'] * $item['quantity'];
            $items[] = $item;
        }
        
        if ($valid) {
            $conn->query("INSERT INTO orders (customer_name, customer_email, customer_phone, total_amount) VALUES ('$name', '$email', '$phone', $total)");
            $order_id = $conn->insert_id;
            
            foreach ($items as $item) {
                $conn->query("INSERT INTO order_items (order_id, book_id, book_name, quantity, price) VALUES ($order_id, {$item['book_id']}, '{$item['name']}', {$item['quantity']}, {$item['price']})");
                $new_stock = $item['stock'] - $item['quantity'];
                $conn->query("UPDATE books SET qty=$new_stock WHERE id={$item['book_id']}");
            }
            
            $conn->query("DELETE FROM cart WHERE session_id='$session_id'");
            echo "<script>alert('🎉 Order placed! Order #$order_id'); window.location.href='?orders=1';</script>";
            exit;
        }
    }
}

// ========== GET DATA ==========
$current_view = 'shop';
if (isset($_GET['admin'])) $current_view = 'admin';
elseif (isset($_GET['cart'])) $current_view = 'cart';
elseif (isset($_GET['orders'])) $current_view = 'orders';

// Get cart count
$cart_count_query = $conn->query("SELECT SUM(quantity) as total FROM cart WHERE session_id='$session_id'");
$cart_count = ($cart_count_query && $cart_count_query->num_rows) ? $cart_count_query->fetch_assoc()['total'] : 0;

// Get edit book data
$edit_book = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit']) && $is_admin) {
    $edit_result = $conn->query("SELECT * FROM books WHERE id=" . intval($_GET['edit']));
    if ($edit_result && $edit_result->num_rows) $edit_book = $edit_result->fetch_assoc();
}

// Category filter
$selected_category = isset($_GET['category']) ? $_GET['category'] : '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql = "SELECT * FROM books WHERE 1=1";
if ($selected_category && $selected_category != 'all') {
    $sql .= " AND category = '" . $conn->real_escape_string($selected_category) . "'";
}
if ($search_term) {
    $sql .= " AND (name LIKE '%" . $conn->real_escape_string($search_term) . "%' OR author LIKE '%" . $conn->real_escape_string($search_term) . "%')";
}
$sql .= " ORDER BY created_at DESC";
$books = $conn->query($sql);

$categories = ['Business & Finance', 'Accounting & Cost', 'Engineering & Electrical', 'IT & Web Development', 'Spiritual & Psychological'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elite BookStore — Publish, Print & Sell Premium Books</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f4f2; color: #1a2a3a; }
        
        .navbar { background: #0b2b3f; color: white; position: sticky; top: 0; z-index: 1000; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .nav-container { max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; padding: 16px 32px; flex-wrap: wrap; gap: 16px; }
        .logo-area { display: flex; align-items: center; gap: 12px; }
        .logo-icon { background: rgba(255,255,255,0.15); width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; }
        .logo-text h1 { font-size: 1.4rem; font-weight: 700; }
        .logo-text p { font-size: 0.7rem; opacity: 0.8; }
        .nav-links { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .nav-link { color: white; text-decoration: none; padding: 8px 20px; border-radius: 40px; font-weight: 500; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); }
        .cart-badge { background: #ef4444; border-radius: 20px; padding: 2px 8px; font-size: 0.7rem; font-weight: bold; }
        .container { max-width: 1400px; margin: 40px auto; padding: 0 32px; }
        
        .hero { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); border-radius: 32px; padding: 60px 48px; margin-bottom: 48px; text-align: center; color: white; }
        .hero h1 { font-size: 3rem; font-weight: 800; margin-bottom: 16px; }
        .hero p { font-size: 1.2rem; opacity: 0.9; max-width: 600px; margin: 0 auto; }
        
        .filter-bar { background: white; border-radius: 60px; padding: 12px 24px; margin-bottom: 40px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .category-chips { display: flex; flex-wrap: wrap; gap: 12px; }
        .cat-chip { background: #f0f2f5; padding: 8px 20px; border-radius: 40px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: 0.2s; color: #2c3e50; }
        .cat-chip.active, .cat-chip:hover { background: #1e3c72; color: white; }
        .search-box { display: flex; gap: 8px; background: #f0f2f5; padding: 4px 16px; border-radius: 40px; }
        .search-box input { border: none; background: transparent; padding: 8px; width: 200px; outline: none; }
        .search-box button { background: none; border: none; cursor: pointer; color: #1e3c72; }
        
        .section-title { font-size: 1.8rem; font-weight: 700; margin-bottom: 32px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .books-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 32px; }
        .book-card { background: white; border-radius: 24px; overflow: hidden; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .book-card:hover { transform: translateY(-4px); box-shadow: 0 12px 28px rgba(0,0,0,0.1); }
        .book-cover { height: 220px; background: #e8edf2; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .book-cover img { width: 100%; height: 100%; object-fit: cover; }
        .no-cover { font-size: 3rem; color: #94a3b8; }
        .video-preview video { width: 100%; height: 140px; object-fit: cover; }
        .book-info { padding: 20px; }
        .book-title { font-size: 1.2rem; font-weight: 700; margin-bottom: 4px; }
        .book-author { color: #64748b; font-size: 0.8rem; margin-bottom: 8px; }
        .category-badge { display: inline-block; background: #e2e8f0; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; margin-bottom: 12px; }
        .price { font-size: 1.5rem; font-weight: 800; color: #1e3c72; }
        .stock { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; margin: 12px 0; }
        .in-stock { background: #d1fae5; color: #065f46; }
        .low-stock { background: #fed7aa; color: #9a3412; }
        .out-stock { background: #fee2e2; color: #991b1b; }
        .description { color: #475569; font-size: 0.8rem; line-height: 1.4; margin: 10px 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .quantity-form { display: flex; gap: 10px; margin-top: 12px; }
        .quantity-form input { width: 65px; padding: 8px; border: 1px solid #cbd5e1; border-radius: 12px; text-align: center; }
        .btn-add-cart { flex: 1; background: #1e3c72; color: white; border: none; padding: 8px; border-radius: 30px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-add-cart:hover { background: #2a5298; }
        .btn-add-cart:disabled { background: #94a3b8; cursor: not-allowed; }
        
        .form-section { background: white; border-radius: 24px; padding: 32px; margin-bottom: 32px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .form-grid input, .form-grid select, .form-grid textarea { padding: 12px; border: 1px solid #cbd5e1; border-radius: 12px; font-family: inherit; width: 100%; }
        .btn-primary { background: #1e3c72; color: white; border: none; padding: 12px 28px; border-radius: 40px; font-weight: 600; cursor: pointer; margin-top: 20px; }
        .admin-table { background: white; border-radius: 24px; overflow-x: auto; padding: 20px; }
        .admin-table table { width: 100%; border-collapse: collapse; }
        .admin-table th, .admin-table td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        
        .cart-table, .orders-list { background: white; border-radius: 24px; overflow-x: auto; }
        .cart-table table, .orders-list table { width: 100%; border-collapse: collapse; }
        .cart-table th, .cart-table td, .orders-list th, .orders-list td { padding: 16px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .empty-state { text-align: center; padding: 60px; background: white; border-radius: 24px; }
        .login-box { max-width: 400px; margin: 60px auto; background: white; border-radius: 24px; padding: 40px; text-align: center; }
        
        .footer { background: #0b2b3f; color: white; margin-top: 60px; padding: 50px 20px 30px; }
        .footer-content { max-width: 1400px; margin: 0 auto; }
        .footer-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 40px; margin-bottom: 40px; }
        .footer-grid h4 { margin-bottom: 20px; font-size: 1rem; font-weight: 600; }
        .footer-grid p { margin: 10px 0; color: #94a3b8; cursor: pointer; transition: color 0.2s; }
        .footer-grid p:hover { color: white; }
        .newsletter-section { text-align: center; margin-bottom: 40px; padding-bottom: 30px; border-bottom: 1px solid #334155; }
        .newsletter-section h3 { font-size: 1.3rem; margin-bottom: 10px; }
        .newsletter-section p { color: #94a3b8; margin-bottom: 20px; }
        .newsletter-form { display: flex; justify-content: center; gap: 10px; flex-wrap: wrap; }
        .newsletter-form input { padding: 12px 20px; width: 280px; border-radius: 40px; border: none; outline: none; font-family: inherit; }
        .newsletter-form button { padding: 12px 28px; background: #1e3c72; color: white; border: none; border-radius: 40px; cursor: pointer; font-weight: 600; }
        .social-links { display: flex; gap: 20px; margin-top: 15px; }
        .social-links i { font-size: 1.5rem; cursor: pointer; transition: opacity 0.2s; }
        .copyright { text-align: center; padding-top: 30px; border-top: 1px solid #334155; color: #64748b; font-size: 0.85rem; }
        
        @media (max-width: 768px) { .nav-container { flex-direction: column; } .container { padding: 0 16px; } .hero h1 { font-size: 2rem; } .hero { padding: 40px 24px; } .filter-bar { flex-direction: column; align-items: stretch; } }
    </style>
</head>
<body>

<div class="navbar">
    <div class="nav-container">
        <div class="logo-area">
            <div class="logo-icon"><i class="fas fa-book"></i></div>
            <div class="logo-text">
                <h1>Elite BookStore</h1>
                <p>by Gerar Isaac — Premium Reading Experience</p>
            </div>
        </div>
        <div class="nav-links">
            <a href="?" class="nav-link <?= $current_view == 'shop' ? 'active' : '' ?>"><i class="fas fa-store"></i> Shop</a>
            <a href="?cart=1" class="nav-link <?= $current_view == 'cart' ? 'active' : '' ?>">
                <i class="fas fa-shopping-cart"></i> Cart
                <?php if ($cart_count > 0): ?>
                    <span class="cart-badge"><?= $cart_count ?></span>
                <?php endif; ?>
            </a>
            <a href="?orders=1" class="nav-link <?= $current_view == 'orders' ? 'active' : '' ?>"><i class="fas fa-truck"></i> Orders</a>
            <?php if ($is_admin): ?>
                <a href="?admin=1" class="nav-link <?= $current_view == 'admin' ? 'active' : '' ?>"><i class="fas fa-user-shield"></i> Admin</a>
                <a href="?admin_logout=1" class="nav-link" onclick="return confirm('Logout?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
            <?php else: ?>
                <a href="?admin=1" class="nav-link"><i class="fas fa-lock"></i> Admin</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="container">
<?php if ($current_view == 'admin'): ?>
    <?php if (!$is_admin): ?>
        <div class="login-box">
            <i class="fas fa-crown" style="font-size: 3rem; color: #1e3c72;"></i>
            <h2 style="margin: 16px 0;">Admin Login</h2>
            <?php if (isset($login_error)): ?>
                <div style="background: #fee2e2; padding: 10px; border-radius: 12px; margin-bottom: 20px; color: #dc2626;"><?= $login_error ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="text" name="username" placeholder="Username" required style="width: 100%; padding: 12px; margin-bottom: 16px; border-radius: 40px; border: 1px solid #cbd5e1;">
                <input type="password" name="password" placeholder="Password" required style="width: 100%; padding: 12px; margin-bottom: 20px; border-radius: 40px; border: 1px solid #cbd5e1;">
                <button type="submit" name="admin_login" class="btn-primary" style="width: 100%;"><i class="fas fa-sign-in-alt"></i> Login</button>
            </form>
        </div>
    <?php else: ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
            <h2><i class="fas fa-user-shield"></i> Admin Dashboard</h2>
            <span>Welcome, <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
        </div>
        
        <div class="form-section">
            <h3><?= $edit_book ? '✏️ Edit Book' : '➕ Add New Book' ?></h3>
            <form method="POST" enctype="multipart/form-data">
                <?php if ($edit_book): ?>
                    <input type="hidden" name="book_id" value="<?= $edit_book['id'] ?>">
                <?php endif; ?>
                <div class="form-grid">
                    <input type="text" name="name" placeholder="Book Title" value="<?= htmlspecialchars($edit_book['name'] ?? '') ?>" required>
                    <input type="text" name="author" placeholder="Author Name" value="<?= htmlspecialchars($edit_book['author'] ?? '') ?>" required>
                    <select name="category" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat ?>" <?= ($edit_book && $edit_book['category'] == $cat) ? 'selected' : '' ?>><?= $cat ?></option>
                        <?php endforeach; ?>
                        <option value="General" <?= ($edit_book && $edit_book['category'] == 'General') ? 'selected' : '' ?>>General</option>
                    </select>
                    <input type="number" step="0.01" name="price" placeholder="Price (USD)" value="<?= $edit_book['price'] ?? '' ?>" required>
                    <input type="number" name="qty" placeholder="Stock Quantity" value="<?= $edit_book['qty'] ?? '' ?>" required>
                    <textarea name="desc" placeholder="Book Description" rows="3"><?= htmlspecialchars($edit_book['description'] ?? '') ?></textarea>
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">📸 Cover Image</label>
                        <input type="file" name="cover" accept="image/jpeg,image/png,image/gif,image/webp">
                        <?php if (!empty($edit_book['cover_image']) && file_exists("uploads/" . $edit_book['cover_image'])): ?>
                            <img src="uploads/<?= $edit_book['cover_image'] ?>" width="60" style="margin-top: 8px; border-radius: 8px;">
                        <?php endif; ?>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">🎬 Video Preview</label>
                        <input type="file" name="video" accept="video/mp4,video/webm,video/ogg">
                        <?php if (!empty($edit_book['video']) && file_exists("uploads/" . $edit_book['video'])): ?>
                            <video width="100" controls style="margin-top: 8px; border-radius: 8px;">
                                <source src="uploads/<?= $edit_book['video'] ?>">
                            </video>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="submit" name="<?= $edit_book ? 'edit_book' : 'add_book' ?>" class="btn-primary">
                    <i class="fas fa-save"></i> <?= $edit_book ? 'Update Book' : 'Add Book' ?>
                </button>
                <?php if ($edit_book): ?>
                    <a href="?admin=1" style="margin-left: 15px; color: #64748b;">Cancel Edit</a>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="admin-table">
            <h3 style="margin-bottom: 20px;">📚 All Books</h3>
            <table>
                <thead>
                    <tr><th>ID</th><th>Cover</th><th>Title</th><th>Author</th><th>Category</th><th>Price</th><th>Stock</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php $all_books = $conn->query("SELECT * FROM books ORDER BY id DESC"); 
                    while ($b = $all_books->fetch_assoc()): ?>
                    <tr>
                        <td><?= $b['id'] ?></td>
                        <td><?php if ($b['cover_image'] && file_exists("uploads/" . $b['cover_image'])) echo "<img src='uploads/{$b['cover_image']}' width='40' style='border-radius: 6px;'>"; else echo "📖"; ?></td>
                        <td><?= htmlspecialchars($b['name']) ?></td>
                        <td><?= htmlspecialchars($b['author']) ?></td>
                        <td><?= htmlspecialchars($b['category']) ?></td>
                        <td>$<?= number_format($b['price'], 2) ?></td>
                        <td><?= $b['qty'] ?></td>
                        <td>
                            <a href="?admin=1&edit=<?= $b['id'] ?>" style="background: #e2e8f0; padding: 4px 12px; border-radius: 20px; text-decoration: none; margin-right: 8px;">Edit</a>
                            <a href="?delete_book=<?= $b['id'] ?>" onclick="return confirm('Delete this book?')" style="background: #fee2e2; padding: 4px 12px; border-radius: 20px; text-decoration: none; color: #dc2626;">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php elseif ($current_view == 'cart'): ?>
    <div class="section-title">
        <span><i class="fas fa-shopping-cart"></i> Your Cart</span>
        <a href="?"><button class="btn-primary" style="background: #64748b;"><i class="fas fa-arrow-left"></i> Continue Shopping</button></a>
    </div>
    
    <?php
    $cart_items = $conn->query("SELECT c.*, b.name, b.price, b.cover_image, b.qty as stock FROM cart c JOIN books b ON c.book_id = b.id WHERE c.session_id='$session_id'");
    if ($cart_items->num_rows == 0):
    ?>
        <div class="empty-state">
            <i class="fas fa-shopping-bag" style="font-size: 3rem; opacity: 0.5;"></i>
            <h3>Your cart is empty</h3>
            <p>Add some amazing books to get started!</p>
            <a href="?"><button class="btn-primary" style="margin-top: 20px;">Browse Books</button></a>
        </div>
    <?php else: 
        $cart_total = 0;
    ?>
        <div class="cart-table">
            <table>
                <thead><tr><th>Book</th><th>Cover</th><th>Price</th><th>Quantity</th><th>Total</th><th></th></tr></thead>
                <tbody>
                    <?php while ($item = $cart_items->fetch_assoc()): 
                        $subtotal = $item['price'] * $item['quantity'];
                        $cart_total += $subtotal;
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                        <td><?php if ($item['cover_image'] && file_exists("uploads/" . $item['cover_image'])) echo "<img src='uploads/{$item['cover_image']}' width='50' style='border-radius: 8px;'>"; else echo "📖"; ?></td>
                        <td>$<?= number_format($item['price'], 2) ?></td>
                        <td>
                            <form method="POST" style="display: flex; gap: 8px;">
                                <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                <input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="0" style="width: 70px; padding: 6px; border-radius: 12px; border: 1px solid #cbd5e1;">
                                <button type="submit" name="update_cart" style="background: #e2e8f0; border: none; padding: 6px 12px; border-radius: 20px; cursor: pointer;">Update</button>
                            </form>
                        </td>
                        <td>$<?= number_format($subtotal, 2) ?></td>
                        <td><a href="?remove_cart=<?= $item['id'] ?>" onclick="return confirm('Remove?')" style="color: #dc2626;"><i class="fas fa-trash"></i></a></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <div style="background: white; border-radius: 24px; padding: 24px; margin-top: 24px; text-align: right;">
            <h3>Total: $<?= number_format($cart_total, 2) ?></h3>
            <form method="POST" style="max-width: 400px; margin-left: auto; margin-top: 20px;">
                <input type="text" name="name" placeholder="Full Name" required style="width: 100%; padding: 12px; margin-bottom: 12px; border-radius: 40px; border: 1px solid #cbd5e1;">
                <input type="email" name="email" placeholder="Email Address" required style="width: 100%; padding: 12px; margin-bottom: 12px; border-radius: 40px; border: 1px solid #cbd5e1;">
                <input type="tel" name="phone" placeholder="Phone Number" style="width: 100%; padding: 12px; margin-bottom: 20px; border-radius: 40px; border: 1px solid #cbd5e1;">
                <button type="submit" name="checkout" class="btn-primary" style="width: 100%;"><i class="fas fa-check-circle"></i> Place Order</button>
                <a href="?clear_cart=1" style="display: block; margin-top: 12px; color: #dc2626;">Clear Cart</a>
            </form>
        </div>
    <?php endif; ?>

<?php elseif ($current_view == 'orders'): ?>
    <div class="section-title">
        <span><i class="fas fa-truck"></i> Your Orders</span>
        <a href="?"><button class="btn-primary"><i class="fas fa-store"></i> Continue Shopping</button></a>
    </div>
    
    <?php
    $orders = $conn->query("SELECT * FROM orders ORDER BY order_date DESC");
    if ($orders->num_rows == 0):
    ?>
        <div class="empty-state">
            <i class="fas fa-receipt" style="font-size: 3rem; opacity: 0.5;"></i>
            <h3>No orders yet</h3>
            <p>Complete your first purchase to see it here!</p>
            <a href="?"><button class="btn-primary" style="margin-top: 20px;">Start Shopping</button></a>
        </div>
    <?php else: ?>
        <div class="orders-list">
            <?php while ($order = $orders->fetch_assoc()): ?>
                <div style="background: white; border-radius: 24px; padding: 24px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; flex-wrap: wrap; margin-bottom: 16px;">
                        <h3><i class="fas fa-receipt"></i> Order #<?= $order['id'] ?></h3>
                        <span><strong>Date:</strong> <?= date('F j, Y', strtotime($order['order_date'])) ?></span>
                        <span><strong>Status:</strong> <span style="color: #10b981;"><?= ucfirst($order['status']) ?></span></span>
                        <span><strong>Total:</strong> $<?= number_format($order['total_amount'], 2) ?></span>
                    </div>
                    <div style="background: #f8fafc; padding: 16px; border-radius: 16px; margin-bottom: 16px;">
                        <p><strong>Customer:</strong> <?= htmlspecialchars($order['customer_name']) ?> | <?= htmlspecialchars($order['customer_email']) ?></p>
                    </div>
                    <?php
                    $order_items = $conn->query("SELECT * FROM order_items WHERE order_id={$order['id']}");
                    if ($order_items->num_rows > 0):
                    ?>
                        <table style="width: 100%;">
                            <thead><tr style="background: #f1f5f9;"><th>Book</th><th>Quantity</th><th>Price</th><th>Total</th></tr></thead>
                            <tbody>
                                <?php while ($item = $order_items->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['book_name']) ?></td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td>$<?= number_format($item['price'], 2) ?></td>
                                    <td>$<?= number_format($item['quantity'] * $item['price'], 2) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <!-- SHOP PAGE -->
    <div class="hero">
        <h1>Publish. Print. Sell.</h1>
        <p>Premium books for every reader — Business, Engineering, IT, and Spiritual wisdom.</p>
    </div>
    
    <div class="filter-bar">
        <div class="category-chips">
            <span class="cat-chip <?= $selected_category == '' || $selected_category == 'all' ? 'active' : '' ?>" data-cat="all"><i class="fas fa-layer-group"></i> All Books</span>
            <?php foreach ($categories as $cat): ?>
                <span class="cat-chip <?= $selected_category == $cat ? 'active' : '' ?>" data-cat="<?= urlencode($cat) ?>"><i class="fas fa-tag"></i> <?= $cat ?></span>
            <?php endforeach; ?>
        </div>
        <form method="GET" class="search-box">
            <input type="text" name="search" placeholder="Search books..." value="<?= htmlspecialchars($search_term) ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
            <?php if ($selected_category): ?>
                <input type="hidden" name="category" value="<?= htmlspecialchars($selected_category) ?>">
            <?php endif; ?>
        </form>
    </div>
    
    <div class="section-title">
        <span><i class="fas fa-book-open"></i> Our Collection</span>
        <span><?= $books->num_rows ?> premium titles available</span>
    </div>
    
    <div class="books-grid">
        <?php if ($books->num_rows == 0): ?>
            <div class="empty-state" style="grid-column: 1/-1;">
                <i class="fas fa-search" style="font-size: 3rem; opacity: 0.5;"></i>
                <h3>No books found</h3>
                <p>Try a different category or search term</p>
                <a href="?"><button class="btn-primary" style="margin-top: 20px;">Clear Filters</button></a>
            </div>
        <?php else: while ($book = $books->fetch_assoc()): 
            $stock_class = $book['qty'] > 5 ? "in-stock" : ($book['qty'] > 0 ? "low-stock" : "out-stock");
            $stock_text = $book['qty'] > 5 ? "In Stock" : ($book['qty'] > 0 ? "Only {$book['qty']} left" : "Out of Stock");
        ?>
        <div class="book-card">
            <div class="book-cover">
                <?php if ($book['cover_image'] && file_exists("uploads/" . $book['cover_image'])): ?>
                    <img src="uploads/<?= $book['cover_image'] ?>" alt="<?= htmlspecialchars($book['name']) ?>">
                <?php else: ?>
                    <div class="no-cover"><i class="fas fa-book"></i></div>
                <?php endif; ?>
            </div>
            
            <?php if ($book['video'] && file_exists("uploads/" . $book['video'])): ?>
            <div class="video-preview">
                <video controls preload="metadata">
                    <source src="uploads/<?= $book['video'] ?>" type="video/mp4">
                </video>
            </div>
            <?php endif; ?>
            
            <div class="book-info">
                <div class="book-title"><?= htmlspecialchars($book['name']) ?></div>
                <div class="book-author"><i class="fas fa-user"></i> <?= htmlspecialchars($book['author']) ?></div>
                <div class="category-badge"><?= htmlspecialchars($book['category']) ?></div>
                <div class="price">$<?= number_format($book['price'], 2) ?></div>
                <div class="stock <?= $stock_class ?>"><?= $stock_text ?></div>
                <div class="description"><?= htmlspecialchars(substr($book['description'], 0, 120)) ?>...</div>
                
                <?php if ($book['qty'] > 0): ?>
                <form method="POST" class="quantity-form">
                    <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                    <input type="number" name="quantity" value="1" min="1" max="<?= $book['qty'] ?>">
                    <button type="submit" name="add_to_cart" class="btn-add-cart"><i class="fas fa-cart-plus"></i> Add to Cart</button>
                </form>
                <?php else: ?>
                    <button class="btn-add-cart" disabled><i class="fas fa-ban"></i> Out of Stock</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; endif; ?>
    </div>
<?php endif; ?>
</div>

<!-- FOOTER -->
<div class="footer">
    <div class="footer-content">
        <div class="newsletter-section">
            <h3><i class="fas fa-envelope"></i> Get Publishing & Marketing Tips</h3>
            <p>Get exclusive Publishing & Marketing tips to help you create and sell your books effectively. You can unsubscribe anytime.</p>
            <form class="newsletter-form" onsubmit="alert('Thank you for subscribing!'); return false;">
                <input type="email" placeholder="Your email address" required>
                <button type="submit">Subscribe</button>
            </form>
        </div>
        
        <div class="footer-grid">
            <div>
                <h4>Company</h4>
                <p>About Us</p>
                <p>Our Team</p>
                <p>News</p>
                <p>Community</p>
                <p>Developers</p>
            </div>
            <div>
                <h4>Products</h4>
                <p>Bookstore</p>
                <p>Print API</p>
                <p>Enterprise</p>
                <p>Pricing</p>
                <p>Create</p>
            </div>
            <div>
                <h4>Resources</h4>
                <p>Knowledge Base</p>
                <p>Publishing Guides</p>
                <p>Video Tutorials</p>
                <p>Podcast</p>
                <p>Blog</p>
            </div>
            <div>
                <h4>Legal</h4>
                <p>Privacy Policy</p>
                <p>Terms & Conditions</p>
                <p>Security</p>
                <p>Cookie Policy</p>
            </div>
            <div>
                <h4>Connect</h4>
                <div class="social-links">
                    <i class="fab fa-facebook"></i>
                    <i class="fab fa-x-twitter"></i>
                    <i class="fab fa-instagram"></i>
                    <i class="fab fa-linkedin"></i>
                    <i class="fab fa-youtube"></i>
                    <i class="fab fa-tiktok"></i>
                </div>
                <p style="margin-top: 15px;"><i class="fas fa-globe"></i> Language: English</p>
            </div>
        </div>
        
        <div class="copyright">
            <p><i class="fas fa-copyright"></i> <?= date("Y") ?> Elite BookStore — A Gerar Isaac Company</p>
            <p>Premium Reading Experience with Video Previews • Secure Checkout • Global Shipping • 24/7 Support</p>
            <p style="margin-top: 10px; font-size: 0.75rem;">Certified B Corporation™ • SOC 2 Certified</p>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('.cat-chip').forEach(chip => {
        chip.addEventListener('click', function() {
            let cat = this.getAttribute('data-cat');
            let url = new URL(window.location.href);
            if (cat === 'all') {
                url.searchParams.delete('category');
            } else {
                url.searchParams.set('category', cat);
            }
            url.searchParams.delete('search');
            window.location.href = url.toString();
        });
    });
</script>

</body>
</html>