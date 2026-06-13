<?php
declare(strict_types=1);

namespace App\Core;

use App\Helpers\AuthHelper;

abstract class Controller {

    protected function view(string $view, array $data = []): void {
        extract($data);
        $viewFile = VIEW_PATH . '/' . $view . '.php';
        
        if (file_exists($viewFile)) {
            ob_start();
            require $viewFile;
            $content = ob_get_clean();
            
            require VIEW_PATH . '/layouts/main.php';
        } else {
            die("View $view not found");
        }
    }

    protected function partial(string $view, array $data = []): void {
        extract($data);
        $viewFile = VIEW_PATH . '/' . $view . '.php';
        
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            die("Partial view $view not found");
        }
    }

    protected function respond(string $view, array $data = []): void {
        if ($this->isHtmxRequest()) {
            $this->partial($view, $data);
        } else {
            $this->view($view, $data);
        }
    }

    protected function redirect(string $url): void {
        $fullUrl = ($_ENV['APP_URL'] ?? '') . $url;
        if ($this->isHtmxRequest()) {
            header('HX-Redirect: ' . $fullUrl);
            exit;
        }
        header("Location: " . $fullUrl);
        exit;
    }

    protected function json(mixed $data, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function flash(string $type, string $message): void {
        $_SESSION['flash_message'] = [
            'type' => $type,
            'message' => $message
        ];
    }

    protected function requireAuth(): void {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
    }

    protected function requirePermission(string|array $namaIzin): void {
        if (!AuthHelper::hasPermission($namaIzin)) {
            http_response_code(403);
            if (file_exists(VIEW_PATH . '/errors/403.php')) {
                require VIEW_PATH . '/errors/403.php';
            } else {
                echo "<h1>403 Forbidden</h1><p>You don't have permission to access this resource.</p>";
            }
            exit;
        }
    }

    protected function isHtmxRequest(): bool {
        return isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
    }

    protected function generateCsrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    protected function validateCsrfToken(): void {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(419);
            die("CSRF token mismatch. Please refresh the page.");
        }
    }

    protected function logActivity(string $aksi, string $fitur, string $deskripsi, ?array $detail = null): void {
        try {
            $db = Database::getInstance()->getConnection();
            $sql = "INSERT INTO log_aktifitas (user_id, aksi, fitur, deskripsi, detail, ip_address, user_agent) 
                    VALUES (:user_id, :aksi, :fitur, :deskripsi, :detail, :ip, :ua)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':user_id' => $_SESSION['user_id'] ?? null,
                ':aksi' => $aksi,
                ':fitur' => $fitur,
                ':deskripsi' => $deskripsi,
                ':detail' => $detail ? json_encode($detail) : null,
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (\Exception $e) {
            // Silently fail if log cannot be written so it doesn't break main flow
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
}
