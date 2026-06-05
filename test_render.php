<?php
define('ROOT_PATH', __DIR__);
define('APP_PATH', __DIR__ . '/app');
define('VIEW_PATH', __DIR__ . '/views');
$_SESSION = ['user_id' => 1, 'role' => 'admin'];
$_ENV['APP_URL'] = 'http://localhost/rekap-mukholif';
require __DIR__ . '/vendor/autoload.php';
use App\Core\Controller;
class TestController extends Controller {
    public function renderDashboard() {
        $stats = ['santri' => 10, 'jenis_pelanggaran' => 5, 'total_pelanggaran' => 2, 'santri_tanpa_pelanggaran' => 8];
        $recent_violations = [];
        $frequent_violation = null;
        $top_violators = [];
        $best_students = [];
        $top_histori = [];
        $this->respond('pages/dashboard/index', [
            'stats' => $stats,
            'recent_violations' => $recent_violations,
            'frequent_violation' => $frequent_violation,
            'top_violators' => $top_violators,
            'best_students' => $best_students,
            'top_histori' => $top_histori
        ]);
    }
}
ob_start();
(new TestController())->renderDashboard();
$out = ob_get_clean();
file_put_contents('test_out.html', $out);
