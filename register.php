<?php
session_start();
require_once __DIR__ . '/db.php';

$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
  require_once $composerAutoload;
}

const REGISTRATION_CODE_TTL_SECONDS = 600;
const REGISTRATION_CODE_RESEND_COOLDOWN_SECONDS = 30;
const PENDING_REGISTRATION_SESSION_KEY = 'vibemap_pending_registration';

function load_local_smtp_config(): array
{
  static $config = null;

  if (is_array($config)) {
    return $config;
  }

  $config = [];
  $configPath = __DIR__ . '/smtp-config.php';
  if (!is_file($configPath)) {
    return $config;
  }

  $loaded = require $configPath;
  if (!is_array($loaded)) {
    return $config;
  }

  foreach ($loaded as $key => $value) {
    if (!is_string($key)) {
      continue;
    }
    if (is_string($value) || is_numeric($value)) {
      $config[$key] = trim((string) $value);
    }
  }

  return $config;
}

function env_string(string $key, string $default = ''): string
{
  $values = [
    getenv($key),
    $_ENV[$key] ?? null,
    $_SERVER[$key] ?? null,
  ];

  if (function_exists('apache_getenv')) {
    $apacheValue = apache_getenv($key, true);
    if ($apacheValue !== false) {
      $values[] = $apacheValue;
    }
  }

  foreach ($values as $value) {
    if ($value === false || $value === null) {
      continue;
    }
    $stringValue = trim((string) $value);
    if ($stringValue !== '') {
      return $stringValue;
    }
  }

  $localConfig = load_local_smtp_config();
  if (isset($localConfig[$key])) {
    $stringValue = trim((string) $localConfig[$key]);
    if ($stringValue !== '') {
      return $stringValue;
    }
  }

  return $default;
}

function mb_strlen_safe(string $value): int
{
  if (function_exists('mb_strlen')) {
    return (int) mb_strlen($value);
  }
  return strlen($value);
}

function clear_pending_registration(): void
{
  unset($_SESSION[PENDING_REGISTRATION_SESSION_KEY]);
}

function ensure_users_storage(mysqli $conn, string &$error): bool
{
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
    $error = 'Failed to prepare user storage.';
    return false;
  }

  $checkFullNameColumnResult = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'full_name'");
  if ($checkFullNameColumnResult === false) {
    $error = 'Failed to prepare user storage.';
    return false;
  }
  if (mysqli_num_rows($checkFullNameColumnResult) === 0) {
    if (!mysqli_query($conn, "ALTER TABLE users ADD COLUMN full_name VARCHAR(150) NOT NULL DEFAULT '' AFTER username")) {
      mysqli_free_result($checkFullNameColumnResult);
      $error = 'Failed to prepare user storage.';
      return false;
    }
  }
  mysqli_free_result($checkFullNameColumnResult);

  return true;
}

function send_registration_code_email(string $email, string $fullName, string $code, string &$error): bool
{
  $mailerClass = '\\PHPMailer\\PHPMailer\\PHPMailer';
  if (!class_exists($mailerClass)) {
    $error = 'Mail library not installed. Run: composer require phpmailer/phpmailer';
    return false;
  }

  $host = env_string('VIBEMAP_SMTP_HOST', 'smtp.gmail.com');
  $port = (int) env_string('VIBEMAP_SMTP_PORT', '587');
  $encryption = strtolower(env_string('VIBEMAP_SMTP_ENCRYPTION', 'tls'));
  $username = env_string('VIBEMAP_SMTP_USERNAME');
  $password = env_string('VIBEMAP_SMTP_PASSWORD');
  $fromEmail = env_string('VIBEMAP_SMTP_FROM_EMAIL', $username);
  $fromName = env_string('VIBEMAP_SMTP_FROM_NAME', 'VibeMap');

  if ($username !== '' && strpos($username, '@') === false && filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
    $username = $fromEmail;
  }

  if ($username === '' || $password === '' || $fromEmail === '') {
    $error = 'SMTP is not configured. Set VIBEMAP_SMTP_USERNAME, VIBEMAP_SMTP_PASSWORD, and VIBEMAP_SMTP_FROM_EMAIL (or create smtp-config.php).';
    return false;
  }

  $subject = 'Your VibeMap authentication code';
  $safeName = $fullName !== '' ? $fullName : 'there';
  $body = "Hi {$safeName},\n\nYour VibeMap authentication code is: {$code}\n\nThis code expires in 10 minutes.\n\nIf you did not request this, you can ignore this email.";

  try {
    $mail = new $mailerClass(true);
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->Port = $port;
    $mail->SMTPAuth = true;
    $mail->Username = $username;
    $mail->Password = $password;
    $mail->Timeout = 20;
    $mail->CharSet = 'UTF-8';
    $mail->SMTPDebug = 0;

    if ($encryption === 'ssl') {
      $mail->SMTPSecure = $mailerClass::ENCRYPTION_SMTPS;
    } else {
      $mail->SMTPSecure = $mailerClass::ENCRYPTION_STARTTLS;
    }

    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($email, $safeName);
    $mail->Subject = $subject;
    $mail->Body = $body;
    $mail->isHTML(false);

    if ($mail->send()) {
      return true;
    }
  } catch (\Throwable $e) {
    // Keep error generic for safety.
  }

  $error = 'Unable to send authentication code to Gmail right now. Check SMTP credentials and try again.';
  return false;
}

