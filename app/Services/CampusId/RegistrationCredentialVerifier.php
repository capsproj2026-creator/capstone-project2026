<?php

namespace App\Services\CampusId;

use App\Models\User;
use App\Support\PlateLookup;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Block registration when documents, names, or photos do not belong to one person.
 */
class RegistrationCredentialVerifier
{
    public function __construct(
        private readonly CampusIdOcrService $campusIdOcr,
        private readonly LicenseOcrService $licenseOcr,
        private readonly OrCrOcrService $orCrOcr,
    ) {}

    /**
     * @return array<string, string>
     */
    public function assert(Request $request, ?int $ignoreUserId = null): array
    {
        $errors = [];
        $formName = (string) $request->input('full_name');
        $formId = (string) $request->input('id_number');
        $formLicense = (string) $request->input('driver_license_number');
        $formPlate = (string) $request->input('plate_number');

        $licenseFile = $request->file('driver_license');
        $idFile = $request->file('id_document');
        $profileFile = $request->file('profile_pic');
        $orFile = $request->file('lto_or_photo');
        $crFile = $request->file('lto_cr_photo');

        $license = $this->safeLicenseScan($licenseFile);
        $campusId = $this->safeCampusIdScan($idFile);
        $or = $this->safeOrCrScan($orFile, 'or', $formPlate);
        $cr = $this->safeOrCrScan($crFile, 'cr', $formPlate);

        if (($license['full_name'] ?? null) === null) {
            $errors['driver_license'] = 'Could not read the name on the driver\'s license. Use a clear photo of the front of your license.';
        } elseif (! $this->namesMatch($formName, (string) $license['full_name'])) {
            $errors['full_name'] = 'The name on the form does not match the driver\'s license.';
        }

        if (($campusId['full_name'] ?? null) === null) {
            $errors['id_document'] = 'Could not read the name on the school ID. Use a clear photo of the full card.';
        } elseif (! $this->namesMatch($formName, (string) $campusId['full_name'])) {
            $errors['id_document'] = 'The school ID name does not match the name on the form and driver\'s license.';
        }

        if (($campusId['id_number'] ?? null) !== null && ! $this->idsMatch($formId, (string) $campusId['id_number'])) {
            $errors['id_number'] = 'The ID number on the form does not match the SN on the school ID.';
        }

        if (($license['driver_license_number'] ?? null) !== null
            && ! $this->licensesMatch($formLicense, (string) $license['driver_license_number'])) {
            $errors['driver_license_number'] = 'The license number on the form does not match the driver\'s license photo.';
        }

        foreach (['or' => $or, 'cr' => $cr] as $kind => $doc) {
            $field = $kind === 'or' ? 'lto_or_photo' : 'lto_cr_photo';
            $label = $kind === 'or' ? 'OR' : 'CR';
            $owner = (string) ($doc['owner_name'] ?? '');
            if ($owner !== '' && ! $this->namesMatch($formName, $owner)) {
                $errors[$field] = "The owner name on the {$label} does not match the applicant name.";
            }
            $plate = (string) ($doc['plate_number'] ?? '');
            if ($plate !== '' && PlateLookup::normalize($plate) !== PlateLookup::normalize($formPlate)) {
                $errors['plate_number'] = "The plate on the {$label} does not match the plate on the form.";
            }
        }

        if ($this->licenseTaken($formLicense, $ignoreUserId)) {
            $errors['driver_license_number'] = 'This driver\'s license number is already registered to another account.';
        }

        $face = $this->compareFaces($profileFile, $idFile, $licenseFile);
        if (! ($face['ok'] ?? false)) {
            $errors['profile_pic'] = (string) ($face['message'] ?? 'The profile picture, school ID, and driver\'s license must show the same person.');
        }

        $reuse = $this->reusedPhotoError($face['hashes'] ?? [], $ignoreUserId);
        if ($reuse !== null) {
            $errors['profile_pic'] = $reuse;
        }

        $request->attributes->set('credential_photo_hashes', $this->collectSha256($face['hashes'] ?? []));
        $request->attributes->set('credential_photo_ahashes', $this->collectAhashes($face['hashes'] ?? []));

        return $errors;
    }

    /**
     * @param  list<string>  $sha256
     * @param  list<string>  $ahashes
     */
    public function persistFingerprints(User $user, array $sha256, array $ahashes): void
    {
        $user->forceFill([
            'credential_photo_hashes' => array_values(array_unique($sha256)),
            'credential_photo_ahashes' => array_values(array_unique($ahashes)),
        ])->save();
    }

