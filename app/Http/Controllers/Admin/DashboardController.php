<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Event;
use App\Models\TestSession;
use App\Models\TestResult;
use App\Models\QuestionVersion;
use App\Models\ST30Question;
use App\Models\SJTQuestion;
use App\Models\ResendRequest;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // User Statistics
        $totalUsers = User::count();
        $totalAdmins = User::where('role', 'admin')->count();
        $totalPICs = User::where('role', 'pic')->count();
        $totalStaff = User::where('role', 'staff')->count();
        $totalParticipants = User::where('role', 'user')->count();
        $activeUsers = User::where('is_active', true)->count();

        // Event Statistics
        $totalEvents = Event::count();
        $activeEvents = Event::where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->count();
        $upcomingEvents = Event::where('start_date', '>', now())->count();
        $expiredEvents = Event::where('end_date', '<', now())->count();

        // Test Statistics
        $totalTestSessions = TestSession::count();
        $completedTests = TestSession::where('is_completed', true)->count();
        $ongoingTests = TestSession::where('is_completed', false)->count();
        $testsToday = TestSession::whereDate('created_at', today())->count();
        $testsThisWeek = TestSession::whereBetween('created_at', [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek()
        ])->count();
        $testsThisMonth = TestSession::whereMonth('created_at', now()->month)->count();

        // Question Bank Statistics
        $totalQuestionVersions = QuestionVersion::count();
        $activeVersions = QuestionVersion::where('is_active', true)->count();
        $totalST30Questions = ST30Question::count();
        $totalSJTQuestions = SJTQuestion::count();

        // Results Statistics
        $totalResults = TestResult::count();
        $resultsWithPDF = TestResult::whereNotNull('pdf_path')->count();
        $emailsSent = TestResult::whereNotNull('email_sent_at')->count();
        $pendingResults = TestResult::whereNull('email_sent_at')->count();

        // Resend Requests
        $totalResendRequests = ResendRequest::count();
        $pendingResendRequests = ResendRequest::where('status', 'pending')->count();
        $approvedResendRequests = ResendRequest::where('status', 'approved')->count();
        $rejectedResendRequests = ResendRequest::where('status', 'rejected')->count();

        // Recent Activities
        $recentTestSessions = TestSession::with(['user', 'event'])
            ->latest()
            ->take(5)
            ->get();

        $recentResults = TestResult::with(['testSession.user', 'testSession.event'])
            ->latest()
            ->take(5)
            ->get();

        $recentResendRequests = ResendRequest::with(['user', 'testResult'])
            ->latest()
            ->take(5)
            ->get();

        // Chart Data - Tests per day (last 7 days - Default View)
        $testsPerDay = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $count = TestSession::whereDate('created_at', $date)->count();
            $testsPerDay[] = [
                'date' => $date->format('M d'),
                'count' => $count
            ];
        }

        // Chart Data - Test completion rate
        $completionRate = $totalTestSessions > 0 ? round(($completedTests / $totalTestSessions) * 100, 1) : 0;

        return view('admin.dashboard.index', compact(
            'totalUsers', 'totalAdmins', 'totalPICs', 'totalStaff', 'totalParticipants', 'activeUsers',
            'totalEvents', 'activeEvents', 'upcomingEvents', 'expiredEvents',
            'totalTestSessions', 'completedTests', 'ongoingTests', 'testsToday', 'testsThisWeek', 'testsThisMonth',
            'totalQuestionVersions', 'activeVersions', 'totalST30Questions', 'totalSJTQuestions',
            'totalResults', 'resultsWithPDF', 'emailsSent', 'pendingResults',
            'totalResendRequests', 'pendingResendRequests', 'approvedResendRequests', 'rejectedResendRequests',
            'recentTestSessions', 'recentResults', 'recentResendRequests',
            'testsPerDay', 'completionRate'
        ));
    }

    public function getStatistics(Request $request)
    {
        $period = $request->get('period', 'week'); // default week
        $labels = [];
        $data = [];

        if ($period === 'today') {
            // Data per Jam (00:00 - 23:00)
            $start = Carbon::today();
            for ($i = 0; $i <= 23; $i++) {
                $labels[] = sprintf('%02d:00', $i);
                $data[] = TestSession::whereBetween('created_at', [
                    $start->copy()->addHours($i),
                    $start->copy()->addHours($i)->endOfHour()
                ])->count();
            }

        } elseif ($period === 'month') {
            // Data per Tanggal dalam bulan ini
            $start = Carbon::now()->startOfMonth();
            $daysInMonth = $start->daysInMonth;

            for ($i = 1; $i <= $daysInMonth; $i++) {
                $date = $start->copy()->day($i);
                $labels[] = $date->format('d M');
                $data[] = TestSession::whereDate('created_at', $date)->count();
            }

        } else {
            // Default: Data 7 Hari Terakhir
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $labels[] = $date->format('M d');
                $data[] = TestSession::whereDate('created_at', $date)->count();
            }
        }

        return response()->json([
            'labels' => $labels,
            'data' => $data
        ]);
    }
}
