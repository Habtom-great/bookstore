<?php while($book = mysqli_fetch_assoc($books)): ?>

<tr>
    <td><?= $book['id'] ?></td>

    <!-- COVER (LIKE STAFF IMAGE) -->
    <td>
        <?php
        $cover = !empty($book['cover_image']) ? "uploads/" . $book['cover_image'] : $default_cover;
        ?>
        <img src="<?= $cover ?>" 
             style="width:60px;height:80px;object-fit:cover;border-radius:8px;"
             onerror="this.src='<?= $default_cover ?>';">
    </td>

    <!-- VIDEO -->
    <td>
        <?php if (!empty($book['video'])): ?>
            <video controls style="width:120px;height:80px;border-radius:8px;">
                <source src="uploads/<?= $book['video'] ?>" type="video/mp4">
            </video>
        <?php else: ?>
            <span style="color:#999;">No video</span>
        <?php endif; ?>
    </td>

    <td><?= htmlspecialchars($book['name']) ?></td>
    <td><?= htmlspecialchars($book['author']) ?></td>
    <td>$<?= number_format($book['price'],2) ?></td>
    <td><?= $book['qty'] ?></td>

    <td>
        <a href="?delete=<?= $book['id'] ?>" onclick="return confirm('Delete?')">
            <button style="background:red;color:white;">Delete</button>
        </a>
    </td>
</tr>

<?php endwhile; ?>