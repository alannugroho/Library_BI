<?php

declare(strict_types=1);

function handle_auth_route(string $path, string $method): bool
{
    if ($path === '/login' && $method === 'GET') {
        $pageTitle = 'Masuk';
        $flash = consume_flash();
        render_view('auth/login', compact('pageTitle', 'flash'));
    }

    if ($path === '/login' && $method === 'POST') {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Sesi formulir kedaluwarsa. Silakan coba lagi.');
            legacy_redirect('/login');
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $blockedUntil = (int) ($_SESSION['login_blocked_until'] ?? 0);
        if ($blockedUntil > time()) {
            flash('error', 'Terlalu banyak percobaan login. Silakan coba lagi beberapa menit lagi.');
            legacy_redirect('/login');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            flash('error', 'Masukkan email dan kata sandi yang valid.');
            legacy_redirect('/login');
        }

        try {
            $statement = database()->prepare(
                'SELECT id, email, password_hash, role, status FROM users WHERE email = :email LIMIT 1'
            );
            $statement->execute(['email' => $email]);
            $user = $statement->fetch();
        } catch (PDOException $exception) {
            error_log('Login query failed: ' . $exception->getMessage());
            flash('error', 'Login belum tersedia karena database belum siap.');
            legacy_redirect('/login');
        }

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $_SESSION['login_attempts'] = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
            if ($_SESSION['login_attempts'] >= 5) {
                $_SESSION['login_blocked_until'] = time() + 300;
            }
            flash('error', 'Email atau kata sandi tidak sesuai.');
            legacy_redirect('/login');
        }

        if ($user['status'] !== 'active') {
            flash('error', 'Akun belum aktif. Silakan menunggu verifikasi atau persetujuan pustakawan.');
            legacy_redirect('/login');
        }

        session_regenerate_id(true);
        clear_login_attempts();
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
        legacy_redirect('/dashboard');
    }

    if ($path === '/register' && $method === 'GET') {
        $pageTitle = 'Registrasi eksternal';
        $flash = consume_flash();
        render_view('auth/register', compact('pageTitle', 'flash'));
    }

    if ($path === '/register' && $method === 'POST') {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Sesi formulir kedaluwarsa. Silakan coba lagi.');
            legacy_redirect('/register');
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Masukkan alamat email yang valid.');
            legacy_redirect('/register');
        }
        if (strlen($password) < 8 || $password !== $passwordConfirmation) {
            flash('error', 'Kata sandi minimal 8 karakter dan harus sama dengan konfirmasinya.');
            legacy_redirect('/register');
        }

        try {
            $pdo = database();
            $pdo->beginTransaction();
            $userStatement = $pdo->prepare(
                "INSERT INTO users (email, password_hash, role, status)
                 VALUES (:email, :password_hash, 'eksternal', 'pending')"
            );
            $userStatement->execute([
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $userId = (int) $pdo->lastInsertId();
            $token = bin2hex(random_bytes(32));
            $tokenStatement = $pdo->prepare(
                'INSERT INTO email_verifications (user_id, token, expires_at)
                 VALUES (:user_id, :token, DATE_ADD(NOW(), INTERVAL 24 HOUR))'
            );
            $tokenStatement->execute(['user_id' => $userId, 'token' => hash('sha256', $token)]);
            $pdo->commit();

            $verificationUrl = '/verify-email?token=' . urlencode($token);
            $message = 'Registrasi berhasil. Akun Anda menunggu verifikasi email dan persetujuan pustakawan.';
            if (app_config()['app']['environment'] === 'local') {
                $message .= ' Tautan pengujian: ' . $verificationUrl;
            }
            flash('success', $message);
            legacy_redirect('/login');
        } catch (PDOException $exception) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errorCode = is_array($exception->errorInfo ?? null) ? (int) ($exception->errorInfo[1] ?? 0) : 0;
            if ($errorCode === 1062) {
                flash('error', 'Email tersebut sudah terdaftar.');
            } else {
                error_log('Registration failed: ' . $exception->getMessage());
                flash('error', 'Registrasi gagal. Pastikan database sudah siap.');
            }
            legacy_redirect('/register');
        }
    }

    if ($path === '/verify-email' && $method === 'GET') {
        $token = (string) ($_GET['token'] ?? '');
        $verified = false;
        if (preg_match('/^[a-f0-9]{64}$/', $token) === 1) {
            try {
                $statement = database()->prepare(
                    "SELECT ev.user_id
                     FROM email_verifications ev
                     WHERE ev.token = :token AND ev.verified_at IS NULL AND ev.expires_at > NOW()
                     LIMIT 1"
                );
                $statement->execute(['token' => hash('sha256', $token)]);
                $verification = $statement->fetch();
                if ($verification) {
                    $pdo = database();
                    $pdo->beginTransaction();
                    $pdo->prepare('UPDATE email_verifications SET verified_at = NOW() WHERE token = :token')
                        ->execute(['token' => hash('sha256', $token)]);
                    $pdo->prepare("UPDATE users SET status = 'pending' WHERE id = :id")
                        ->execute(['id' => $verification['user_id']]);
                    $pdo->commit();
                    $verified = true;
                }
            } catch (PDOException $exception) {
                error_log('Email verification failed: ' . $exception->getMessage());
            }
        }

        $pageTitle = $verified ? 'Email terverifikasi' : 'Verifikasi tidak valid';
        render_view('auth/verification', compact('pageTitle', 'verified'));
    }

    if ($path === '/logout' && $method === 'POST') {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Sesi formulir kedaluwarsa. Silakan coba lagi.');
            legacy_redirect('/dashboard');
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        legacy_redirect('/');
    }

    return false;
}

