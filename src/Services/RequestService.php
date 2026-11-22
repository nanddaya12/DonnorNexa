<?php
declare(strict_types=1);

namespace DonorNexa\Services;

use DateTimeImmutable;
use DonorNexa\Core\Config;
use DonorNexa\Core\Database;
use DonorNexa\Core\Validator;
use DonorNexa\Exceptions\ConflictException;
use DonorNexa\Exceptions\ForbiddenException;
use DonorNexa\Exceptions\NotFoundException;
use DonorNexa\Exceptions\ValidationException;
use DonorNexa\Repositories\DonorRepository;
use DonorNexa\Repositories\HospitalRepository;
use DonorNexa\Repositories\MatchRepository;
use DonorNexa\Repositories\NotificationRepository;
use DonorNexa\Repositories\RequestRepository;
use DonorNexa\Repositories\SettingRepository;
use DonorNexa\Traits\Auditable;
use PDOException;

class RequestService
{
    use Auditable;

    private RequestRepository $requests;
    private MatchRepository $matches;
    private HospitalRepository $hospitals;
    private NotificationRepository $notes;

    public function __construct()
    {
        $this->requests = new RequestRepository();
        $this->matches = new MatchRepository();
        $this->hospitals = new HospitalRepository();
        $this->notes = new NotificationRepository();
    }