    public function namesMatch(string $left, string $right): bool
    {
        $ca = $this->compactLetters($left);
        $cb = $this->compactLetters($right);
        if (strlen($ca) < 6 || strlen($cb) < 6) {
            return false;
        }
        if ($ca === $cb) {
            return true;
        }
        $sortedA = str_split($ca);
        $sortedB = str_split($cb);
        sort($sortedA);
        sort($sortedB);
        if ($sortedA === $sortedB) {
            return true;
        }

        $a = $this->nameTokens($left);
        $b = $this->nameTokens($right);
        if (count($a) < 2 || count($b) < 2) {
            return false;
        }

        $shared = 0;
        foreach ($a as $token) {
            if ($this->tokenInList($token, $b)) {
                $shared++;
            }
        }

        $needed = min(count($a), count($b), 3);

        return $shared >= 2 && $shared >= $needed - 1;
    }

    private function compactLetters(string $value): string
    {
        return strtoupper((string) preg_replace('/[^A-Z]/i', '', $value));
    }

    /**
     * @return list<string>
     */
    private function nameTokens(string $value): array
    {
        $compact = strtoupper((string) preg_replace('/[^A-Z]+/i', ' ', $value));
        $parts = preg_split('/\s+/', trim($compact)) ?: [];
        $skip = ['DE', 'DEL', 'DELA', 'DA', 'SAN', 'SANTA', 'JR', 'SR', 'II', 'III'];
        $out = [];
        foreach ($parts as $part) {
            if (strlen($part) < 2 || in_array($part, $skip, true)) {
                continue;
            }
            $out[] = $part;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  list<string>  $haystack
     */
    private function tokenInList(string $needle, array $haystack): bool
    {
        foreach ($haystack as $token) {
            if ($token === $needle) {
                return true;
            }
            if (strlen($needle) >= 4 && strlen($token) >= 4 && (str_contains($needle, $token) || str_contains($token, $needle))) {
                return true;
            }
            if (strlen($needle) >= 5 && strlen($token) >= 5 && levenshtein($needle, $token) <= 1) {
                return true;
            }
        }

        return false;
    }

    private function idsMatch(string $form, string $scanned): bool
    {
        $a = preg_replace('/\D+/', '', $form) ?? '';
        $b = preg_replace('/\D+/', '', $scanned) ?? '';

        return $a !== '' && $a === $b;
    }

    private function licensesMatch(string $form, string $scanned): bool
    {
        $a = strtoupper((string) preg_replace('/[^A-Z0-9]/', '', $form));
        $b = strtoupper((string) preg_replace('/[^A-Z0-9]/', '', $scanned));

        return $a !== '' && $a === $b;
    }

    private function licenseTaken(string $license, ?int $ignoreUserId): bool
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Z0-9]/', '', $license));
        if (strlen($normalized) < 6) {
            return false;
        }

        return User::query()
            ->whereNotNull('driver_license_number')
            ->get(['id', 'driver_license_number'])
            ->contains(function (User $user) use ($normalized, $ignoreUserId): bool {
                if ($ignoreUserId !== null && (int) $user->id === $ignoreUserId) {
                    return false;
                }
                $other = strtoupper((string) preg_replace('/[^A-Z0-9]/', '', (string) $user->driver_license_number));

                return $other !== '' && $other === $normalized;
            });
    }

    /**
     * @return array{full_name: ?string, driver_license_number: ?string}
     */
    private function safeLicenseScan(?UploadedFile $file): array
    {
        if (! $file instanceof UploadedFile || ! str_starts_with((string) $file->getMimeType(), 'image/')) {
            return ['full_name' => null, 'driver_license_number' => null];
        }

        try {
            return $this->licenseOcr->scan($file);
        } catch (Throwable $e) {
            report($e);

            return ['full_name' => null, 'driver_license_number' => null];
        }
    }

    /**
     * @return array{full_name: ?string, id_number: ?string}
     */
    private function safeCampusIdScan(?UploadedFile $file): array
    {
        if (! $file instanceof UploadedFile || ! str_starts_with((string) $file->getMimeType(), 'image/')) {
            return ['full_name' => null, 'id_number' => null];
        }

        try {
            return $this->campusIdOcr->scan($file);
        } catch (Throwable $e) {
            report($e);

            return ['full_name' => null, 'id_number' => null];
        }
    }

    /**
     * @return array{plate_number: ?string, owner_name: ?string}
     */
    private function safeOrCrScan(?UploadedFile $file, string $kind, string $plate): array
    {
        if (! $file instanceof UploadedFile) {
            return ['plate_number' => null, 'owner_name' => null];
        }

        try {
            return $this->orCrOcr->scan($file, $kind, $plate);
        } catch (Throwable $e) {
            report($e);

            return ['plate_number' => null, 'owner_name' => null];
        }
    }

