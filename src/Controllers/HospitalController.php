<?php
declare(strict_types=1);

namespace DonorNexa\Controllers;

use DonorNexa\Exceptions\AppException;
use DonorNexa\Exceptions\ForbiddenException;
use DonorNexa\Exceptions\NotFoundException;
use DonorNexa\Exceptions\ValidationException;
use DonorNexa\Repositories\AuditRepository;
use DonorNexa\Repositories\CityRepository;
use DonorNexa\Repositories\HospitalRepository;
use DonorNexa\Repositories\MatchRepository;
use DonorNexa\Repositories\RequestRepository;
use DonorNexa\Services\MatchingService;
use DonorNexa\Services\RequestService;

class HospitalController extends BaseController
{
    private function hospital()
    {
        return (new HospitalRepository())->find($this->user()->id) ?? throw new NotFoundException('Hospital profile not found.');
    }

    /** Load a request and make sure it belongs to the logged-in hospital. */
    private function ownRequest(int $id)
    {
        $request = (new RequestRepository())->find($id) ?? throw new NotFoundException('Request not found.');
        if ((int) $request->hospital_id !== $this->user()->id) {
            throw new ForbiddenException('That request belongs to another hospital.');
        }
        return $request;
    }

    public function dashboard(): void
    {
        $hospital = $this->hospital();
        $filters = $this->filters(['status', 'urgency', 'sort', 'dir']);
        $list = (new RequestRepository())->paginate($filters, $this->page(), $hospital->user_id);
        $this->render('hospital/dashboard', [
            'hospital' => $hospital,
            'list'     => $list,
            'filters'  => $filters,
            'inventory' => (new HospitalRepository())->inventory($hospital->user_id),
            'activeCount' => (new RequestRepository())->openForHospital($hospital->user_id),
        ], 'Hospital dashboard');
    }

    public function createForm(): void
    {
        $this->render('hospital/request_form', ['hospital' => $this->hospital(), 'errors' => $this->pullErrors()], 'New blood request');
    }

    public function create(): void
    {
        try {
            $id = (new RequestService())->create($this->user()->id, $_POST);
        } catch (ValidationException $e) {
            $this->failValidation($e, 'hospital/request/new');
            return;
        } catch (AppException $e) {
            \DonorNexa\Core\Session::flashOld($_POST);
            $this->flash('error', $e->getMessage());
            $this->go('hospital/request/new');
            return;
        }
        $this->flash('success', 'Request raised. The best-matched donors have been notified.');
        $this->go('hospital/request', ['id' => $id]);
    }

    public function show(): void
    {
        $request = $this->ownRequest((int) $this->input('id'));
        $matches = (new MatchRepository())->forRequest((int) $request->id);

        // Privacy: a phone number is revealed only after the donor accepted, and each reveal is audited once
        $audit = new AuditRepository();
        foreach ($matches as &$m) {
            $m['reveal'] = in_array($m['status'], ['accepted', 'donated'], true);
            if ($m['reveal'] && !$audit->exists('contact_revealed', 'request_matches:' . $m['id'])) {
                $audit->log($this->user()->id, 'contact_revealed', 'request_matches:' . $m['id'], 'masked', 'revealed');
            }
        }
        unset($m);

        $this->render('hospital/request_detail', [
            'request' => $request,
            'matches' => $matches,
        ], 'Request #' . $request->id);
    }

    public function notifyMore(): void
    {
        $request = $this->ownRequest((int) $this->input('id'));
        try {
            if (!$request->isOpen()) {
                throw new \DonorNexa\Exceptions\ConflictException('Only open requests can notify more donors.');
            }
            $count = (new MatchingService())->notifyBatch($request);
            $this->flash($count > 0 ? 'success' : 'info', $count > 0 ? "$count more donors notified." : 'No further eligible donors were found for this request.');
        } catch (AppException $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->go('hospital/request', ['id' => $request->id]);
    }

    public function donated(): void
    {
        $request = $this->ownRequest((int) $this->input('id'));
        try {
            (new RequestService())->markDonated((int) $this->input('match_id'), $this->user()->id);
            $this->flash('success', 'Donation recorded and your inventory was updated.');
        } catch (AppException $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->go('hospital/request', ['id' => $request->id]);
    }

    public function cancel(): void
    {
        $request = $this->ownRequest((int) $this->input('id'));
        try {
            (new RequestService())->cancel((int) $request->id, $this->user()->id);
            $this->flash('info', 'Request cancelled. Repeated cancellations lower your trust score.');
        } catch (AppException $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->go('hospital/request', ['id' => $request->id]);
    }

    public function close(): void
    {
        $request = $this->ownRequest((int) $this->input('id'));
        try {
            (new RequestService())->close((int) $request->id, $this->user()->id);
            $this->flash('success', 'Request closed.');
        } catch (AppException $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->go('hospital/request', ['id' => $request->id]);
    }

    public function inventory(): void
    {
        $this->render('hospital/inventory', [
            'hospital'  => $this->hospital(),
            'inventory' => (new HospitalRepository())->inventory($this->user()->id),
        ], 'Blood inventory');
    }

    public function saveInventory(): void
    {
        $repo = new HospitalRepository();
        $id = $this->user()->id;
        foreach (blood_groups() as $group) {
            $raw = $_POST['units'][$group] ?? '';
            if ($raw === '' || filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 9999]]) === false) {
                $this->flash('error', "Units for $group must be a whole number between 0 and 9999.");
                $this->go('hospital/inventory');
                return;
            }
        }
        foreach (blood_groups() as $group) {
            $repo->setInventory($id, $group, (int) $_POST['units'][$group]);
        }
        (new AuditRepository())->log($id, 'inventory_updated', 'hospitals:' . $id, null, 'manual update');
        $this->flash('success', 'Inventory saved.');
        $this->go('hospital/inventory');
    }
}
