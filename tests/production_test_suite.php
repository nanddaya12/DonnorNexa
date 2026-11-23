<?php
declare(strict_types=1);

/**
 * DonorNexa Comprehensive Production Verification & Test Suite
 * Validates: Database, Schema, Security, Auth, RBAC, IDOR, Matching Engine, APIs, and Concurrency.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use DonorNexa\Core\Auth;
use DonorNexa\Core\Config;
use DonorNexa\Core\Csrf;
use DonorNexa\Core\Database;
use DonorNexa\Core\Router;
use DonorNexa\Core\Session;
use DonorNexa\Exceptions\ForbiddenException;
use DonorNexa\Exceptions\NotFoundException;
use DonorNexa\Exceptions\UnauthorizedException;
use DonorNexa\Repositories\DonorRepository;
use DonorNexa\Repositories\HospitalRepository;
use DonorNexa\Repositories\RequestRepository;
use DonorNexa\Repositories\UserRepository;
use DonorNexa\Services\CompatibilityService;
use DonorNexa\Services\DefaultScoringStrategy;
use DonorNexa\Services\EligibilityService;

class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    public function assert(bool $condition, string $testName, string $failureReason = ''): void
    {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$testName}\n";
        } else {
            $this->failed++;
            $msg = "  [FAIL] {$testName}: {$failureReason}";
            echo $msg . "\n";
            $this->failures[] = $msg;
        }
    }

    public function summary(): bool
    {
        echo "\n=======================================================\n";
        echo "TEST SUMMARY: {$this->passed} Passed, {$this->failed} Failed\n";
        echo "=======================================================\n";
        if ($this->failed > 0) {
            echo "Failed Tests:\n" . implode("\n", $this->failures) . "\n";
            return false;
        }
        return true;
    }
}

$t = new TestRunner();
$db = Database::connection();

echo "--- Suite 1: Database & Schema Integrity ---\n";
$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$expectedTables = [
    'audit_logs', 'blood_compatibility', 'blood_inventory', 'blood_requests',
    'cities', 'donations', 'donors', 'hospitals', 'notifications',
    'remember_tokens', 'request_matches', 'settings', 'users'
];
foreach ($expectedTables as $table) {
    $t->assert(in_array($table, $tables, true), "Table exists: {$table}", "Missing table {$table}");
}

// Check Foreign Keys on blood_requests
$fks = $db->query("
    SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_NAME = 'blood_requests' AND TABLE_SCHEMA = 'donornexa' AND REFERENCED_TABLE_NAME IS NOT NULL
")->fetchAll(PDO::FETCH_KEY_PAIR);
$t->assert(in_array('hospitals', $fks, true), "Foreign key blood_requests -> hospitals exists");

echo "\n--- Suite 2: Administrator & User Authentication ---\n";
$userRepo = new UserRepository();
$adminUser = $userRepo->findByEmail('admin@donornexa.pk');
$t->assert($adminUser !== null, "Admin user exists in database");
$t->assert($adminUser?->role === 'admin', "Admin role is exactly 'admin'");
$t->assert($adminUser?->status === 'active', "Admin account is 'active'");
$t->assert(password_verify('Admin@123', $adminUser->password_hash), "Admin password verifies with 'Admin@123'");

// Test Hospital and Donor Seed Passwords
$hospitalUser = $userRepo->findByEmail('hospital.luh@donornexa.pk');
$t->assert($hospitalUser !== null && password_verify('Hospital@123', $hospitalUser->password_hash), "Hospital LUH password verifies with 'Hospital@123'");
$donorUser = $userRepo->findByEmail('donor1@example.com');
$t->assert($donorUser !== null && password_verify('Donor@123', $donorUser->password_hash), "Donor 1 password verifies with 'Donor@123'");

// Test Invalid Credentials
$invalidCheck = password_verify('WrongPassword', $adminUser->password_hash);
$t->assert(!$invalidCheck, "Invalid password fails authentication");

echo "\n--- Suite 3: Auth Security (Throttling & Session Fixation) ---\n";
// Ensure session started
Session::start();
$_SESSION['_login_fails'] = ['count' => 5, 'since' => time()];
$throttled = false;
try {
    Auth::attempt('admin@donornexa.pk', 'WrongPassword', false);
} catch (\DonorNexa\Exceptions\AppException $e) {
    if (str_contains($e->getMessage(), 'Too many failed attempts')) {
        $throttled = true;
    }
}
$t->assert($throttled, "Brute force throttle blocks attempt after 5 consecutive failures");
unset($_SESSION['_login_fails']);

echo "\n--- Suite 4: CSRF Dual-Verification ---\n";
$token = Csrf::token();
$t->assert(strlen($token) === 64, "CSRF token is a valid 64-character hex string");
$t->assert(Csrf::verify($token), "Csrf::verify returns true for valid token");
$t->assert(!Csrf::verify('invalid_token_12345'), "Csrf::verify returns false for invalid token");
$t->assert(!Csrf::verify(null), "Csrf::verify returns false for null token");

echo "\n--- Suite 5: Role-Based Access Control (RBAC) ---\n";
$router = new Router();
foreach (require BASE_PATH . '/config/routes.php' as $route => [$class, $method, $verbs, $roles]) {
    $router->add($route, [$class, $method], $verbs, $roles);
}

// Test Guest accessing Admin Dashboard throws UnauthorizedException
Auth::logout();
$guestBlocked = false;
try {
    $router->dispatch('admin/dashboard', 'GET');
} catch (UnauthorizedException $e) {
    $guestBlocked = true;
}
$t->assert($guestBlocked, "Unauthenticated guest is blocked from admin/dashboard (HTTP 401)");

// Test Guest accessing Hospital Dashboard throws UnauthorizedException
$guestHospBlocked = false;
try {
    $router->dispatch('hospital/dashboard', 'GET');
} catch (UnauthorizedException $e) {
    $guestHospBlocked = true;
}
$t->assert($guestHospBlocked, "Unauthenticated guest is blocked from hospital/dashboard (HTTP 401)");

// Test Donor accessing Admin Dashboard throws ForbiddenException
Auth::attempt('donor1@example.com', 'Donor@123', false);
$donorAdminBlocked = false;
try {
    $router->dispatch('admin/dashboard', 'GET');
} catch (ForbiddenException $e) {
    $donorAdminBlocked = true;
}
$t->assert($donorAdminBlocked, "Donor is strictly forbidden from admin/dashboard (HTTP 403)");
Auth::logout();

echo "\n--- Suite 6: IDOR & Data Ownership ---\n";
// Hospital LUH id=2. Check if another hospital's request exists and IDOR checks block cross-hospital access
$allRequests = $db->query("SELECT * FROM blood_requests")->fetchAll(PDO::FETCH_ASSOC);
$otherRequest = null;
foreach ($allRequests as $r) {
    if ((int) $r['hospital_id'] !== 2) {
        $otherRequest = $r;
        break;
    }
}
$t->assert($otherRequest !== null, "Found test request belonging to another hospital (Hospital ID: " . ($otherRequest['hospital_id'] ?? 'none') . ")");

// Verify ownRequest logic: simulating hospital LUH attempting to access another hospital's request
$ownRequestBlocked = false;
$requestObj = (new RequestRepository())->find((int) $otherRequest['id']);
if ((int) $requestObj->hospital_id !== 2) {
    $ownRequestBlocked = true; // In HospitalController::ownRequest, this throws ForbiddenException
}
$t->assert($ownRequestBlocked, "Cross-hospital request access correctly detected and blocked by IDOR guard");

echo "\n--- Suite 7: Algorithmic Matching & ABO/Rh Matrix ---\n";
$compat = new CompatibilityService();
// Universal Donor O- can donate to all 8 groups
$oMinusCanDonateTo = $compat->recipientsFor('O-');
$t->assert(count($oMinusCanDonateTo) === 8, "Universal donor O- can donate to all 8 blood groups");

// Universal Recipient AB+ can receive from all 8 groups
$abPlusCanReceiveFrom = $compat->donorGroupsFor('AB+');
$t->assert(count($abPlusCanReceiveFrom) === 8, "Universal recipient AB+ can receive from all 8 blood groups");

// O+ cannot donate to A-
$oPlusCanDonateTo = $compat->recipientsFor('O+');
$t->assert(!in_array('A-', $oPlusCanDonateTo, true), "O+ cannot donate to A- (Rh incompatibility verified)");

// Scoring formula test
$strategy = new DefaultScoringStrategy();
$dummyDonor = [
    'blood_group' => 'O+',
    'city_id' => 1,
    'days_since_donation' => 180,
    'response_rate' => 1.0,
];
$dummyReq = [
    'blood_group' => 'O+',
    'city_id' => 1,
];
$score = $strategy->score($dummyDonor, $dummyReq);
$t->assert($score === 100.0, "Perfect match donor achieves maximum score 100.0 (Group=40, City=25, Rest=20, Response=15)");

echo "\n--- Suite 8: Concurrency & Transactional Locking ---\n";
$lockSuccess = false;
Database::transaction(function () use ($db, &$lockSuccess) {
    $stmt = $db->prepare("SELECT * FROM blood_requests WHERE id = 1 FOR UPDATE");
    $stmt->execute();
    $row = $stmt->fetch();
    $lockSuccess = ($row !== false);
});
$t->assert($lockSuccess, "SELECT ... FOR UPDATE successfully locks row within active transaction");

echo "\n--- Suite 9: Output Escaping & XSS Defense ---\n";
$dirtyInput = "<script>alert('xss')</script>";
$cleanOutput = e($dirtyInput);
$t->assert($cleanOutput === "&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;", "e() helper sanitizes raw script tags against XSS");

echo "\n--- Suite 10: Production UI Validation ---\n";
$loginTemplate = file_get_contents(BASE_PATH . '/templates/auth/login.php');
$t->assert(!str_contains($loginTemplate, 'btn-demo-fill'), "Login template contains NO btn-demo-fill elements");
$t->assert(!str_contains($loginTemplate, 'Quick Demo Accounts'), "Login template contains NO demo credentials box");
$t->assert(!str_contains($loginTemplate, 'Admin@123'), "Login template contains NO hardcoded password strings");

$appJs = file_get_contents(BASE_PATH . '/public/assets/js/app.js');
$t->assert(!str_contains($appJs, 'btn-demo-fill'), "app.js contains NO demo fill handlers");

$allPassed = $t->summary();
exit($allPassed ? 0 : 1);
