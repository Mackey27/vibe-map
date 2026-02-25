<?php
require_once __DIR__ . '/db.php';

$loginError = '';
$usernameValue = '';
$authenticatedUser = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $usernameValue = trim($_POST['username'] ?? '');
  $password = (string) ($_POST['password'] ?? '');

  if ($usernameValue === '' || $password === '') {
    $loginError = 'Please fill in all fields.';
  } else {
    $stmt = mysqli_prepare($conn, "SELECT password_hash FROM users WHERE username = ? LIMIT 1");
    if (!$stmt) {
      $loginError = 'Unable to log in right now.';
    } else {
      mysqli_stmt_bind_param($stmt, 's', $usernameValue);
      mysqli_stmt_execute($stmt);
      mysqli_stmt_store_result($stmt);

      if (mysqli_stmt_num_rows($stmt) !== 1) {
        $loginError = 'Invalid username or password.';
      } else {
        mysqli_stmt_bind_result($stmt, $passwordHash);
        mysqli_stmt_fetch($stmt);
        if (!password_verify($password, $passwordHash)) {
          $loginError = 'Invalid username or password.';
        } else {
          $authenticatedUser = $usernameValue;
        }
      }

      mysqli_stmt_close($stmt);
    }
  }
}

if ($authenticatedUser !== ''):
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Redirecting...</title>
  <link rel="icon" type="logo" href="logo.png">
</head>
<body>
  <script>
    try {
      localStorage.setItem('vibemap_auth_user', <?= json_encode($authenticatedUser, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);
    } catch (error) {}
    window.location.replace('index.php');
  </script>
</body>
</html>
<?php
exit;
endif;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VibeMap Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
      --accent-soft: #fff0ec;
      --mint: #4ecdc4;
      --mint-soft: #e8faf8;
      --lavender: #a78bfa;
      --lavender-soft: #f3f0ff;
    }

    body {
      font-family: 'Outfit', sans-serif;
      background: radial-gradient(circle at 20% 20%, var(--mint-soft), transparent 40%), var(--bg);
      color: var(--fg);
      min-height: 100vh;
    }
  </style>
</head>
<body class="flex items-center justify-center p-4">
  <div class="w-full max-w-md rounded-3xl bg-[var(--card)] border border-[var(--border)] shadow-xl overflow-hidden">
    <div class="p-6 border-b border-[var(--border)] bg-gradient-to-b from-[var(--bg-warm)] to-[var(--card)]">
      <div class="mx-auto mb-3 w-56 sm:w-64 h-20 sm:h-24 overflow-hidden">
        <img
          src="vibemap.png"
          alt="VibeMap logo"
          class="w-full h-full object-cover object-center"
        >
      </div>
      <p class="text-sm text-[var(--muted)] mt-1">Sign in to continue</p>
    </div>

    <form method="post" class="p-6 space-y-4">
      <?php if ($loginError !== ''): ?>
        <div class="rounded-2xl border border-[var(--accent)]/30 bg-[var(--accent)]/10 px-4 py-3 text-sm text-[var(--fg)]">
          <?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?>
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
          placeholder="Enter username"
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
          autocomplete="current-password"
          class="w-full px-4 py-3 rounded-2xl bg-[var(--bg)] border border-[var(--border)] text-sm outline-none focus:border-[var(--accent)]"
          placeholder="Enter password"
          required
        >
      </div>
      
      <button
        type="submit"
        class="w-full py-3.5 rounded-2xl bg-gradient-to-r from-[var(--accent)] to-[#ff8a65] text-white font-semibold shadow-lg shadow-[var(--accent)]/25"
      >
        Login
      </button>

      <p class="text-sm text-center text-[var(--muted)]">
        New to VibeMap?
        <a href="register.php" class="font-semibold text-[var(--accent)]">Create account</a>
      </p>
    </form>
  </div>
</body>
</html>
