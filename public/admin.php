<?php
/**
 * WrightCommerce - Simple Admin Panel
 * For testing purposes only - NOT for production!
 * 
 * Place in: C:\xampp\htdocs\wrightcommerce\public\admin.php
 * Access via: http://localhost/wrightcommerce/public/admin.php
 */

session_start();
require_once '../config/database.php';
require_once '../app/helpers/Database.php';

$db = Database::getInstance();

// Check if logged in
$isLoggedIn = isset($_SESSION['user_id']);
$businessId = $_SESSION['business_id'] ?? null;

// Get page parameter
$page = $_GET['page'] ?? 'dashboard';
$category = $_GET['category'] ?? null; 
$action = $_GET['action'] ?? null;
$productId = $_GET['id'] ?? null;

// Handle logout
if ($action === 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WrightCommerce Admin Panel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; }
        
        /* Sidebar */
        .sidebar { position: fixed; left: 0; top: 0; bottom: 0; width: 250px; background: #2c3e50; color: white; padding: 20px 0; }
        .sidebar h2 { padding: 0 20px; margin-bottom: 30px; font-size: 20px; }
        .sidebar a { display: block; padding: 15px 20px; color: white; text-decoration: none; transition: 0.3s; }
        .sidebar a:hover, .sidebar a.active { background: #34495e; }
        .sidebar .user-info { padding: 15px 20px; border-top: 1px solid #34495e; margin-top: 20px; font-size: 13px; }
        
        /* Main Content */
        .main { margin-left: 250px; padding: 30px; }
        .header { background: white; padding: 20px 30px; margin: -30px -30px 30px -30px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .header h1 { font-size: 24px; color: #2c3e50; }
        
        /* Cards */
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .stat-card h3 { font-size: 32px; margin-bottom: 5px; }
        .stat-card p { color: #666; font-size: 14px; }
        .stat-card.blue { border-left: 4px solid #3498db; }
        .stat-card.green { border-left: 4px solid #2ecc71; }
        .stat-card.orange { border-left: 4px solid #e67e22; }
        .stat-card.red { border-left: 4px solid #e74c3c; }
        
        /* Tables */
        .content-box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ecf0f1; }
        th { background: #f8f9fa; font-weight: 600; color: #2c3e50; }
        tr:hover { background: #f8f9fa; }
        
        /* Buttons */
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 14px; transition: 0.3s; }
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; }
        .btn-success { background: #2ecc71; color: white; }
        .btn-success:hover { background: #27ae60; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-danger:hover { background: #c0392b; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        
        /* Badges */
        .badge { padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        
        /* Login Form */
        .login-box { max-width: 400px; margin: 100px auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .login-box h2 { margin-bottom: 30px; text-align: center; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; color: #666; font-size: 14px; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        .form-group input:focus { outline: none; border-color: #3498db; }
        
        /* Utility */
        .text-right { text-align: right; }
        .mb-20 { margin-bottom: 20px; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .alert-error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
    </style>
</head>
<body>

<?php if (!$isLoggedIn): ?>
    <!-- Login Page -->
    <div class="login-box">
        <h2>🔐 Admin Login</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
        </form>
        <p style="text-align: center; margin-top: 20px; font-size: 13px;">
            Don't have an account? Use the <a href="/wrightcommerce/public/">API</a> to register
        </p>
    </div>
    
    <?php
    // Handle login
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        $user = $db->find('users', ['email' => $email]);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['business_id'] = $user['business_id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            header('Location: admin.php');
            exit;
        } else {
            echo "<script>alert('Invalid credentials!');</script>";
        }
    }
    ?>

<?php else: ?>
    <!-- Main Admin Panel -->
    <div class="sidebar">
        <h2>📦 WrightCommerce</h2>
        <a href="?page=dashboard" class="<?= $page === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a>
        <a href="?page=products" class="<?= $page === 'products' ? 'active' : '' ?>">📦 All Products</a>
        <a href="?page=low-stock" class="<?= $page === 'low-stock' ? 'active' : '' ?>">⚠️ Low Stock</a>
        <a href="?page=categories" class="<?= $page === 'categories' ? 'active' : '' ?>">🏷️ Categories</a>
        <a href="?page=add-product" class="<?= $page === 'add-product' ? 'active' : '' ?>">➕ Add Product</a>
        
        <div class="user-info">
            <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong><br>
            <small><?= htmlspecialchars($_SESSION['user_email']) ?></small><br>
            <a href="?action=logout" style="color: #e74c3c; margin-top: 10px; display: inline-block;">Logout</a>
        </div>
    </div>
    
    <div class="main">
        <div class="header">
            <h1><?= ucfirst(str_replace('-', ' ', $page)).($category ? " - ".htmlspecialchars($category) : ""); ?></h1>
        </div>
        
        <?php
        // Get business stats
        $stats = [
            'total' => $db->count('products', ['business_id' => $businessId, 'status' => 'active']),
            'out_of_stock' => $db->count('products', ['business_id' => $businessId, 'stock' => 0])
        ];
        
        $lowStockResult = $db->query("SELECT COUNT(*) as count FROM products WHERE business_id = ? AND stock > 0 AND stock < 10", [$businessId]);
        $stats['low_stock'] = $lowStockResult[0]['count'];
        
        $valueResult = $db->query("SELECT SUM(stock * price) as total FROM products WHERE business_id = ? AND status = 'active'", [$businessId]);
        $stats['value'] = $valueResult[0]['total'] ?? 0;
        
        // Include all admin pages
        include 'admin-pages.php';
        ?>
    </div>

<?php endif; ?>

</body>
</html>