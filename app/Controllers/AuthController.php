<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\UserModel;

class AuthController extends Controller {
    private UserModel $userModel;

    public function __construct() {
        $this->userModel = new UserModel();
    }

    public function login(): void {
        // If already logged in
        if (isset($_SESSION['user_id'])) {
            $this->redirect('/');
        }
        
        // This view uses a special layout without sidebar, or we just load it directly
        // Let's create a specific view that contains full HTML since it's a login page
        $this->partial('auth/login');
    }

    public function loginProcess(): void {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $this->flash('danger', 'Username dan password wajib diisi.');
            $this->redirect('/login');
        }

        $user = $this->userModel->findByUsername($username);

        if ($user) {
            $dbHash = $user['password'];
            $isValid = false;
            $needsUpgrade = false;

            // 1. Cek bcrypt (standar baru)
            if (password_verify($password, $dbHash)) {
                $isValid = true;
                // Cek apakah perlu rehash (misal cost berubah di masa depan)
                if (password_needs_rehash($dbHash, PASSWORD_DEFAULT)) {
                    $needsUpgrade = true;
                }
            } 
            // 2. Fallback SHA-256 (standar lama)
            elseif (hash('sha256', $password) === $dbHash) {
                $isValid = true;
                $needsUpgrade = true;
            }

            if ($isValid) {
                // Auto upgrade ke bcrypt jika masih SHA-256
                if ($needsUpgrade) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $this->userModel->update((int)$user['id'], ['password' => $newHash]);
                }

                // Setup Session
                $_SESSION['user_id']      = $user['id'];
                $_SESSION['username']     = $user['username'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['role']         = $user['role'];
                $_SESSION['login_time']   = time();
                
                // Load Permissions
                $_SESSION['permissions'] = $this->userModel->getPermissions((int)$user['id']);

                $this->logActivity('login', 'Sistem', 'User berhasil login');
                $this->redirect('/');
            }
        }

        $this->flash('danger', 'Username atau password salah!');
        $this->redirect('/login');
    }

    public function logout(): void {
        if (isset($_SESSION['user_id'])) {
            $this->logActivity('logout', 'Sistem', 'User berhasil logout');
        }
        session_unset();
        session_destroy();
        $this->redirect('/login');
    }
}