    public function create(int $hospitalId, array $input): int
    {
        $data = Validator::make($input, [
            'blood_group'  => 'required|in:' . implode(',', blood_groups()),
            'units_needed' => 'required|int|between:1,10',
            'urgency'      => 'required|in:Normal,Urgent,Critical',
            'required_by'  => 'required|datetime',
            'ward_notes'   => 'max:255',
        ], ['required_by' => 'Required-by time', 'units_needed' => 'Units needed'])->validated();

        $deadline = new DateTimeImmutable($data['required_by']);
        $now = new DateTimeImmutable();
        if ($deadline <= $now->modify('+10 minutes')) {
            throw new ValidationException(['required_by' => 'Required-by time must be at least 10 minutes from now.']);
        }
        if ($deadline > $now->modify('+30 days')) {
            throw new ValidationException(['required_by' => 'Required-by time cannot be more than 30 days ahead.']);
        }
        $data['required_by'] = $deadline->format('Y-m-d H:i:s');

        $hospital = $this->hospitals->find($hospitalId) ?? throw new NotFoundException('Hospital not found.');
        if ($hospital->verification_status !== 'verified') {
            throw new ForbiddenException('Your hospital must be verified by the administrator before raising requests.');
        }
        if ((int) $hospital->trust_score < Config::get('app.min_trust_to_post')) {
            throw new ForbiddenException('Your trust score is too low to raise new requests. Please contact the administrator.');
        }

        return (int) Database::transaction(function () use ($hospitalId, $data) {
            $this->db()->prepare('SELECT user_id FROM hospitals WHERE user_id = ? FOR UPDATE')->execute([$hospitalId]);
            $limit = (int) Config::get('app.max_open_critical');
            if ($data['urgency'] === 'Critical' && $this->requests->countOpenCritical($hospitalId) >= $limit) {
                throw new ConflictException("You already have $limit open Critical requests. Close or wait for one before raising another.");
            }
            try {
                $id = $this->requests->create($hospitalId, $data);
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'at most 3 open Critical')) {
                    throw new ConflictException('You already have the maximum number of open Critical requests.');
                }
                throw $e;
            }
            $this->audit('request_created', 'blood_requests:' . $id, null, [
                'blood_group' => $data['blood_group'], 'units' => $data['units_needed'], 'urgency' => $data['urgency'],
            ]);
            $request = $this->requests->find($id);
            (new MatchingService())->notifyBatch($request);
            return $id;
        });
    }

    private function db(): \PDO
    {
        return Database::connection();
    }

    public function accept(int $matchId, int $donorId): void
    {
        $donor = (new DonorRepository())->find($donorId) ?? throw new NotFoundException('Donor profile not found.');
        $eligibility = (new EligibilityService())->check($donor);
        if (!$eligibility['eligible']) {
            throw new ConflictException(implode(' ', $eligibility['reasons']));
        }

        Database::transaction(function () use ($matchId, $donorId) {
            $match = $this->matches->lockForDonor($matchId, $donorId) ?? throw new NotFoundException('Request not found.');
            $request = $this->requests->lockById((int) $match['request_id']);

            if ($match['status'] !== 'notified') {
                throw new ConflictException('You have already responded to this request.');
            }
            if ($request['status'] !== 'open' || new DateTimeImmutable($request['required_by']) <= new DateTimeImmutable()) {
                throw new ConflictException('This request is no longer open.');
            }
            if ((int) $request['units_fulfilled'] >= (int) $request['units_needed']) {
                throw new ConflictException('Sorry, other donors have already covered every unit needed.');
            }

            $this->matches->setStatus($matchId, 'accepted');
            $this->requests->allocateUnit((int) $request['id']);
            $this->audit('match_accepted', 'request_matches:' . $matchId, 'notified', 'accepted', $donorId);

            $filled = (int) $request['units_fulfilled'] + 1 >= (int) $request['units_needed'];
            if ($filled) {
                $this->audit('request_status', 'blood_requests:' . $request['id'], 'open', 'fulfilled', $donorId);
                $this->releaseWaiting((int) $request['id']);
            }
            $this->notes->add((int) $request['hospital_id'], "A donor accepted your request #{$request['id']}. Contact details are now visible.");
        });
    }

    public function decline(int $matchId, int $donorId, string $reason): void
    {
        $reason = mb_substr(trim($reason), 0, 255);
        Database::transaction(function () use ($matchId, $donorId, $reason) {
            $match = $this->matches->lockForDonor($matchId, $donorId) ?? throw new NotFoundException('Request not found.');
            if ($match['status'] !== 'notified') {
                throw new ConflictException('You have already responded to this request.');
            }
            $this->matches->setStatus($matchId, 'declined', $reason !== '' ? $reason : null);
            $this->audit('match_declined', 'request_matches:' . $matchId, 'notified', 'declined', $donorId);
        });
    }

    public function markDonated(int $matchId, int $hospitalId): void
    {
        Database::transaction(function () use ($matchId, $hospitalId) {
            $match = $this->matches->lockWithRequest($matchId) ?? throw new NotFoundException('Match not found.');
            if ((int) $match['hospital_id'] !== $hospitalId) {
                throw new ForbiddenException();
            }
            if ($match['status'] !== 'accepted') {
                throw new ConflictException('Only accepted donors can be marked as donated.');
            }
            $request = $this->requests->lockById((int) $match['request_id']);

            $db = $this->db();
            $db->prepare('INSERT INTO donations (match_id, units, donated_on, verified_by) VALUES (?, 1, CURDATE(), ?)')
               ->execute([$matchId, $hospitalId]);
            $this->matches->setStatus($matchId, 'donated', null, false);
            $this->hospitals->addInventory($hospitalId, $request['blood_group'], 1);
            $this->audit('donation_completed', 'request_matches:' . $matchId, 'accepted', 'donated', $hospitalId);
            $this->notes->add((int) $match['donor_id'], 'Thank you! Your donation was recorded. You can donate again after the cool-down period.');

            if ($request['status'] === 'fulfilled' && $this->matches->acceptedUnfinished((int) $request['id']) === 0) {
                $this->requests->setStatus((int) $request['id'], 'closed');
                $this->audit('request_status', 'blood_requests:' . $request['id'], 'fulfilled', 'closed', $hospitalId);
            }
        });
    }

    public function cancel(int $requestId, int $hospitalId): void
    {
        $this->finish($requestId, $hospitalId, 'cancelled', true);
    }

    public function close(int $requestId, int $hospitalId): void
    {
        $this->finish($requestId, $hospitalId, 'closed', false);
    }

    private function finish(int $requestId, int $hospitalId, string $newStatus, bool $penalty): void
    {
        Database::transaction(function () use ($requestId, $hospitalId, $newStatus, $penalty) {
            $request = $this->requests->lockById($requestId) ?? throw new NotFoundException('Request not found.');
            if ((int) $request['hospital_id'] !== $hospitalId) {
                throw new ForbiddenException();
            }
            if (!in_array($request['status'], ['open', 'fulfilled'], true)) {
                throw new ConflictException('This request is already ' . $request['status'] . '.');
            }
            $this->requests->setStatus($requestId, $newStatus);
            $this->audit('request_status', 'blood_requests:' . $requestId, $request['status'], $newStatus, $hospitalId);
            $this->releaseWaiting($requestId);
            if ($penalty) {
                $this->hospitals->adjustTrust($hospitalId, -(int) Config::get('app.cancel_penalty'));
                $this->audit('trust_decreased', 'hospitals:' . $hospitalId, null, '-' . Config::get('app.cancel_penalty'), $hospitalId);
            }
        });
    }

    private function releaseWaiting(int $requestId): void
    {
        $rows = $this->db()->prepare("SELECT id FROM request_matches WHERE request_id = ? AND status = 'notified'");
        $rows->execute([$requestId]);
        foreach ($rows->fetchAll(\PDO::FETCH_COLUMN) as $id) {
            $this->matches->setStatus((int) $id, 'no_response', null, false);
            $this->audit('match_status', 'request_matches:' . $id, 'notified', 'no_response');
        }
    }
}

class ExpiryService
{
    use Auditable;

    public function run(): array
    {
        return Database::transaction(function (): array {
            $db = Database::connection();
            $requests = new RequestRepository();
            $matches = new MatchRepository();
            $notes = new NotificationRepository();

            $late = $db->query("SELECT id, hospital_id FROM blood_requests WHERE status = 'open' AND required_by < NOW()")->fetchAll();
            foreach ($late as $row) {
                $requests->setStatus((int) $row['id'], 'expired');
                $this->auditSystem('request_status', 'blood_requests:' . $row['id'], 'open', 'expired');
                $notes->add((int) $row['hospital_id'], "Request #{$row['id']} passed its required-by time and has expired.");
            }

            $stale = $matches->staleNotifiedIds((new SettingRepository())->get('response_hours', 12));
            foreach ($stale as $id) {
                $matches->setStatus($id, 'no_response', null, false);
                $this->auditSystem('match_status', 'request_matches:' . $id, 'notified', 'no_response');
            }
            return ['expired' => count($late), 'no_response' => count($stale)];
        });
    }
}
