<?php
session_start();
$conn = new mysqli("localhost", "root", "", "bookstore");

if ($conn->connect_error) die("DB Error");

// ===== AUTH =====
if (!isset($_SESSION['admin_logged_in'])) {
    die("Access denied");
}

// ===== UPLOAD PATH =====
$upload_dir = __DIR__ . "/uploads/";
$web_path = "uploads/";

if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

// ===== ADD BOOK =====
if (isset($_POST['add_book'])) {

    $name = $_POST['name'];
    $author = $_POST['author'];
    $price = $_POST['price'];
    $qty = $_POST['qty'];
    $desc = $_POST['desc'];

    $youtube = $_POST['youtube'];
    $linkedin = $_POST['linkedin'];
    $facebook = $_POST['facebook'];

    // IMAGE
    $cover = "";
    if (!empty($_FILES['cover']['name'])) {
        $cover = time() . "_cover_" . basename($_FILES['cover']['name']);
        move_uploaded_file($_FILES['cover']['tmp_name'], $upload_dir . $cover);
    }

    // VIDEO
    $video = "";
    if (!empty($_FILES['video']['name'])) {
        $video = time() . "_video_" . basename($_FILES['video']['name']);
        move_uploaded_file($_FILES['video']['tmp_name'], $upload_dir . $video);
    }

    $sql = "INSERT INTO books 
    (name, author, price, qty, description, cover_image, video, youtube_link, linkedin_link, facebook_link)
    VALUES 
    ('$name','$author','$price','$qty','$desc','$cover','$video','$youtube','$linkedin','$facebook')";

    $conn->query($sql);
    header("Location: inventory.php");
    exit();
}

// ===== FETCH =====
$books = $conn->query("SELECT * FROM books ORDER BY id DESC");
?>

<!DOCTYPE html>
<html>
<head>
<title>📚 Smart Book Inventory</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
body{
    font-family: 'Segoe UI';
    background:#f4f7fb;
}

.container{
    width:95%;
    margin:auto;
}

.card{
    background:#fff;
    padding:20px;
    margin:20px 0;
    border-radius:15px;
    box-shadow:0 8px 25px rgba(0,0,0,0.05);
}

input, textarea{
    width:100%;
    padding:10px;
    margin:5px 0 15px;
    border-radius:10px;
    border:1px solid #ddd;
}

button{
    background:#2a5298;
    color:#fff;
    padding:10px 20px;
    border:none;
    border-radius:25px;
    cursor:pointer;
}

.book-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(280px,1fr));
    gap:20px;
}

.book{
    background:white;
    border-radius:15px;
    overflow:hidden;
    box-shadow:0 5px 20px rgba(0,0,0,0.08);
    transition:.3s;
}

.book:hover{
    transform:translateY(-5px);
}

.book img{
    width:100%;
    height:200px;
    object-fit:cover;
}

.video{
    width:100%;
    height:180px;
}

.social a{
    margin-right:10px;
    font-size:18px;
    color:#555;
}

.social a:hover{
    color:#2a5298;
}
</style>
</head>

<body>

<div class="container">

<div class="card">
<h2>➕ Add Book</h2>

<form method="POST" enctype="multipart/form-data">

<input name="name" placeholder="Book name" required>
<input name="author" placeholder="Author" required>
<input name="price" placeholder="Price" required>
<input name="qty" placeholder="Stock" required>

<textarea name="desc" placeholder="Description"></textarea>

<input type="file" name="cover">
<input type="file" name="video">

<input name="youtube" placeholder="YouTube link">
<input name="linkedin" placeholder="LinkedIn link">
<input name="facebook" placeholder="Facebook link">

<button name="add_book">Add Book</button>

</form>
</div>

<div class="card">
<h2>📚 Books</h2>

<div class="book-grid">

<?php while($b = $books->fetch_assoc()): ?>

<div class="book">

<img src="<?= !empty($b['cover_image']) ? $web_path.$b['cover_image'] : 'uploads/download.png' ?>">

<div style="padding:15px">

<h3><?= $b['name'] ?></h3>
<p><?= $b['author'] ?></p>
<p><strong>$<?= $b['price'] ?></strong></p>

<?php if(!empty($b['video'])): ?>
<video class="video" controls>
<source src="<?= $web_path.$b['video'] ?>">
</video>
<?php endif; ?>

<?php if(!empty($b['youtube_link'])): ?>
<iframe width="100%" height="180"
src="<?= str_replace('watch?v=','embed/',$b['youtube_link']) ?>">
</iframe>
<?php endif; ?>

<div class="social">
<?php if($b['linkedin_link']): ?>
<a href="<?= $b['linkedin_link'] ?>" target="_blank"><i class="fab fa-linkedin"></i></a>
<?php endif; ?>

<?php if($b['facebook_link']): ?>
<a href="<?= $b['facebook_link'] ?>" target="_blank"><i class="fab fa-facebook"></i></a>
<?php endif; ?>
</div>

</div>
</div>

<?php endwhile; ?>

</div>
</div>

</div>

</body>
</html>