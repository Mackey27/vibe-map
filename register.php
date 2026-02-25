<?php
require_once __DIR__ . '/db.php';

$registerError = '';
$usernameValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $usernameValue = trim($_POST['username'] ?? '');
  $password = (string) ($_POST['password'] ?? '');
  $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

  if ($usernameValue === '' || $password === '' || $confirmPassword === '') {
    $registerError = 'Please fill in all fields.';
  } elseif (strlen($usernameValue) < 3) {
    $registerError = 'Username must be at least 3 characters.';
  } elseif (strlen($password) < 6) {
    $registerError = 'Password must be at least 6 characters.';
  } elseif ($password !== $confirmPassword) {
    $registerError = 'Passwords do not match.';
  } else {
    $createUsersTableSql = "
      CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    if (!mysqli_query($conn, $createUsersTableSql)) {
      $registerError = 'Failed to prepare user storage.';
    } else {
      $checkStmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? LIMIT 1");
      if (!$checkStmt) {
        $registerError = 'Failed to validate username.';
      } else {
        mysqli_stmt_bind_param($checkStmt, 's', $usernameValue);
        mysqli_stmt_execute($checkStmt);
        mysqli_stmt_store_result($checkStmt);
        $exists = mysqli_stmt_num_rows($checkStmt) > 0;
        mysqli_stmt_close($checkStmt);

        if ($exists) {
          $registerError = 'Username already exists.';
        } else {
          $passwordHash = password_hash($password, PASSWORD_DEFAULT);
          $insertStmt = mysqli_prepare($conn, "INSERT INTO users (username, password_hash) VALUES (?, ?)");
          if (!$insertStmt) {
            $registerError = 'Failed to create account.';
          } else {
            mysqli_stmt_bind_param($insertStmt, 'ss', $usernameValue, $passwordHash);
            if (mysqli_stmt_execute($insertStmt)) {
              header('Location: login.php');
              exit;
            } else {
              $registerError = 'Failed to save account.';
            }
            mysqli_stmt_close($insertStmt);
          }
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VibeMap Register</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="icon" type="logo" href="logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #f5f0e8;
      --bg-warm: #fff8f0;
      --card: #ffffff;
      --border: #e8dfd4;
      --fg: #2d2a26;
      --muted: #8a8279;
      --accent: #ff6b4a;
      --mint: #4ecdc4;
      --mint-soft: #e8faf8;
      --lavender-soft: #f3f0ff;
    }

    body {
      font-family: 'Outfit', sans-serif;
      background: radial-gradient(circle at 80% 10%, var(--lavender-soft), transparent 35%), var(--bg);
      color: var(--fg);
      min-height: 100vh;
    }
  </style>
</head>
<body class="flex items-center justify-center p-4">
  <div class="w-full max-w-md rounded-3xl bg-[var(--card)] border border-[var(--border)] shadow-xl overflow-hidden">
    <div class="p-6 border-b border-[var(--border)] bg-gradient-to-b from-[var(--bg-warm)] to-[var(--card)]">
      <h1 class="text-2xl font-bold tracking-tight">Create Account</h1>
      <p class="text-sm text-[var(--muted)] mt-1">Register a new VibeMap account</p>
    </div>

    <form method="post" class="p-6 space-y-4">

      <?php if ($registerError !== ''): ?>
        <div class="rounded-2xl border border-[var(--accent)]/30 bg-[var(--accent)]/10 px-4 py-3 text-sm text-[var(--fg)]">
          <?= htmlspecialchars($registerError, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <div>
        <label for="username" class="block text-sm font-medium mb-1.5">Username</label>
        <input
          id="username"
          name="username"
          type="text"
          autocomplete="username"
          class="w-full px-4 py-3 rounded-2xl bg-[var(--bg)] border border-[var(--border)] text-sm outline-none focus:border-[var(--accent)]"
          placeholder="Choose username"
          value="<?= htmlspecialchars($usernameValue, ENT_QUOTES, 'UTF-8') ?>"
          required
        >
      </div>

      <div>
        <label for="password" class="block text-sm font-medium mb-1.5">Password</label>
        <input
          id="password"
          name="password"
          type="password"
          autocomplete="new-password"
          class="w-full px-4 py-3 rounded-2xl bg-[var(--bg)] border border-[var(--border)] text-sm outline-none focus:border-[var(--accent)]"
          placeholder="At least 6 characters"
          required
        >
      </div>

      <div>
        <label for="confirm-password" class="block text-sm font-medium mb-1.5">Confirm password</label>
        <input
          id="confirm-password"
          name="confirm_password"
          type="password"
          autocomplete="new-password"
          class="w-full px-4 py-3 rounded-2xl bg-[var(--bg)] border border-[var(--border)] text-sm outline-none focus:border-[var(--accent)]"
          placeholder="Re-enter your password"
          required
        >
      </div>

      <button
        type="submit"
        class="w-full py-3.5 rounded-2xl bg-gradient-to-r from-[var(--accent)] to-[#ff8a65] text-white font-semibold shadow-lg shadow-[var(--accent)]/25"
      >
        Register
      </button>

      <p class="text-sm text-center text-[var(--muted)]">
        Already have an account?
        <a href="login.php" class="font-semibold text-[var(--accent)]">Go to login</a>
      </p>
    </form>
  </div>
</body>
</html>
