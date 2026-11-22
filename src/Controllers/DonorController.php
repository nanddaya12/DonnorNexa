<?php
declare(strict_types=1);

namespace DonorNexa\Controllers;

use DonorNexa\Core\Validator;
use DonorNexa\Exceptions\AppException;
use DonorNexa\Exceptions\NotFoundException;
use DonorNexa\Exceptions\ValidationException;
use DonorNexa\Repositories\CityRepository;
use DonorNexa\Repositories\DonorRepository;
use DonorNexa\Repositories\MatchRepository;
use DonorNexa\Repositories\NotificationRepository;
use DonorNexa\Repositories\UserRepository;
use DonorNexa\Services\EligibilityService;
use DonorNexa\Services\RequestService;
use DonorNexa\Services\UploadService;

class DonorController extends BaseController
{
    private function donor()
    {
        return (new DonorRepository())->find($this->user()->id) ?? throw new NotFoundException('Donor profile not found.');
    }

    public function dashboard(): void
    {
        $donor = $this->donor();
        $eligibility = new EligibilityService();
        $notes = new NotificationRepository();
        $this->render('donor/dashboard', [
            'donor'       => $donor,
            'eligibility' => $eligibility->check($donor),
            'cooldown'    => $eligibility->cooldownDays(),
            'matches'     => (new MatchRepository())->forDonor($donor->user_id),
            'notes'       => $notes->recent($donor->user_id, 5),
            'count'       => (new DonorRepository())->donationCount($donor->user_id),
        ], 'Donor dashboard');
        $notes->markAllRead($donor->user_id);
    }

    public function respond(): void
    {
        $matchId = (int) $this->input('match_id');
        $service = new RequestService();
        try {
            if ($this->input('decision') === 'accept') {
                $service->accept($matchId, $this->user()->id);
                $this->flash('success', 'Thank you! You accepted the request. The hospital can now contact you.');
            } else {
                $service->decline($matchId, $this->user()->id, $this->input('reason'));
                $this->flash('info', 'Request declined. Thanks for letting us know.');
            }
        } catch (AppException $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->go('donor/dashboard');
    }

    public function profile(): void
    {
        $this->render('donor/profile', [
            'donor'  => $this->donor(),
            'cities' => (new CityRepository())->allCities(),
            'errors' => $this->pullErrors(),
        ], 'My profile');
    }

    public function updateProfile(): void
    {
        $userId = $this->user()->id;
        $current = $this->donor();
        $v = Validator::make($_POST, [
            'name'        => 'required|name',
            'blood_group' => 'required|in:' . implode(',', blood_groups()),
            'dob'         => 'required|date|dob',
            'weight'      => 'required|between:30,250',
            'city_id'     => 'required|int',
            'phone'       => 'required|mobile',
            'cnic'        => 'cnic',
        ], ['dob' => 'Date of birth', 'city_id' => 'City', 'blood_group' => 'Blood group']);

        $donors = new DonorRepository();
        if (!$v->fails()) {
            $c = $v->clean();
            if (!(new CityRepository())->exists((int) $c['city_id'])) {
                $v->add('city_id', 'Choose a valid city.');
            }
            if ($c['cnic'] !== '' && $donors->cnicTaken($c['cnic'], $userId)) {
                $v->add('cnic', 'This CNIC is already registered.');
            }
        }

        $uploads = new UploadService();
        $newPhoto = null;
        $file = $_FILES['photo'] ?? null;
        if (!$v->fails() && $file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $newPhoto = $uploads->savePhoto($file);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $f => $m) {
                    $v->add($f, $m);
                }
            }
        }
        if ($v->fails()) {
            $this->failValidation(new ValidationException($v->errors()), 'donor/profile');
            return;
        }

        $c = $v->clean();
        \DonorNexa\Core\Database::transaction(function () use ($userId, $c, $newPhoto, $donors) {
            (new UserRepository())->updateName($userId, $c['name']);
            $donors->update($userId, $c, $newPhoto);
        });
        if ($newPhoto !== null) {
            $uploads->deletePhoto($current->photo);
        }
        $this->flash('success', 'Profile updated.');
        $this->go('donor/profile');
    }

    public function history(): void
    {
        $donors = new DonorRepository();
        $id = $this->user()->id;
        $count = $donors->donationCount($id);
        $this->render('donor/history', [
            'donor'   => $this->donor(),
            'history' => $donors->history($id),
            'count'   => $count,
            'badges'  => $this->badges($count),
        ], 'Donation history');
    }

    /** @return array<int,array{label:string,earned:bool}> */
    private function badges(int $count): array
    {
        $out = [];
        foreach ([1 => '1st donation', 5 => '5th donation', 10 => '10th donation'] as $need => $label) {
            $out[] = ['label' => $label, 'earned' => $count >= $need, 'need' => $need];
        }
        return $out;
    }

    public function toggleAvailability(): void
    {
        (new DonorRepository())->setAvailability($this->user()->id, isset($_POST['available']));
        $this->go('donor/dashboard');
    }
}
