<?php
declare(strict_types=1);

namespace DonorNexa\Controllers;

use DonorNexa\Core\Validator;
use DonorNexa\Exceptions\AppException;
use DonorNexa\Exceptions\ConflictException;
use DonorNexa\Exceptions\NotFoundException;
use DonorNexa\Repositories\AuditRepository;
use DonorNexa\Repositories\CityRepository;
use DonorNexa\Repositories\HospitalRepository;
use DonorNexa\Repositories\NotificationRepository;
use DonorNexa\Repositories\RequestRepository;
use DonorNexa\Repositories\SettingRepository;
use DonorNexa\Repositories\UserRepository;
use DonorNexa\Services\ReportService;

class AdminController extends BaseController
{
    private function audit(): AuditRepository
    {
        return new AuditRepository();
    }

    public function dashboard(): void
    {
        $this->render('admin/dashboard', ['summary' => (new ReportService())->adminSummary()], 'Admin dashboard');
    }

    public function hospitals(): void
    {
        $filters = $this->filters(['q', 'status', 'sort', 'dir']);
        $this->render('admin/hospitals', [
            'list' => (new HospitalRepository())->paginate($filters, $this->page()),
            'filters' => $filters,
        ], 'Hospitals');
    }

    public function verifyHospital(): void
    {
        $id = (int) $this->input('id');
        $decision = $this->input('decision');
        $hospitals = new HospitalRepository();
        try {
            if (!in_array($decision, ['verified', 'rejected', 'pending'], true)) {
                throw new ConflictException('Unknown decision.');
            }
            $hospital = $hospitals->find($id) ?? throw new NotFoundException('Hospital not found.');
            $old = $hospital->verification_status;
            $hospitals->setVerification($id, $decision);
            $this->audit()->log($this->user()->id, 'hospital_' . $decision, 'hospitals:' . $id, $old, $decision);
            (new NotificationRepository())->add($id, $decision === 'verified'
                ? 'Your hospital account has been verified. You can now raise blood requests.'
                : "Your hospital verification status is now: $decision.");
            $this->flash('success', "{$hospital->hospital_name} marked as $decision.");
        } catch (AppException $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->go('admin/hospitals', $this->filters(['q', 'status', 'sort', 'dir', 'page']));
    }

    /** Admin-only download of the uploaded licence PDF (files live outside the public folder). */
    public function licence(): void
    {
        $hospital = (new HospitalRepository())->find((int) $this->input('id')) ?? throw new NotFoundException('Hospital not found.');
        $file = (string) $hospital->licence_file;
        $path = BASE_PATH . '/storage/licences/' . $file;
        if (!preg_match('/^[a-zA-Z0-9._-]+\.pdf$/', $file) || !is_file($path)) {
            throw new NotFoundException('No licence document is stored for this hospital.');
        }
        $this->audit()->log($this->user()->id, 'licence_viewed', 'hospitals:' . $hospital->user_id, null, null);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="licence-' . (int) $hospital->user_id . '.pdf"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public function users(): void
    {
        $filters = $this->filters(['q', 'role', 'status', 'sort', 'dir']);
        $this->render('admin/users', [
            'list' => (new UserRepository())->paginate($filters, $this->page()),
            'filters' => $filters,
        ], 'Users');
    }

    public function toggleBlock(): void
    {
        $id = (int) $this->input('id');
        $users = new UserRepository();
        try {
            $target = $users->find($id) ?? throw new NotFoundException('User not found.');
            if ($target->isAdmin()) {
                throw new ConflictException('Administrator accounts cannot be blocked here.');
            }
            $new = $target->status === 'active' ? 'blocked' : 'active';
            $users->setStatus($id, $new);
            if ($new === 'blocked') {
                $users->deleteRememberTokens($id);
            }
            $this->audit()->log($this->user()->id, 'user_' . $new, 'users:' . $id, $target->status, $new);
            $this->flash('success', "{$target->name} is now $new.");
        } catch (AppException $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->go('admin/users', $this->filters(['q', 'role', 'status', 'sort', 'dir', 'page']));
    }

    public function requests(): void
    {
        $filters = $this->filters(['q', 'status', 'urgency', 'blood_group', 'sort', 'dir']);
        $this->render('admin/requests', [
            'list' => (new RequestRepository())->paginate($filters, $this->page()),
            'filters' => $filters,
        ], 'All requests');
    }

    public function auditLog(): void
    {
        $filters = $this->filters(['q', 'action', 'sort', 'dir']);
        $repo = $this->audit();
        $this->render('admin/audit', [
            'list' => $repo->paginate($filters, $this->page()),
            'filters' => $filters,
            'actions' => $repo->actions(),
        ], 'Audit log');
    }

    public function cities(): void
    {
        $this->render('admin/cities', ['cities' => (new CityRepository())->withUsage()], 'Cities');
    }

    public function addCity(): void
    {
        $v = Validator::make($_POST, ['city_name' => 'required|name|max:80', 'province' => 'required|name|max:40'], ['city_name' => 'City', 'province' => 'Province']);
        $cities = new CityRepository();
        if (!$v->fails() && $cities->nameTaken($v->clean()['city_name'])) {
            $v->add('city_name', 'That city already exists.');
        }
        if ($v->fails()) {
            $this->flash('error', implode(' ', $v->errors()));
        } else {
            $c = $v->clean();
            $id = $cities->add($c['city_name'], $c['province']);
            $this->audit()->log($this->user()->id, 'city_added', 'cities:' . $id, null, $c['city_name']);
            $this->flash('success', 'City added.');
        }
        $this->go('admin/cities');
    }

    public function deleteCity(): void
    {
        $id = (int) $this->input('id');
        try {
            $deleted = (new CityRepository())->delete($id);
            if ($deleted) {
                $this->audit()->log($this->user()->id, 'city_deleted', 'cities:' . $id, null, null);
                $this->flash('success', 'City deleted.');
            }
        } catch (\PDOException $e) {
            // ON DELETE RESTRICT: the city is still used by donors or hospitals
            $this->flash('error', 'This city is in use by donors or hospitals and cannot be deleted.');
        }
        $this->go('admin/cities');
    }

    public function settings(): void
    {
        $this->render('admin/settings', ['settings' => (new SettingRepository())->allSettings(), 'errors' => $this->pullErrors()], 'System settings');
    }

    public function saveSettings(): void
    {
        $limits = ['cooldown_days' => [30, 365], 'top_n' => [1, 50], 'response_hours' => [1, 72]];
        $repo = new SettingRepository();
        foreach ($limits as $key => [$min, $max]) {
            $raw = $_POST[$key] ?? '';
            if (filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]) === false) {
                $this->flash('error', ucfirst(str_replace('_', ' ', $key)) . " must be a whole number between $min and $max.");
                $this->go('admin/settings');
                return;
            }
        }
        foreach ($limits as $key => $_) {
            $old = $repo->get($key);
            $repo->set($key, (int) $_POST[$key]);
            if ($old !== (int) $_POST[$key]) {
                $this->audit()->log($this->user()->id, 'setting_changed', 'settings:' . $key, (string) $old, (string) (int) $_POST[$key]);
            }
        }
        $this->flash('success', 'Settings saved.');
        $this->go('admin/settings');
    }

    public function export(): void
    {
        $type = $this->input('type');
        $this->audit()->log($this->user()->id, 'report_exported', 'reports:' . $type, null, null);
        (new ReportService())->exportCsv($type);
    }
}
