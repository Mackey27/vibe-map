<?php
session_start();
require_once __DIR__ . '/db.php';

$loginError = '';
$emailValue = '';
$authenticatedUser = '';
$authenticatedFullName = '';
$registerSuccess = '';

if (isset($_SESSION['vibemap_register_success']) && is_string($_SESSION['vibemap_register_success'])) {
  $registerSuccess = trim($_SESSION['vibemap_register_success']);
  unset($_SESSION['vibemap_register_success']);
}

$createUsersTableSql = "
  CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL DEFAULT '',
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

if (!mysqli_query($conn, $createUsersTableSql)) {
  $loginError = 'Unable to log in right now.';
} else {
  $checkFullNameColumnResult = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'full_name'");
  if ($checkFullNameColumnResult === false) {
    $loginError = 'Unable to log in right now.';
  } else {
    if (mysqli_num_rows($checkFullNameColumnResult) === 0) {
      if (!mysqli_query($conn, "ALTER TABLE users ADD COLUMN full_name VARCHAR(150) NOT NULL DEFAULT '' AFTER username")) {
        mysqli_free_result($checkFullNameColumnResult);
        $loginError = 'Unable to log in right now.';
      }
    }
    mysqli_free_result($checkFullNameColumnResult);
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $emailValue = strtolower(trim($_POST['email'] ?? ''));
  $password = (string) ($_POST['password'] ?? '');

  if ($emailValue === '' || $password === '') {
    $loginError = 'Please fill in all fields.';
  } elseif (!filter_var($emailValue, FILTER_VALIDATE_EMAIL) || !preg_match('/@gmail\.com$/i', $emailValue)) {
    $loginError = 'Please enter a valid Gmail address.';
  } elseif ($loginError !== '') {
    // Storage setup failed earlier.
  } else {
    $stmt = mysqli_prepare($conn, "SELECT password_hash, full_name FROM users WHERE username = ? LIMIT 1");
    if (!$stmt) {
      $loginError = 'Unable to log in right now.';
    } else {
      mysqli_stmt_bind_param($stmt, 's', $emailValue);
      mysqli_stmt_execute($stmt);
      mysqli_stmt_store_result($stmt);

      if (mysqli_stmt_num_rows($stmt) !== 1) {
        $loginError = 'Invalid Gmail or password.';
      } else {
        mysqli_stmt_bind_result($stmt, $passwordHash, $storedFullName);
        mysqli_stmt_fetch($stmt);
        if (!password_verify($password, $passwordHash)) {
          $loginError = 'Invalid Gmail or password.';
        } else {
          $authenticatedUser = $emailValue;
          $fullNameValue = trim((string) ($storedFullName ?? ''));
          if ($fullNameValue === '') {
            $fullNameValue = $emailValue;
          }
          $authenticatedFullName = $fullNameValue;
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
</head>
<body>
  <script>
    try {
      localStorage.setItem('vibemap_auth_user', <?= json_encode($authenticatedUser, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);
      localStorage.setItem('vibemap_auth_name', <?= json_encode($authenticatedFullName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);
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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="icon" type="image/png" href="logo.png">
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
      <p class="text-sm text-[var(--muted)] mt-1">Sign in with Gmail to continue</p>
    </div>

    <form method="post" class="p-6 space-y-4">
      <?php if ($registerSuccess !== ''): ?>
        <div class="alert alert-success mb-0 text-sm" role="alert">
          <?= htmlspecialchars($registerSuccess, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <?php if ($loginError !== ''): ?>
        <div class="rounded-2xl border border-[var(--accent)]/30 bg-[var(--accent)]/10 px-4 py-3 text-sm text-[var(--fg)]">
          <?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <div>
        <label for="email" class="block text-sm font-medium mb-1.5">Gmail</label>
        <input
          id="email"
          name="email"
          type="email"
          inputmode="email"
          autocomplete="email"
          class="w-full px-4 py-3 rounded-2xl bg-[var(--bg)] border border-[var(--border)] text-sm outline-none focus:border-[var(--accent)]"
          placeholder="Enter Gmail address"
          value="<?= htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8') ?>"
          required
        >
      </div>

      <div>
        <label for="password" class="block text-sm font-medium mb-1.5">Password</label>
        <div class="relative">
          <input
            id="password"
            name="password"
            type="password"
            autocomplete="current-password"
            class="w-full px-4 py-3 pr-12 rounded-2xl bg-[var(--bg)] border border-[var(--border)] text-sm outline-none focus:border-[var(--accent)]"
            placeholder="Enter password"
            required
          >
          <button
            type="button"
            data-password-toggle
            data-target="password"
            aria-label="Show password"
            class="absolute inset-y-0 right-0 px-3 text-[var(--muted)] hover:text-[var(--fg)] transition-colors"
          >
            <span data-eye-open>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
            </span>
            <span data-eye-closed class="hidden">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20C5 20 1 12 1 12a21.66 21.66 0 0 1 5.06-6.94"></path>
                <path d="M9.9 4.24A10.7 10.7 0 0 1 12 4c7 0 11 8 11 8a21.79 21.79 0 0 1-3.17 4.7"></path>
                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"></path>
                <line x1="1" y1="1" x2="23" y2="23"></line>
              </svg>
            </span>
          </button>
        </div>
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
  <script>
    document.querySelectorAll('[data-password-toggle]').forEach(function(button) {
      button.addEventListener('click', function() {
        var targetId = button.getAttribute('data-target');
        if (!targetId) return;
        var input = document.getElementById(targetId);
        if (!input) return;

        var showPassword = input.type === 'password';
        input.type = showPassword ? 'text' : 'password';

        var eyeOpen = button.querySelector('[data-eye-open]');
        var eyeClosed = button.querySelector('[data-eye-closed]');
        if (eyeOpen && eyeClosed) {
          eyeOpen.classList.toggle('hidden', !showPassword);
          eyeClosed.classList.toggle('hidden', showPassword);
        }

        button.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
      });
    });
  </script>
</body>
</html>
