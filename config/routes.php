<?php
/**
 * Route table for the front controller (public/index.php).
 * Format: 'route' => [Controller::class, 'method', [HTTP methods], roles]
 * roles: null = public, [] = any logged-in user, or a list such as ['admin'].
 */

use DonorNexa\Controllers\AdminController;
use DonorNexa\Controllers\AuthController;
use DonorNexa\Controllers\DonorController;
use DonorNexa\Controllers\HospitalController;
use DonorNexa\Controllers\NotificationController;
use DonorNexa\Controllers\PublicController;

return [
    // Guest / public
    'home'               => [PublicController::class, 'home', ['GET'], null],
    'emergencies'        => [PublicController::class, 'emergencies', ['GET'], null],
    'login'              => [AuthController::class, 'showLogin', ['GET'], null],
    'login/submit'       => [AuthController::class, 'login', ['POST'], null],
    'register/donor'     => [AuthController::class, 'showRegisterDonor', ['GET'], null],
    'register/donor/submit' => [AuthController::class, 'registerDonor', ['POST'], null],
    'register/hospital'  => [AuthController::class, 'showRegisterHospital', ['GET'], null],
    'register/hospital/submit' => [AuthController::class, 'registerHospital', ['POST'], null],
    'logout'             => [AuthController::class, 'logout', ['POST'], []],
    'notifications'      => [NotificationController::class, 'index', ['GET'], []],

    // Donor
    'donor/dashboard'    => [DonorController::class, 'dashboard', ['GET'], ['donor']],
    'donor/respond'      => [DonorController::class, 'respond', ['POST'], ['donor']],
    'donor/profile'      => [DonorController::class, 'profile', ['GET'], ['donor']],
    'donor/profile/save' => [DonorController::class, 'updateProfile', ['POST'], ['donor']],
    'donor/history'      => [DonorController::class, 'history', ['GET'], ['donor']],
    'donor/availability' => [DonorController::class, 'toggleAvailability', ['POST'], ['donor']],

    // Hospital
    'hospital/dashboard'      => [HospitalController::class, 'dashboard', ['GET'], ['hospital']],
    'hospital/request/new'    => [HospitalController::class, 'createForm', ['GET'], ['hospital']],
    'hospital/request/create' => [HospitalController::class, 'create', ['POST'], ['hospital']],
    'hospital/request'        => [HospitalController::class, 'show', ['GET'], ['hospital']],
    'hospital/request/notify' => [HospitalController::class, 'notifyMore', ['POST'], ['hospital']],
    'hospital/request/donated' => [HospitalController::class, 'donated', ['POST'], ['hospital']],
    'hospital/request/cancel' => [HospitalController::class, 'cancel', ['POST'], ['hospital']],
    'hospital/request/close'  => [HospitalController::class, 'close', ['POST'], ['hospital']],
    'hospital/inventory'      => [HospitalController::class, 'inventory', ['GET'], ['hospital']],
    'hospital/inventory/save' => [HospitalController::class, 'saveInventory', ['POST'], ['hospital']],

    // Admin
    'admin/dashboard'    => [AdminController::class, 'dashboard', ['GET'], ['admin']],
    'admin/hospitals'    => [AdminController::class, 'hospitals', ['GET'], ['admin']],
    'admin/hospital/verify' => [AdminController::class, 'verifyHospital', ['POST'], ['admin']],
    'admin/hospital/licence' => [AdminController::class, 'licence', ['GET'], ['admin']],
    'admin/users'        => [AdminController::class, 'users', ['GET'], ['admin']],
    'admin/user/block'   => [AdminController::class, 'toggleBlock', ['POST'], ['admin']],
    'admin/requests'     => [AdminController::class, 'requests', ['GET'], ['admin']],
    'admin/audit'        => [AdminController::class, 'auditLog', ['GET'], ['admin']],
    'admin/cities'       => [AdminController::class, 'cities', ['GET'], ['admin']],
    'admin/city/add'     => [AdminController::class, 'addCity', ['POST'], ['admin']],
    'admin/city/delete'  => [AdminController::class, 'deleteCity', ['POST'], ['admin']],
    'admin/settings'     => [AdminController::class, 'settings', ['GET'], ['admin']],
    'admin/settings/save' => [AdminController::class, 'saveSettings', ['POST'], ['admin']],
    'admin/export'       => [AdminController::class, 'export', ['GET'], ['admin']],
];
