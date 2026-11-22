<?php
declare(strict_types=1);

namespace DonorNexa\Controllers;

use DonorNexa\Core\Auth;
use DonorNexa\Core\Validator;
use DonorNexa\Exceptions\AppException;
use DonorNexa\Exceptions\ValidationException;
use DonorNexa\Repositories\CityRepository;
use DonorNexa\Services\RegistrationService;

class AuthController extends BaseController
{
    private function guestOnly(): void
    {
        if ($user = Auth::user()) {
            $this->go($user->homeRoute());
        }
    }

    public function showLogin(): void
    {
        $this->guestOnly();
        $this->render('auth/login', ['errors' => $this->pullErrors()], 'Log in');
    }

    public function login(): void
    {
        $v = Validator::make($_POST, ['email' => 'required|email', 'password' => 'required']);
        try {
            $data = $v->validated();
            $user = Auth::attempt($data['email'], (string) ($_POST['password'] ?? ''), isset($_POST['remember']));
        } catch (ValidationException $e) {
            $this->failValidation($e, 'login');
            return;
        } catch (AppException $e) {
            \DonorNexa\Core\Session::flashOld($_POST);
            $this->flash('error', $e->getMessage());
            $this->go('login');
            return;
        }
        $this->flash('success', 'Welcome back, ' . $user->name . '.');
        $this->go($user->homeRoute());
    }

    public function showRegisterDonor(): void
    {
        $this->guestOnly();
        $this->render('auth/register_donor', [
            'cities' => (new CityRepository())->allCities(),
            'errors' => $this->pullErrors(),
        ], 'Become a donor');
    }

    public function registerDonor(): void
    {
        try {
            (new RegistrationService())->registerDonor($_POST, $_FILES['photo'] ?? null);
        } catch (ValidationException $e) {
            $this->failValidation($e, 'register/donor');
            return;
        }
        $this->flash('success', 'Registration complete. Please log in.');
        $this->go('login');
    }

    public function showRegisterHospital(): void
    {
        $this->guestOnly();
        $this->render('auth/register_hospital', [
            'cities' => (new CityRepository())->allCities(),
            'errors' => $this->pullErrors(),
        ], 'Register a hospital');
    }

    public function registerHospital(): void
    {
        try {
            (new RegistrationService())->registerHospital($_POST, $_FILES['licence_file'] ?? null);
        } catch (ValidationException $e) {
            $this->failValidation($e, 'register/hospital');
            return;
        }
        $this->flash('success', 'Hospital registered. Your account stays Pending until the administrator verifies your licence.');
        $this->go('login');
    }

    public function logout(): void
    {
        Auth::logout();
        // The session was destroyed, so start a fresh one to carry the flash message
        \DonorNexa\Core\Session::start();
        $this->flash('success', 'You have been logged out.');
        $this->go('login');
    }
}
