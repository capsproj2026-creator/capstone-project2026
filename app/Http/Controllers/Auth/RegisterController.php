<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Notification;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\NavigationService;
use App\Services\SequenceService;
use App\Services\TemporaryRfidService;
use App\Support\PasswordRules;
use App\Support\RegistrationCooldown;
use App\Support\SafeUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisterController extends Controller
{
    /** @var list<string> */
    public const REGISTRATION_DEPARTMENT_CODES = [
        'CEA',
        'CTHBM',
        'CHS',
        'CCS',
        'CTDE',
        'CAS',
        'BUHI',
    ];

    public static function registrationDepartmentLabels(): array
    {
        return [
            'CEA' => 'College of Engineering and Architecture (CEA)',
            'CTHBM' => 'College of Tourism, Hospitality, and Business Management (CTHBM)',
            'CHS' => 'College of Health Sciences (CHS)',
            'CCS' => 'College of Computer Studies (CCS)',
            'CTDE' => 'College of Technological and Developmental Education (CTDE)',
            'CAS' => 'College of Arts and Sciences (CAS)',
            'BUHI' => 'Buhi Campus',
        ];
    }

    public function show(Request $request): View|RedirectResponse
    {
        $user = Auth::user();

        if ($user) {
            if (! $user->hasVerifiedEmail()) {
                return redirect()->route('verification.notice');
            }

            if ($user->canAccessPortal()) {
                return redirect()->to(NavigationService::dashboardUrlFor($user));
            }

            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerate(true);
        }

        $dbError = null;
        $departments = collect();
        $vehicleTypes = collect();

        try {
            $labels = self::registrationDepartmentLabels();
            $departments = Department::query()
                ->whereIn('departmentcode', self::REGISTRATION_DEPARTMENT_CODES)
                ->get()
                ->sortBy(fn (Department $dept) => array_search($dept->departmentcode, self::REGISTRATION_DEPARTMENT_CODES, true))
                ->values()
                ->map(function (Department $dept) use ($labels) {
                    $dept->departmentname = $labels[$dept->departmentcode] ?? $dept->departmentname;

                    return $dept;
                });
            $vehicleTypes = Vehicle::query()->orderBy('id')->get();
        } catch (\Throwable $e) {
            report($e);
            $dbError = 'Database connection is not available. Registration form options may be empty until the database is configured.';
        }

        $converting = app(TemporaryRfidService::class)->findByConversionToken($request->query('temp') ?? $request->old('temp_token'));

        return view('auth.register', [
            'dbError' => $dbError,
            'departments' => $departments,
            'vehicleTypes' => $vehicleTypes,
            'passwordHint' => PasswordRules::hint(),
            'hasCspcLogo' => is_file(public_path('images/cspc-logo.png')),
            'converting' => $converting,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            return $this->completeRegistration($request);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withInput($request->except('password', 'password_confirmation', '_token'))
                ->withErrors([
                    'email' => 'Unable to complete registration right now. Please check your connection and try again.',
                ]);
        }
    }

    private function completeRegistration(Request $request): RedirectResponse
    {
        $email = strtolower(trim((string) $request->input('email')));
        $idNumber = trim((string) $request->input('id_number'));
        $temps = app(TemporaryRfidService::class);
        $converting = $temps->findByConversionToken($request->input('temp_token'));

        RegistrationCooldown::purgeExpiredDeniedCollisions($email, $idNumber);

        $blocking = RegistrationCooldown::findBlockingDeniedUser($email, $idNumber);
        if ($blocking && $converting && (int) $blocking->id === (int) $converting->id) {
            $blocking = null;
        }
        if ($blocking && RegistrationCooldown::isWithinCooldown($blocking)) {
            throw ValidationException::withMessages([
                'email' => RegistrationCooldown::remainingMessage($blocking),
            ]);
        }

        $vehicleIds = Vehicle::query()
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $ignoreId = $converting?->id;

        $validated = $request->validate([
            'temp_token' => ['nullable', 'string', 'max:80'],
            'full_name' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email:rfc,dns',
                'max:100',
                Rule::unique(User::class, 'email')->ignore($ignoreId),
            ],
            'phone_number' => ['required', 'string', 'max:20', 'min:7'],
            'password' => PasswordRules::required(),
            'id_number' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9]+$/', Rule::unique(User::class, 'id_number')->ignore($ignoreId)],
            'reg_category' => ['required', 'in:vehicle'],
            'profile_pic' => ['required', 'image', 'max:5120'],
            'id_document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'user_type' => ['required', 'in:Student,Staff'],
            'plate_number' => ['required', 'string', 'max:20', 'min:2'],
            'vehicle_model' => ['required', 'string', 'max:80'],
            'vehicle_color' => ['required', 'string', 'max:40'],
            'department_code' => ['required', 'string', Rule::in(self::REGISTRATION_DEPARTMENT_CODES)],
            'vehicle_id' => ['required', Rule::in($vehicleIds)],
            'driver_license_number' => ['required', 'string', 'max:30'],
            'driver_license' => ['required', 'image', 'max:5120'],
            'lto_or_photo' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'lto_cr_photo' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ], [
            'full_name.required' => 'Please enter your full name.',
            'address.required' => 'Please enter your address.',
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address with a working domain (format and DNS check).',
            'email.unique' => 'This email address is already registered.',
            'phone_number.required' => 'Please enter your contact number.',
            'phone_number.min' => 'Please enter a valid contact number.',
            'password.required' => 'Please enter a password.',
            'password.confirmed' => 'Password confirmation does not match.',
            'plate_number.required' => 'Please enter your plate number.',
            'plate_number.min' => 'Please enter a valid plate number.',
            'vehicle_model.required' => 'Please enter the vehicle model.',
            'vehicle_color.required' => 'Please enter the vehicle color.',
            'id_document.required' => 'Please upload a clear photo of a valid identification / school ID.',
            'profile_pic.required' => 'Please upload a profile picture.',
            'user_type.required' => 'Please select a user type.',
            'department_code.required' => 'Please select a department.',
            'vehicle_id.required' => 'Please select a vehicle type.',
            'driver_license_number.required' => 'Please enter your driver\'s license number.',
            'driver_license.required' => 'Please capture or upload your driver\'s license in the scanner.',
            'lto_or_photo.required' => 'Please upload a clear photo of the LTO Official Receipt (OR).',
            'lto_cr_photo.required' => 'Please upload a clear photo of the LTO Certificate of Registration (CR).',
        ]);

        if ($blocking && RegistrationCooldown::passwordMatchesDenied($blocking, $validated['password'])) {
            throw ValidationException::withMessages([
                'password' => 'Please choose a password different from your previous registration password.',
            ]);
        }

        // Ensure expired denied collisions are gone even if unique rule raced.
        RegistrationCooldown::purgeExpiredDeniedCollisions($email, $idNumber);

        $userRoleId = $request->input('user_type') === 'Student' ? 3 : 4;
        $plateNumber = strtoupper(trim($request->input('plate_number')));
        $departmentCode = $request->input('department_code');
        $vehicleId = (int) $request->input('vehicle_id');

        $fullname = preg_replace('/\s+/u', ' ', trim($validated['full_name'])) ?: trim($validated['full_name']);

        $licenseFilename = SafeUpload::store(
            $request->file('driver_license'),
            'uploads/documents/license',
            'LIC',
            'local'
        );
        $orFilename = SafeUpload::store(
            $request->file('lto_or_photo'),
            'uploads/documents/orcr',
            'OR',
            'local'
        );
        $crFilename = SafeUpload::store(
            $request->file('lto_cr_photo'),
            'uploads/documents/cr',
            'CR',
            'local'
        );
        $idDocFilename = SafeUpload::store(
            $request->file('id_document'),
            'uploads/documents/id',
            'IDDOC',
            'local'
        );

        $profileFilename = $this->storeUpload($request, 'profile_pic', 'uploads/profile', 'default_avatar.png');

        $payload = [
            'fullname' => $fullname,
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'],
            'address' => mb_strtoupper(trim($validated['address']), 'UTF-8'),
            'application_date' => now(),
            'password' => Hash::make($validated['password']),
            'user_role_id' => $userRoleId,
            'department_code' => $departmentCode,
            'vehicle_id' => $vehicleId,
            'vehicle_model' => trim($validated['vehicle_model']),
            'vehicle_color' => trim($validated['vehicle_color']),
            'id_number' => $validated['id_number'],
            'plate_number' => $plateNumber,
            'driver_license_number' => strtoupper(trim($validated['driver_license_number'])),
            'profile_pic' => $profileFilename,
            'id_document' => $idDocFilename,
            'driver_license' => $licenseFilename,
            'or_cr_photo' => $orFilename,
            'lto_or_photo' => $orFilename,
            'lto_cr_photo' => $crFilename,
            'status' => User::STATUS_PENDING,
            'strike_count' => 0,
            'Gate_access' => User::GATE_ACCESS_PENDING,
            'email_verified_at' => null,
            'declined_at' => null,
            'created_at' => now(),
        ];

        $user = null;
        $lastError = null;

        if ($converting) {
            unset($payload['created_at']);
            $user = $temps->convertToFull($converting, $payload);
        } else {
            for ($attempt = 0; $attempt < 2; $attempt++) {
                try {
                    $payloadWithId = $payload;
                    $payloadWithId['id'] = SequenceService::next('users');
                    $payloadWithId['account_type'] = TemporaryRfidService::ACCOUNT_FULL;

                    $user = User::query()->create($payloadWithId);
                    $lastError = null;
                    break;
                } catch (\Throwable $e) {
                    $lastError = $e;
                    if ($attempt === 1 || ! str_contains($e->getMessage(), 'duplicate key')) {
                        throw $e;
                    }
                }
            }

            if (! $user && $lastError) {
                throw $lastError;
            }
        }

        Notification::query()->create([
            'user_id' => $user->id,
            'sender_id' => $user->id,
            'title' => 'Registration Received',
            'message' => "Hello {$user->fullname}, your account is now pending review. Please verify your email, then wait for admin approval.",
            'type' => 'System',
            'is_read' => false,
            'created_at' => now(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        $user->sendEmailVerificationNotification();

        return redirect()
            ->route('verification.notice')
            ->with('success', 'Registration successful! Please verify your email to continue.');
    }

    private function storeUpload(Request $request, string $field, string $directory, string $default): string
    {
        if (! $request->hasFile($field)) {
            return $default;
        }

        return SafeUpload::store(
            $request->file($field),
            $directory,
            strtoupper(substr($field, 0, 4)),
            'public'
        );
    }
}
