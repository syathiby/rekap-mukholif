<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\DashboardModel;

class DashboardController extends Controller {
    private DashboardModel $dashboardModel;

    public function __construct() {
        // Harus login
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
        $this->dashboardModel = new DashboardModel();
    }

    public function index(): void {
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;

        $startDateSql = $this->dashboardModel->getActivePeriod() . ' 00:00:00';
        $endDateSql = date('Y-m-d 23:59:59');

        if (!empty($startDate)) {
            $startDateSql = $startDate . ' 00:00:00';
        }
        if (!empty($endDate)) {
            $endDateSql = $endDate . ' 23:59:59';
        }

        $stats = $this->dashboardModel->getStats($startDateSql, $endDateSql);
        $recentViolations = $this->dashboardModel->getRecentViolations();
        $frequentViolation = $this->dashboardModel->getFrequentViolation($startDateSql, $endDateSql);
        $topViolators = $this->dashboardModel->getTopViolators($startDateSql, $endDateSql);
        $bestStudents = $this->dashboardModel->getBestStudents($startDateSql, $endDateSql);
        $topHistory = $this->dashboardModel->getTopHistory();

        $data = [
            'stats' => $stats,
            'recent_violations' => $recentViolations,
            'frequent_violation' => $frequentViolation,
            'top_violators' => $topViolators,
            'best_students' => $bestStudents,
            'top_histori' => $topHistory,
            'start_date' => $startDate,
            'end_date' => $endDate
        ];

        $this->respond('pages/dashboard/index', $data);
    }
}