$registerError = '';
$registerInfo = '';
$showVerificationStep = false;
$fullNameValue = '';
$emailValue = '';
$verificationCodeValue = '';

$pendingRegistration = isset($_SESSION[PENDING_REGISTRATION_SESSION_KEY]) && is_array($_SESSION[PENDING_REGISTRATION_SESSION_KEY])
  ? $_SESSION[PENDING_REGISTRATION_SESSION_KEY]
  : null;

if ($pendingRegistration !== null) {
  $expiresAt = (int) ($pendingRegistration['expires_at'] ?? 0);
  if ($expiresAt <= time()) {
    clear_pending_registration();
    $pendingRegistration = null;
    $registerError = 'Authentication code expired. Please register again.';
  }
}

if ($pendingRegistration !== null) {
  $showVerificationStep = true;
  $fullNameValue = (string) ($pendingRegistration['full_name'] ?? '');
  $emailValue = (string) ($pendingRegistration['email'] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = trim((string) ($_POST['action'] ?? 'start_register'));

  if ($action === 'cancel_verification') {
    clear_pending_registration();
    $pendingRegistration = null;
    $showVerificationStep = false;
    $fullNameValue = '';
    $emailValue = '';
    $verificationCodeValue = '';
  } elseif ($action === 'resend_code' || $action === 'verify_code') {
    $pendingRegistration = isset($_SESSION[PENDING_REGISTRATION_SESSION_KEY]) && is_array($_SESSION[PENDING_REGISTRATION_SESSION_KEY])
      ? $_SESSION[PENDING_REGISTRATION_SESSION_KEY]
      : null;

    if ($pendingRegistration === null) {
      $registerError = 'No pending registration found. Please register again.';
      $showVerificationStep = false;
    } else {
      $showVerificationStep = true;
      $fullNameValue = (string) ($pendingRegistration['full_name'] ?? '');
      $emailValue = (string) ($pendingRegistration['email'] ?? '');

      $expiresAt = (int) ($pendingRegistration['expires_at'] ?? 0);
      if ($expiresAt <= time()) {
        clear_pending_registration();
        $pendingRegistration = null;
        $showVerificationStep = false;
        $registerError = 'Authentication code expired. Please register again.';
      } elseif ($action === 'resend_code') {
        $lastSentAt = (int) ($pendingRegistration['last_sent_at'] ?? 0);
        $elapsed = time() - $lastSentAt;
        if ($elapsed < REGISTRATION_CODE_RESEND_COOLDOWN_SECONDS) {
          $waitSeconds = REGISTRATION_CODE_RESEND_COOLDOWN_SECONDS - $elapsed;
          $registerInfo = "Please wait {$waitSeconds}s before resending.";
        } else {
          $newCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
          $sendError = '';
          if (!send_registration_code_email($emailValue, $fullNameValue, $newCode, $sendError)) {
            $registerError = $sendError !== '' ? $sendError : 'Unable to send authentication code.';
          } else {
            $pendingRegistration['code_hash'] = password_hash($newCode, PASSWORD_DEFAULT);
            $pendingRegistration['expires_at'] = time() + REGISTRATION_CODE_TTL_SECONDS;
            $pendingRegistration['last_sent_at'] = time();
            $_SESSION[PENDING_REGISTRATION_SESSION_KEY] = $pendingRegistration;
            $registerInfo = 'New authentication code sent to your Gmail.';
          }
        }
      } else {
        $verificationCodeValue = trim((string) ($_POST['verification_code'] ?? ''));
        if ($verificationCodeValue === '') {
          $registerError = 'Please enter the authentication code.';
        } elseif (!preg_match('/^\d{6}$/', $verificationCodeValue)) {
          $registerError = 'Authentication code must be 6 digits.';
        } elseif (!password_verify($verificationCodeValue, (string) ($pendingRegistration['code_hash'] ?? ''))) {
          $registerError = 'Invalid authentication code.';
        } else {
          if (ensure_users_storage($conn, $registerError)) {
            $checkStmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? LIMIT 1");
            if (!$checkStmt) {
              $registerError = 'Failed to validate Gmail address.';
            } else {
              $pendingEmail = (string) ($pendingRegistration['email'] ?? '');
              mysqli_stmt_bind_param($checkStmt, 's', $pendingEmail);
              mysqli_stmt_execute($checkStmt);
              mysqli_stmt_store_result($checkStmt);
              $exists = mysqli_stmt_num_rows($checkStmt) > 0;
              mysqli_stmt_close($checkStmt);

              if ($exists) {
                clear_pending_registration();
                $registerError = 'Gmail address is already registered. Please log in.';
                $showVerificationStep = false;
              } else {
                $pendingFullName = (string) ($pendingRegistration['full_name'] ?? '');
                $pendingPasswordHash = (string) ($pendingRegistration['password_hash'] ?? '');

	                $insertStmt = mysqli_prepare($conn, "INSERT INTO users (username, full_name, password_hash) VALUES (?, ?, ?)");
	                if (!$insertStmt) {
	                  $registerError = 'Failed to create account.';
	                } else {
	                  mysqli_stmt_bind_param($insertStmt, 'sss', $pendingEmail, $pendingFullName, $pendingPasswordHash);
	                  if (mysqli_stmt_execute($insertStmt)) {
	                    clear_pending_registration();
	                    $_SESSION['vibemap_register_success'] = 'Account created successfully. You can now log in.';
	                    header('Location: login.php');
	                    exit;
	                  }
	                  $registerError = 'Failed to save account.';
	                  mysqli_stmt_close($insertStmt);
	                }
              }
            }
          }
        }
      }
    }
  } else {
    $fullNameValue = trim((string) ($_POST['full_name'] ?? ''));
    $emailValue = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $fullNameLength = mb_strlen_safe($fullNameValue);

    if ($fullNameValue === '' || $emailValue === '' || $password === '' || $confirmPassword === '') {
      $registerError = 'Please fill in all fields.';
    } elseif ($fullNameLength < 2) {
      $registerError = 'Full name must be at least 2 characters.';
    } elseif ($fullNameLength > 150) {
      $registerError = 'Full name is too long.';
    } elseif (!filter_var($emailValue, FILTER_VALIDATE_EMAIL) || !preg_match('/@gmail\.com$/i', $emailValue)) {
      $registerError = 'Please enter a valid Gmail address.';
    } elseif (strlen($password) < 6) {
      $registerError = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
      $registerError = 'Passwords do not match.';
    } elseif (ensure_users_storage($conn, $registerError)) {
      $checkStmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? LIMIT 1");
      if (!$checkStmt) {
        $registerError = 'Failed to validate Gmail address.';
      } else {
        mysqli_stmt_bind_param($checkStmt, 's', $emailValue);
        mysqli_stmt_execute($checkStmt);
        mysqli_stmt_store_result($checkStmt);
        $exists = mysqli_stmt_num_rows($checkStmt) > 0;
        mysqli_stmt_close($checkStmt);

        if ($exists) {
          $registerError = 'Gmail address is already registered.';
        } else {
          $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
          $sendError = '';
          if (!send_registration_code_email($emailValue, $fullNameValue, $code, $sendError)) {
            $registerError = $sendError !== '' ? $sendError : 'Unable to send authentication code.';
          } else {
            $pendingRegistration = [
              'full_name' => $fullNameValue,
              'email' => $emailValue,
              'password_hash' => password_hash($password, PASSWORD_DEFAULT),
              'code_hash' => password_hash($code, PASSWORD_DEFAULT),
              'expires_at' => time() + REGISTRATION_CODE_TTL_SECONDS,
              'last_sent_at' => time(),
            ];
            $_SESSION[PENDING_REGISTRATION_SESSION_KEY] = $pendingRegistration;
            $showVerificationStep = true;
            $registerInfo = 'Authentication code sent to your Gmail. Enter the 6-digit code below.';
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
      <h1 class="text-2xl font-bold tracking-tight"><?= $showVerificationStep ? 'Verify Your Gmail' : 'Create Account' ?></h1>
      <p class="text-sm text-[var(--muted)] mt-1">
        <?= $showVerificationStep ? 'Enter the authentication code sent to Gmail' : 'Register using your Gmail account' ?>
      </p>
    </div>

    <form method="post" class="p-6 space-y-4">
      <?php if ($registerError !== ''): ?>
        <div class="rounded-2xl border border-[var(--accent)]/30 bg-[var(--accent)]/10 px-4 py-3 text-sm text-[var(--fg)]">
          <?= htmlspecialchars($registerError, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <?php if ($registerInfo !== ''): ?>
        <div class="rounded-2xl border border-[var(--mint)]/30 bg-[var(--mint)]/10 px-4 py-3 text-sm text-[var(--fg)]">
          <?= htmlspecialchars($registerInfo, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <?php if ($showVerificationStep): ?>
        <div class="rounded-2xl border border-[var(--border)] bg-[var(--bg)] px-4 py-3 text-sm">
          <p><span class="text-[var(--muted)]">Full name:</span> <?= htmlspecialchars($fullNameValue, ENT_QUOTES, 'UTF-8') ?></p>
          <p><span class="text-[var(--muted)]">Gmail:</span> <?= htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <div>
          <label for="verification-code" class="block text-sm font-medium mb-1.5">Authentication Code</label>
          <input
            id="verification-code"
            name="verification_code"
            type="text"
            inputmode="numeric"
            maxlength="6"
            pattern="[0-9]{6}"
            class="w-full px-4 py-3 rounded-2xl bg-[var(--bg)] border border-[var(--border)] text-sm outline-none tracking-[0.25em] text-center focus:border-[var(--accent)]"
            placeholder="000000"
            value="<?= htmlspecialchars($verificationCodeValue, ENT_QUOTES, 'UTF-8') ?>"
            required
          >
        </div>

        <button
          type="submit"
          name="action"
          value="verify_code"
          class="w-full py-3.5 rounded-2xl bg-gradient-to-r from-[var(--accent)] to-[#ff8a65] text-white font-semibold shadow-lg shadow-[var(--accent)]/25"
        >
          Verify And Create Account
        </button>

        <div class="grid grid-cols-2 gap-3">
          <button
            type="submit"
            name="action"
            value="resend_code"
            class="w-full py-2.5 rounded-2xl border border-[var(--border)] bg-[var(--bg)] text-sm font-semibold hover:bg-[var(--border)] transition-colors"
          >
            Resend Code
          </button>
          <button
            type="submit"
            name="action"
            value="cancel_verification"
            class="w-full py-2.5 rounded-2xl border border-[var(--border)] bg-[var(--bg)] text-sm font-semibold hover:bg-[var(--border)] transition-colors"
          >
            Start Over
          </button>
        </div>
      <?php else: ?>
        <div>
          <label for="full-name" class="block text-sm font-medium mb-1.5">Full Name</label>
          <input
            id="full-name"
            name="full_name"
            type="text"
            autocomplete="name"
            class="w-full px-4 py-3 rounded-2xl bg-[var(--bg)] border border-[var(--border)] text-sm outline-none focus:border-[var(--accent)]"
            placeholder="Enter your full name"
            value="<?= htmlspecialchars($fullNameValue, ENT_QUOTES, 'UTF-8') ?>"
            required
          >
        </div>

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
              autocomplete="new-password"
              class="w-full px-4 py-3 pr-12 rounded-2xl bg-[var(--bg)] border border-[var(--border)] text-sm outline-none focus:border-[var(--accent)]"
              placeholder="At least 6 characters"
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

        <div>
          <label for="confirm-password" class="block text-sm font-medium mb-1.5">Confirm password</label>
          <div class="relative">
            <input
              id="confirm-password"
              name="confirm_password"
              type="password"
              autocomplete="new-password"
              class="w-full px-4 py-3 pr-12 rounded-2xl bg-[var(--bg)] border border-[var(--border)] text-sm outline-none focus:border-[var(--accent)]"
              placeholder="Re-enter your password"
              required
            >
            <button
              type="button"
              data-password-toggle
              data-target="confirm-password"
              aria-label="Show confirm password"
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
          name="action"
          value="start_register"
          class="w-full py-3.5 rounded-2xl bg-gradient-to-r from-[var(--accent)] to-[#ff8a65] text-white font-semibold shadow-lg shadow-[var(--accent)]/25"
        >
          Register
        </button>
      <?php endif; ?>

      <p class="text-sm text-center text-[var(--muted)]">
        Already have an account?
        <a href="login.php" class="font-semibold text-[var(--accent)]">Go to login</a>
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

        var showLabel = targetId === 'confirm-password' ? 'Show confirm password' : 'Show password';
        var hideLabel = targetId === 'confirm-password' ? 'Hide confirm password' : 'Hide password';
        button.setAttribute('aria-label', showPassword ? hideLabel : showLabel);
      });
    });
  </script>
</body>
</html>
