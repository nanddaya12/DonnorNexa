<?php
declare(strict_types=1);

namespace DonorNexa\Services;

use DonorNexa\Core\Config;
use DonorNexa\Core\Database;
use DonorNexa\Core\Logger;
use DonorNexa\Core\Validator;
use DonorNexa\Exceptions\ValidationException;
use DonorNexa\Repositories\CityRepository;
use DonorNexa\Repositories\DonorRepository;
use DonorNexa\Repositories\HospitalRepository;
use DonorNexa\Repositories\UserRepository;
use DonorNexa\Traits\Auditable;

class UploadService
{
    public function savePhoto(array $file, string $field = 'photo'): string
    {
        $ext = $this->validate($file, $field, Config::get('app.upload.photo_mimes'), (int) Config::get('app.upload.photo_max'));
        if (@getimagesize($file['tmp_name']) === false) {
            throw new ValidationException([$field => 'That file is not a valid image.']);
        }
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $target = BASE_PATH . '/public/uploads/photos/' . $name;

        if (!$this->resizeAndStore($file['tmp_name'], $target, $ext)) {
            if (!move_uploaded_file($file['tmp_name'], $target)) {
                Logger::error('Could not store uploaded photo');
                throw new ValidationException([$field => 'Could not save the image. Please try again.']);
            }
        }
        return $name;
    }

    public function saveLicence(array $file, string $field = 'licence_file'): string
    {
        $ext = $this->validate($file, $field, Config::get('app.upload.licence_mimes'), (int) Config::get('app.upload.licence_max'));
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], BASE_PATH . '/storage/licences/' . $name)) {
            Logger::error('Could not store uploaded licence');
            throw new ValidationException([$field => 'Could not save the file. Please try again.']);
        }
        return $name;
    }

    public function deletePhoto(?string $name): void
    {
        if ($name && preg_match('/^[a-f0-9]{32}\.(jpg|png)$/', $name)) {
            @unlink(BASE_PATH . '/public/uploads/photos/' . $name);
        }
    }

    public function deleteLicence(?string $name): void
    {
        if ($name && preg_match('/^[a-f0-9]{32}\.pdf$/', $name)) {
            @unlink(BASE_PATH . '/storage/licences/' . $name);
        }
    }

    private function validate(array $file, string $field, array $allowed, int $maxBytes): string
    {
        $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($code === UPLOAD_ERR_NO_FILE) {
            throw new ValidationException([$field => 'Please choose a file to upload.']);
        }
        if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE || ($file['size'] ?? 0) > $maxBytes) {
            throw new ValidationException([$field => 'File is too large (max ' . round($maxBytes / 1048576, 1) . ' MB).']);
        }
        if ($code !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new ValidationException([$field => 'Upload failed. Please try again.']);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset($allowed[$mime])) {
            throw new ValidationException([$field => 'Only ' . strtoupper(implode('/', array_unique($allowed))) . ' files are allowed.']);
        }
        return $allowed[$mime];
    }

    private function resizeAndStore(string $source, string $target, string $ext): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }
        $img = $ext === 'png' ? @imagecreatefrompng($source) : @imagecreatefromjpeg($source);
        if (!$img) {
            return false;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $max = 600;
        if ($w > $max) {
            $img = imagescale($img, $max, (int) round($h * $max / $w));
        }
        $ok = $ext === 'png' ? imagepng($img, $target, 7) : imagejpeg($img, $target, 80);
        imagedestroy($img);
        return $ok;
    }
}

class RegistrationService
{
    use Auditable;

    private const COMMON = [
        'email'            => 'required|email|max:150',
        'password'         => 'required|password',
        'password_confirm' => 'required|matches:password',
    ];

