<?php

namespace App\Controllers;

use App\Abstracts\AbstractController;
use App\Middleware\RateLimiter;
use App\Repositories\UserRepository;
use App\Services\Config;
use App\Services\Csrf;
use App\Services\Logger;
use App\Services\PasswordHasher;
use App\Services\Route;
use App\Services\Turnstile;
use App\Services\Validator;

/**
 * UserController — Authentication example with full security stack.
 *
 * Demonstrates: Argon2id hashing, CSRF, Turnstile, rate limiting,
 * session hardening, and structured logging.
 */
class UserController extends AbstractController
{
    /**
     * GET /login — display the sign-in form.
     */
    #[Route('/login', methods: ['GET'])]
    public function displayLoginForm(): void
    {
        // Release session lock for non-mutating request.
        session_write_close();
        $this->render('home/home'); // Replace with your login view.
    }

    /**
     * POST /login — process the sign-in form.
     */
    #[Route('/login', methods: ['POST'])]
    public function processLogin(): void
    {
        // --- Rate Limiting ---
        RateLimiter::enforce('login',
            Config::getInt('RATE_LIMIT_LOGIN', 5),
            Config::getInt('RATE_LIMIT_WINDOW', 60)
        );

        // --- Turnstile Verification ---
        $turnstileResponse = $_POST['cf-turnstile-response'] ?? '';
        $turnstileResult   = Turnstile::verify($turnstileResponse, $_SERVER['REMOTE_ADDR'] ?? null);

        if ($turnstileResult->failed()) {
            $this->redirect('login', [], ['Bot verification failed. Please try again.']);
        }

        // --- Input Validation ---
        $errors = Validator::validate($_POST, [
            'email'    => ['required', 'email', 'max:255'],
            'password' => ['required', 'min:1', 'max:128'],
        ]);

        if (!empty($errors)) {
            $this->redirect('login', [], $errors);
        }

        // --- Authentication ---
        $repo = new UserRepository();
        $user = $repo->findByEmail($_POST['email']);

        if (!$user || !PasswordHasher::verify($_POST['password'], $user->getPassword())) {
            Logger::channel('security')->warning('Failed login attempt', [
                'email' => $_POST['email'],
                'ip'    => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
            $this->redirect('login', [], ['Invalid email or password.']);
        }

        // --- Rehash if needed (transparent upgrade) ---
        if (PasswordHasher::needsRehash($user->getPassword())) {
            $repo->updateById($user->getUserId(), [
                'password' => PasswordHasher::hash($_POST['password']),
            ]);
        }

        // --- Session Hardening ---
        session_regenerate_id(true);
        Csrf::refreshToken();

        $_SESSION['connected']  = true;
        $_SESSION['user_id']    = $user->getUserId();
        $_SESSION['firstName']  = $user->getFirstName();
        $_SESSION['role']       = match ($user->getRoleId()) {
            3       => 'admin',
            2       => 'editor',
            default => 'user',
        };
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';

        Logger::channel('app')->info('User logged in', [
            'user_id' => $user->getUserId(),
        ]);

        $this->redirect('dashboard');
    }

    /**
     * POST /logout — destroy session and redirect.
     */
    #[Route('/logout', methods: ['POST'])]
    public function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;

        session_unset();
        session_destroy();

        if ($userId) {
            Logger::channel('app')->info('User logged out', ['user_id' => $userId]);
        }

        // Start a fresh session for flash messages.
        session_start();
        $_SESSION['success'] = 'You have been logged out successfully.';

        $this->redirect('login');
    }

    /**
     * GET /dashboard — protected page (requires authentication).
     */
    #[Route('/dashboard', methods: ['GET'], authRequired: true)]
    public function displayDashboard(): void
    {
        // Release session lock for read-only page.
        session_write_close();
        $this->render('dashboard/dashboard');
    }
}