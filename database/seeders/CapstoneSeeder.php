<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\GeneralInformation;
use App\Models\ParkingArea;
use App\Models\ParkingRule;
use App\Models\ParkingSlot;
use App\Models\StalledVehicle;
use App\Models\User;
use App\Models\UserRole;
use App\Models\Vehicle;
use App\Models\ViolationSanction;
use App\Models\ViolationType;
use App\Services\SequenceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CapstoneSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRoles();
        $this->seedDepartments();
        $this->seedVehicles();
        $this->seedGeneralInformation();
        $this->seedParkingRules();
        $this->seedViolationTypes();
        $this->seedViolationSanctions();
        $this->seedStalledVehicles();
        $this->seedParkingAreasAndSlots();
        $this->seedAdminUser();
        $this->seedTestUsers();
        SequenceService::syncCountersForModels([
            User::class,
            UserRole::class,
            Department::class,
            Vehicle::class,
            GeneralInformation::class,
            ParkingRule::class,
            ViolationType::class,
            ViolationSanction::class,
            StalledVehicle::class,
            ParkingArea::class,
            ParkingSlot::class,
        ]);
    }

    private function seedRoles(): void
    {
        $roles = [
            ['id' => 1, 'role_name' => 'Admin'],
            ['id' => 2, 'role_name' => 'Guard'],
            ['id' => 3, 'role_name' => 'Student'],
            ['id' => 4, 'role_name' => 'Staff'],
            ['id' => 5, 'role_name' => 'Visitors'],
        ];

        foreach ($roles as $role) {
            UserRole::query()->updateOrCreate(['id' => $role['id']], $role);
        }
    }

    private function seedDepartments(): void
    {
        $departments = [
            ['id' => 1, 'departmentcode' => 'CCS', 'departmentname' => 'College of Computer Studies'],
            ['id' => 2, 'departmentcode' => 'CHS', 'departmentname' => 'College of Health Sciences'],
            ['id' => 3, 'departmentcode' => 'CEA', 'departmentname' => 'College of Engineering and Architecture'],
            ['id' => 4, 'departmentcode' => 'CTDE', 'departmentname' => 'College of Technological and Developmental Education'],
            ['id' => 5, 'departmentcode' => 'CAS', 'departmentname' => 'College of Arts and Sciences'],
            ['id' => 6, 'departmentcode' => 'CTHBM', 'departmentname' => 'College of Tourism, Hospitality, and Business Management'],
            ['id' => 7, 'departmentcode' => 'BUHI', 'departmentname' => 'Buhi Campus'],
        ];

        foreach ($departments as $department) {
            Department::query()->updateOrCreate(['id' => $department['id']], $department);
        }
    }

    private function seedVehicles(): void
    {
        Vehicle::query()->updateOrCreate(['id' => 1], ['vehicle_name' => 'Motorcycles']);
        Vehicle::query()->updateOrCreate(['id' => 2], ['vehicle_name' => 'Automobiles']);
    }

    private function seedGeneralInformation(): void
    {
        $items = [
            'The CSPC-designated parking areas are on a "first come, first served" basis. Having a parking stickers does not guaranted a parking space but provides the privilege to park in any vacant and designated parking space.',
            'Parking is authorized only in the designated parking areas.',
            'Drivers of vehicle parked on CSPC-assigned parking spaces shall beer their own risk. The College shall not be liable for any loss or damage to any vehicle or other property or any damage or injury to any person arising from or for the prevention of ingress to egress from the parking spaces caused by the use or attempted use by any person of the parking spaces or any parking spaces thereof, except in the case of negligence of the part of the CSPC, its employees and students.',
            'Vehicle must be properly parked at the designated parking spaces.',
            'Overnight parking (10pm - 5am) is prohibited. In the event an employee needs to leave his/her vehicle in a parking area ovrnight or for an extended period due to work-related travel or other extenuating circumstances, the employee shall notify and seek approval from the GSU.',
            'All parking users are enjoiend to maintain a clean and safe parking area.',
            'Strictly no idling while parked on the premises of the College.',
        ];

        foreach ($items as $index => $description) {
            GeneralInformation::query()->updateOrCreate(
                ['id' => $index + 1],
                ['description' => $description]
            );
        }
    }

    private function seedParkingRules(): void
    {
        $rules = [
            'Drivers are required to observed speed restrictions of 1kph within the compound and give right-of-way to pedestrians.',
            'No littering.',
            'Drivers must respect others property.',
            'Drivers must not turn carelessly or drive irresponsibly',
            'Employees and students must not conduct maintenance or repair jobs to thier cars while they are parked in our lot, except in emergency cases, e.g., jump start of vehicle or related cases.',
            'Lack of available space in a desired area is not a valid excuse for violating parking regulations.',
        ];

        foreach ($rules as $index => $description) {
            ParkingRule::query()->updateOrCreate(['id' => $index + 1], ['description' => $description]);
        }
    }

    private function seedViolationTypes(): void
    {
        $types = [
            ['violation_name' => 'Wrong Parking', 'description' => 'Vehicles are not parked at the designated parking area.', 'status' => 'Active'],
            ['violation_name' => 'Over Speeding', 'description' => 'The driver has violated the approved speed limit within the College premises, which is 15 kph.', 'status' => 'Inactive'],
            ['violation_name' => 'Use of Motorcycle Mufflers', 'description' => 'Mufflers are strictly prohibited inside the College premises.', 'status' => 'Active'],
            ['violation_name' => 'Explicit disrespect', 'description' => 'Explicit disrespect to Security Personnel implementing the Policy.', 'status' => 'Active'],
            ['violation_name' => 'Overtime Parking', 'description' => 'Vehicle remained in a parking slot beyond the allowed dwell time (AI monitored).', 'status' => 'Active'],
            ['violation_name' => 'Unauthorized Parking', 'description' => 'Unregistered or access-denied vehicle detected in a parking area (AI monitored).', 'status' => 'Active'],
        ];

        foreach ($types as $index => $type) {
            ViolationType::query()->updateOrCreate(['id' => $index + 1], $type);
        }

        ViolationType::query()
            ->where('violation_name', 'Over Speeding')
            ->update(['status' => 'Inactive']);
    }

    private function seedViolationSanctions(): void
    {
        $sanctions = [
            ['sanctions_name' => '1st Offense', 'description' => 'Issuance of warning ticket by Security Guards'],
            ['sanctions_name' => '2nd Offense', 'description' => 'Suspension of Parking Permit for six (6) months by endorsement of Security Guards to GSU'],
            ['sanctions_name' => '3rd Offense', 'description' => 'Revocation of Parking Privileges by endorsement of GSU to VPAF'],
        ];

        foreach ($sanctions as $index => $sanction) {
            ViolationSanction::query()->updateOrCreate(['id' => $index + 1], $sanction);
        }
    }

    private function seedStalledVehicles(): void
    {
        $items = [
            'Stalled vehicle owners must notify GSU, through the security officers immediately, with their name, the vehicles license plate number, and parking location.',
            'A grace period of up to 12 hours may be allowed. No extensions will be granted. A lost/broken vehicle key is considered a stalled vehicle and falls under this policy. If 12 hours is not sufficient time to remove the vehicle, the owner is requiered to contact a towing company through any means to have the vehicle removed at their expense within 3 hours.',
        ];

        foreach ($items as $index => $description) {
            StalledVehicle::query()->updateOrCreate(['id' => $index + 1], ['description' => $description]);
        }
    }

    private function seedParkingAreasAndSlots(): void
    {
        $areas = [
            ['id' => 1, 'area_name' => 'Administration Building', 'capacity' => 9, 'prefix' => 'AD', 'designation_notes' => 'College Officials'],
            ['id' => 2, 'area_name' => 'Food Laboratory (Front)', 'capacity' => 20, 'prefix' => 'FO', 'designation_notes' => 'Employees Motorcycle'],
            ['id' => 3, 'area_name' => 'Duran Hall (Front)', 'capacity' => 10, 'prefix' => 'DU', 'designation_notes' => 'College Officials'],
            ['id' => 4, 'area_name' => 'ACAD 1 Building (Front)', 'capacity' => 10, 'prefix' => 'AC', 'designation_notes' => 'College Officials'],
            ['id' => 5, 'area_name' => 'Cultural Office (Front)', 'capacity' => 15, 'prefix' => 'CU', 'designation_notes' => 'Employees Motorcycle'],
            ['id' => 6, 'area_name' => 'College Gymnasium (Right Wing)', 'capacity' => 9, 'prefix' => 'GY', 'designation_notes' => 'Car'],
            ['id' => 7, 'area_name' => 'College Gymnasium (Left Wing)', 'capacity' => 30, 'prefix' => 'GL', 'designation_notes' => 'Employees Motorcycle'],
            ['id' => 8, 'area_name' => 'College Auditorium (Left/Right Wing)', 'capacity' => 12, 'prefix' => 'AU', 'designation_notes' => 'Car'],
            ['id' => 9, 'area_name' => 'Villafuerte Hall Circle', 'capacity' => 70, 'prefix' => 'VI', 'designation_notes' => 'Motorcycle/Car Employee/Students'],
            ['id' => 10, 'area_name' => 'Talipapa', 'capacity' => 250, 'prefix' => 'TA', 'designation_notes' => 'Motorcycle/Car Employee/Students'],
            ['id' => 11, 'area_name' => 'Green Building', 'capacity' => 7, 'prefix' => 'GR', 'designation_notes' => 'Car'],
            ['id' => 12, 'area_name' => 'ACAD 5 Building Circle', 'capacity' => 14, 'prefix' => 'A5', 'designation_notes' => 'Car'],
            ['id' => 13, 'area_name' => 'ACAD Building 5 (Front)', 'capacity' => 6, 'prefix' => 'AF', 'designation_notes' => 'Car'],
            ['id' => 14, 'area_name' => 'ACAD Building 5 (Right Wing)', 'capacity' => 20, 'prefix' => 'AR', 'designation_notes' => 'Employees Motorcycle'],
            ['id' => 15, 'area_name' => 'ACAD Building 5 (Open Space)', 'capacity' => 500, 'prefix' => 'AO', 'designation_notes' => 'Motorcycle/Car Employee/Students'],
            ['id' => 16, 'area_name' => 'ACAD Building 3 (CTDE)', 'capacity' => 12, 'prefix' => 'A3', 'designation_notes' => 'Car'],
            ['id' => 17, 'area_name' => 'ACAD Building 4 (CCS)', 'capacity' => 48, 'prefix' => 'A4', 'designation_notes' => '18 Car / 30 Employees Motorcycle'],
            ['id' => 18, 'area_name' => 'Supply Building (Right Wing)', 'capacity' => 25, 'prefix' => 'SU', 'designation_notes' => 'Employees Motorcycle'],
            ['id' => 19, 'area_name' => 'AI Test Lot', 'capacity' => 20, 'prefix' => 'AI', 'designation_notes' => 'YOLOv9 CAM-AI-1 (wired) Student/Staff'],
            ['id' => 20, 'area_name' => 'AI Lot B', 'capacity' => 20, 'prefix' => 'AIB', 'designation_notes' => 'YOLOv9 CAM-AI-2 (Tapo) Student/Staff'],
            ['id' => 21, 'area_name' => 'AI Lot C', 'capacity' => 20, 'prefix' => 'AIC', 'designation_notes' => 'YOLOv9 CAM-AI-3 (Tapo) Student/Staff'],
        ];

        $slotId = 1;

        foreach ($areas as $area) {
            $prefix = $area['prefix'];
            unset($area['prefix']);

            $area['is_visible'] = true;
            $area['allowed_roles'] = ParkingArea::inferRolesFromDesignation($area['designation_notes'] ?? '');

            ParkingArea::query()->updateOrCreate(['id' => $area['id']], $area);

            for ($i = 1; $i <= $area['capacity']; $i++) {
                $slotNumber = in_array($prefix, ['AI', 'AIB', 'AIC'], true)
                    ? $prefix.'-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)
                    : $prefix.'-'.$i;

                ParkingSlot::query()->updateOrCreate(
                    ['id' => $slotId],
                    [
                        'area_id' => $area['id'],
                        'slot_number' => $slotNumber,
                        'status' => 'Available',
                        'parked_user_id' => null,
                    ]
                );
                $slotId++;
            }
        }
    }

    private function seedAdminUser(): void
    {
        User::query()->updateOrCreate(
            ['id' => 1],
            [
                'fullname' => 'System Administrator',
                'email' => 'admin@my.cspc.edu.ph',
                'phone_number' => '09000000000',
                'password' => Hash::make('admin123'),
                'user_role_id' => 1,
                'id_number' => 'ADMIN-001',
                'plate_number' => 'N/A',
                'profile_pic' => 'default_avatar.png',
                'driver_license' => 'N/A',
                'or_cr_photo' => 'N/A',
                'status' => 'Granted',
                'Gate_access' => 'Access',
                'strike_count' => 0,
                'email_verified_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function seedTestUsers(): void
    {
        $defaults = [
            'phone_number' => '09000000000',
            'password' => Hash::make('password123'),
            'plate_number' => 'N/A',
            'profile_pic' => 'default_avatar.png',
            'driver_license' => 'N/A',
            'or_cr_photo' => 'N/A',
            'Gate_access' => 'Access',
            'strike_count' => 0,
            'email_verified_at' => now(),
            'created_at' => now(),
        ];

        User::query()->updateOrCreate(
            ['id' => 2],
            array_merge($defaults, [
                'fullname' => 'Test Guard',
                'email' => 'guard@my.cspc.edu.ph',
                'user_role_id' => 2,
                'id_number' => 'GUARD-001',
                'status' => 'Granted',
            ])
        );
    }

}