    public function registerDonor(array $input, ?array $photoFile): int
    {
        $v = Validator::make($input, self::COMMON + [
            'name'        => 'required|name',
            'blood_group' => 'required|in:' . implode(',', blood_groups()),
            'dob'         => 'required|date|dob',
            'weight'      => 'required|between:30,250',
            'city_id'     => 'required|int',
            'phone'       => 'required|mobile',
            'cnic'        => 'cnic',
        ], ['password_confirm' => 'Password confirmation', 'dob' => 'Date of birth', 'city_id' => 'City', 'blood_group' => 'Blood group']);

        $users = new UserRepository();
        $donors = new DonorRepository();
        if (!$v->fails()) {
            $c = $v->clean();
            if ($users->emailExists($c['email'])) {
                $v->add('email', 'An account with this email already exists.');
            }
            if (!(new CityRepository())->exists((int) $c['city_id'])) {
                $v->add('city_id', 'Choose a valid city.');
            }
            if ($c['cnic'] !== '' && $donors->cnicTaken($c['cnic'])) {
                $v->add('cnic', 'This CNIC is already registered.');
            }
        }

        $uploads = new UploadService();
        $photo = null;
        if ($photoFile && ($photoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                if (!$v->fails()) {
                    $photo = $uploads->savePhoto($photoFile);
                }
            } catch (ValidationException $e) {
                foreach ($e->errors() as $field => $msg) {
                    $v->add($field, $msg);
                }
            }
        }
        if ($v->fails()) {
            $uploads->deletePhoto($photo);
            throw new ValidationException($v->errors());
        }

        $c = $v->clean();
        try {
            return (int) Database::transaction(function () use ($users, $donors, $c, $photo) {
                $id = $users->create($c['name'], $c['email'], password_hash($c['password'], PASSWORD_DEFAULT), 'donor');
                $donors->create($id, $c, $photo);
                $this->audit('user_registered', 'users:' . $id, null, 'donor', $id);
                return $id;
            });
        } catch (\Throwable $e) {
            $uploads->deletePhoto($photo);
            throw $e;
        }
    }

    public function registerHospital(array $input, ?array $licenceFile): int
    {
        $v = Validator::make($input, self::COMMON + [
            'hospital_name' => 'required|min:3|max:150',
            'licence_no'    => 'required|min:4|max:50',
            'city_id'       => 'required|int',
            'phone'         => 'required|mobile',
        ], ['password_confirm' => 'Password confirmation', 'hospital_name' => 'Hospital name', 'licence_no' => 'Licence number', 'city_id' => 'City']);

        $users = new UserRepository();
        $hospitals = new HospitalRepository();
        if (!$v->fails()) {
            $c = $v->clean();
            if ($users->emailExists($c['email'])) {
                $v->add('email', 'An account with this email already exists.');
            }
            if ($hospitals->licenceNoTaken($c['licence_no'])) {
                $v->add('licence_no', 'This licence number is already registered.');
            }
            if (!(new CityRepository())->exists((int) $c['city_id'])) {
                $v->add('city_id', 'Choose a valid city.');
            }
        }

        $uploads = new UploadService();
        $stored = null;
        if (!$v->fails()) {
            try {
                $stored = $uploads->saveLicence($licenceFile ?? []);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $field => $msg) {
                    $v->add($field, $msg);
                }
            }
        } elseif (($licenceFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $v->add('licence_file', 'Please choose a file to upload.');
        }
        if ($v->fails()) {
            $uploads->deleteLicence($stored);
            throw new ValidationException($v->errors());
        }

        $c = $v->clean();
        try {
            return (int) Database::transaction(function () use ($users, $hospitals, $c, $stored) {
                $id = $users->create($c['hospital_name'], $c['email'], password_hash($c['password'], PASSWORD_DEFAULT), 'hospital');
                $hospitals->create($id, $c, $stored);
                $this->audit('user_registered', 'users:' . $id, null, 'hospital (pending)', $id);
                return $id;
            });
        } catch (\Throwable $e) {
            $uploads->deleteLicence($stored);
            throw $e;
        }
    }
}
