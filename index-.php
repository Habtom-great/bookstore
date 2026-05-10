<?php
session_start();

/* ================= DB ================= */
$conn = new mysqli("localhost","root","","bookstore");
if($conn->connect_error) die("DB Error");

$session_id = session_id();

/* ================= ADMIN ================= */
if(!isset($_SESSION['admin'])) $_SESSION['admin']=false;

/* ================= LOGIN ================= */
if(isset($_POST['login'])){
    if($_POST['user']=="admin" && $_POST['pass']=="admin"){
        $_SESSION['admin']=true;
    } else {
        $error="Wrong login";
    }
}

/* ================= LOGOUT ================= */
if(isset($_GET['logout'])){
    session_destroy();
    header("Location: ?");
}

/* ================= ADD BOOK ================= */
if(isset($_POST['add_book'])){
    $conn->query("INSERT INTO books(name,author,category,price,qty,description)
    VALUES(
        '{$_POST['name']}',
        '{$_POST['author']}',
        '{$_POST['category']}',
        '{$_POST['price']}',
        '{$_POST['qty']}',
        '{$_POST['desc']}'
    )");
}

/* ================= DELETE ================= */
if(isset($_GET['del'])){
    $conn->query("DELETE FROM books WHERE id={$_GET['del']}");
}

/* ================= ADD CART ================= */
if(isset($_POST['add_cart'])){
    $conn->query("INSERT INTO cart(session_id,book_id,quantity)
    VALUES('$session_id',{$_POST['book_id']},{$_POST['qty']})");
}

/* ================= UPDATE CART ================= */
if(isset($_POST['update_cart'])){
    $conn->query("UPDATE cart SET quantity={$_POST['qty']} WHERE id={$_POST['id']}");
}

/* ================= REMOVE CART ================= */
if(isset($_GET['remove'])){
    $conn->query("DELETE FROM cart WHERE id={$_GET['remove']}");
}

/* ================= CHECKOUT ================= */
if(isset($_POST['checkout'])){
    $cart=$conn->query("SELECT c.*,b.name,b.price FROM cart c
    JOIN books b ON c.book_id=b.id WHERE c.session_id='$session_id'");

    $total=0;
    $items=[];

    while($r=$cart->fetch_assoc()){
        $total += $r['price']*$r['quantity'];
        $items[]=$r;
    }

    $conn->query("INSERT INTO orders(session_id,customer_name,customer_email,total_amount)
    VALUES('$session_id','{$_POST['name']}','{$_POST['email']}',$total)");

    $order_id=$conn->insert_id;

    foreach($items as $i){
        $conn->query("INSERT INTO order_items(order_id,book_name,quantity,price)
        VALUES($order_id,'{$i['name']}',{$i['quantity']},{$i['price']})");
    }

    $conn->query("DELETE FROM cart WHERE session_id='$session_id'");
}

/* ================= DATA ================= */
$books=$conn->query("SELECT * FROM books");
$cart=$conn->query("SELECT c.*,b.name,b.price FROM cart c
JOIN books b ON c.book_id=b.id WHERE c.session_id='$session_id'");

$cart_count=$conn->query("SELECT SUM(quantity) t FROM cart WHERE session_id='$session_id'")
->fetch_assoc()['t'] ?? 0;
?>

<!DOCTYPE html>
<html>
<head>
<title>Elite BookStore</title>
<style>
body{font-family:Arial;background:#f5f5f5;margin:0}
.nav{background:#0b2b3f;color:#fff;padding:15px;display:flex;justify-content:space-between}
.container{padding:20px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:15px}
.card{background:#fff;padding:15px;border-radius:10px}
button{background:#1e3c72;color:#fff;border:none;padding:6px}
input,select{padding:5px;margin:5px}
</style>
</head>

<body>

<div class="nav">
<div><b>Elite BookStore</b></div>
<div>
<a href="?">Shop</a> |
<a href="?cart=1">Cart (<?= $cart_count ?>)</a> |
<a href="?orders=1">Orders</a> |
<a href="?admin=1">Admin</a>
</div>
</div>

<div class="container">

<!-- ================= ADMIN ================= -->
<?php if(isset($_GET['admin'])): ?>

<?php if(!$_SESSION['admin']): ?>
<form method="POST">
<h2>Login</h2>
<?php if(isset($error)) echo $error; ?>
<input name="user"><br>
<input type="password" name="pass"><br>
<button name="login">Login</button>
</form>

<?php else: ?>

<h2>Admin Panel</h2>

<form method="POST">
<h3>Add Book</h3>
<input name="name" placeholder="Name">
<input name="author" placeholder="Author">
<input name="category" placeholder="Category">
<input name="price" placeholder="Price">
<input name="qty" placeholder="Qty">
<input name="desc" placeholder="Desc">
<button name="add_book">Add</button>
</form>

<hr>

<?php while($b=$books->fetch_assoc()): ?>
<div class="card">
<h3><?= $b['name'] ?></h3>
<p><?= $b['author'] ?></p>
<p>$<?= $b['price'] ?></p>
<a href="?del=<?= $b['id'] ?>">Delete</a>
</div>
<?php endwhile; ?>

<?php endif; ?>

<!-- ================= CART ================= -->
<?php elseif(isset($_GET['cart'])): ?>

<h2>Cart</h2>

<?php $total=0; while($c=$cart->fetch_assoc()): 
$sub=$c['price']*$c['quantity']; $total+=$sub; ?>
<div class="card">
<h3><?= $c['name'] ?></h3>
<p>$<?= $c['price'] ?></p>

<form method="POST">
<input type="hidden" name="id" value="<?= $c['id'] ?>">
<input name="qty" value="<?= $c['quantity'] ?>">
<button name="update_cart">Update</button>
</form>

<a href="?remove=<?= $c['id'] ?>">Remove</a>
</div>
<?php endwhile; ?>

<h3>Total: $<?= $total ?></h3>

<form method="POST">
<input name="name" placeholder="Name">
<input name="email" placeholder="Email">
<button name="checkout">Checkout</button>
</form>

<!-- ================= SHOP ================= -->
<?php elseif(isset($_GET['orders'])): ?>

<h2>Orders</h2>

<?php
$o=$conn->query("SELECT * FROM orders ORDER BY id DESC");
while($r=$o->fetch_assoc()):
?>
<div class="card">
<h3>Order #<?= $r['id'] ?></h3>
<p><?= $r['customer_name'] ?></p>
<p>$<?= $r['total_amount'] ?></p>
</div>
<?php endwhile; ?>

<!-- ================= SHOP ================= -->
<?php else: ?>

<h2>Books</h2>

<div class="grid">
<?php while($b=$books->fetch_assoc()): ?>
<div class="card">
<h3><?= $b['name'] ?></h3>
<p><?= $b['author'] ?></p>
<p>$<?= $b['price'] ?></p>

<form method="POST">
<input type="hidden" name="book_id" value="<?= $b['id'] ?>">
<input name="qty" value="1">
<button name="add_cart">Add</button>
</form>

</div>
<?php endwhile; ?>
</div>

<?php endif; ?>

</div>
</body>
</html>