    /**
     * @return array{ok: bool, message?: string, hashes?: array<string, array{sha256?: string, ahash?: string}>}
     */
    private function compareFaces(?UploadedFile $profile, ?UploadedFile $id, ?UploadedFile $license): array
    {
        $script = base_path('scripts/match_registration_faces.py');
        if (! is_file($script)) {
            return ['ok' => false, 'message' => 'Photo matching is not available right now.'];
        }

        $temps = [];
        try {
            $images = [];
            foreach (['profile' => $profile, 'id' => $id, 'license' => $license] as $key => $file) {
                if (! $file instanceof UploadedFile || ! str_starts_with((string) $file->getMimeType(), 'image/')) {
                    return ['ok' => false, 'message' => 'Upload a photo for the profile picture, school ID, and driver\'s license.'];
                }
                $path = $this->copyTemp($file, $key);
                $temps[] = $path;
                $images[$key] = $path;
            }

            $python = CampusIdPythonResolver::binary();
            $command = array_merge(CampusIdPythonResolver::commandPrefix($python), [$script]);
            $result = Process::timeout(60)->input(json_encode(['images' => $images]))->run($command);
            $payload = json_decode(trim($result->output()), true);
            if (! is_array($payload)) {
                return [
                    'ok' => false,
                    'message' => 'Could not compare the photos. Try clearer, well-lit pictures.',
                ];
            }

            return $payload;
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'message' => 'Could not compare the photos. Try clearer, well-lit pictures.'];
        } finally {
            foreach ($temps as $path) {
                @unlink($path);
            }
        }
    }

    private function copyTemp(UploadedFile $file, string $key): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'reg-face-'.$key.'-'.bin2hex(random_bytes(4)).'.'.$ext;
        $source = $file->getRealPath();
        if (! is_string($source) || ! @copy($source, $path)) {
            throw new \RuntimeException('Unable to copy '.$key.' photo.');
        }

        return $path;
    }

    /**
     * @param  array<string, array{sha256?: string, ahash?: string}>  $hashes
     */
    private function reusedPhotoError(array $hashes, ?int $ignoreUserId): ?string
    {
        $shaList = $this->collectSha256($hashes);
        $ahashList = $this->collectAhashes($hashes);
        if ($shaList === [] && $ahashList === []) {
            return null;
        }

        $users = User::query()
            ->whereNotNull('credential_photo_hashes')
            ->get(['id', 'credential_photo_hashes', 'credential_photo_ahashes']);

        foreach ($users as $user) {
            if ($ignoreUserId !== null && (int) $user->id === $ignoreUserId) {
                continue;
            }
            $existingSha = is_array($user->credential_photo_hashes) ? $user->credential_photo_hashes : [];
            $existingA = is_array($user->credential_photo_ahashes) ? $user->credential_photo_ahashes : [];
            if (array_intersect($shaList, $existingSha) !== []) {
                return 'That photo was already used on another registration. Upload your own documents.';
            }
            foreach ($ahashList as $hash) {
                foreach ($existingA as $other) {
                    if ($this->ahashDistance((string) $hash, (string) $other) <= 8) {
                        return 'That photo was already used on another registration. Upload your own documents.';
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, array{sha256?: string, ahash?: string}>  $hashes
     * @return list<string>
     */
    private function collectSha256(array $hashes): array
    {
        $out = [];
        foreach ($hashes as $row) {
            $value = strtolower(trim((string) ($row['sha256'] ?? '')));
            if (strlen($value) === 64) {
                $out[] = $value;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  array<string, array{sha256?: string, ahash?: string}>  $hashes
     * @return list<string>
     */
    private function collectAhashes(array $hashes): array
    {
        $out = [];
        foreach ($hashes as $row) {
            $value = strtolower(trim((string) ($row['ahash'] ?? '')));
            if ($value !== '') {
                $out[] = $value;
            }
        }

        return array_values(array_unique($out));
    }

    private function ahashDistance(string $left, string $right): int
    {
        if (! ctype_xdigit($left) || ! ctype_xdigit($right) || strlen($left) !== strlen($right)) {
            return 64;
        }

        $distance = 0;
        $length = strlen($left);
        for ($i = 0; $i < $length; $i += 2) {
            $distance += substr_count(decbin(hexdec(substr($left, $i, 2)) ^ hexdec(substr($right, $i, 2))), '1');
        }

        return $distance;
    }
}
