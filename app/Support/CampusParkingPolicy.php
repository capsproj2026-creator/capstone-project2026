<?php

namespace App\Support;

/**
 * User-facing excerpts from CSPC Ref. No. 2026-021
 * Internal Policy and Guidelines for the Use of Parking Spaces and Vehicle Stickers.
 * Headings use official titles only (no "Section n" prefix).
 */
class CampusParkingPolicy
{
    public const REFERENCE = 'Ref. No. 2026-021';

    public const TITLE = 'Internal Policy and Guidelines for the Use of Parking Spaces and Vehicle Stickers';

    /**
     * @return list<array{id: string, title: string, intro?: string, paragraphs?: list<string>, items?: list<string|array{text: string, children?: list<string>}>}>
     */
    public static function sections(): array
    {
        return [
            [
                'id' => 'rationale',
                'title' => 'Rationale',
                'paragraphs' => [
                    'Relative to Rule VII, of Section 707, Item 4, Table VII.4, Group C 3.2, Division C-2, of the 2004 Revised IRR of P.D. No. 1096, otherwise known as the National Building Code of the Philippines, 2005 Revised Edition, on the Minimum Required Parking Slot, Parking Area, and Loading Space Requirements, with regards to Public Colleges and Universities. In line with the function of the General Services Unit, which is responsible for the security and safety of college personnel, students, visitors, and guests, as well as the college properties, and regulating the entry and exit of vehicles within the campus.',
                    'Thus, the General Services Unit has come up with a policy on parking spaces at the CSPC premises which shall manage and regulate the use of parking spaces, thereby ensuring proper and smooth traffic of vehicles in the College\'s parking areas.',
                ],
            ],
            [
                'id' => 'general-information',
                'title' => 'General Information',
                'items' => [
                    'The CSPC-designated parking areas are on a "first come, first served" basis. Having a parking sticker does not guarantee a parking space but provides the privilege to park in any vacant and designated parking space.',
                    'Parking is authorized only in the designated parking areas.',
                    'Drivers of vehicles parked on CSPC-assigned parking spaces shall bear their own risk. The College shall not be liable for any loss or damage to any vehicle or other property or any damage or injury to any person arising from or for the prevention of ingress to egress from the parking spaces caused by the use or attempted use by any person of the parking spaces, except in the case of negligence on the part of CSPC, its employees, and students.',
                    'Vehicles must be properly parked at the designated parking spaces.',
                    'Overnight parking (10:00 p.m. – 5:00 a.m.) is prohibited. In the event an employee needs to leave his/her vehicle in a parking area overnight or for an extended period due to work-related travel or other extenuating circumstances, the employee shall notify and seek approval from the GSU.',
                    [
                        'text' => 'All parking users are enjoined to maintain a clean and safe parking area. The following rules shall always be observed:',
                        'children' => [
                            'Drivers are required to observe speed restrictions of 15 kph within the compound and give right-of-way to pedestrians.',
                            'No littering.',
                            'Drivers must respect others\' property.',
                            'Drivers must not turn carelessly or drive irresponsibly.',
                            'Employees and students must not conduct maintenance or repair jobs to their cars while they are parked in the lot, except in emergency cases, e.g., jump start of vehicles or related cases.',
                            'Lack of available space in a desired area is not a valid excuse for violating parking regulations.',
                        ],
                    ],
                    'Strictly no idling while parked on the premises of the College.',
                ],
            ],
            [
                'id' => 'stalled-vehicles',
                'title' => 'Stalled Vehicles',
                'items' => [
                    'Stalled vehicle owners must notify the GSU, through the security officers immediately, with their name, the vehicle\'s license plate number, and parking location.',
                    'A grace period of up to 12 hours may be allowed. No extensions will be granted. A lost or broken vehicle key is considered a stalled vehicle and falls under this policy. If 12 hours is not sufficient time to remove the vehicle, the owner is required to contact a towing company through any means to have the vehicle removed at their expense within 3 hours.',
                ],
            ],
            [
                'id' => 'parking-and-traffic-violation',
                'title' => 'Parking and Traffic Violation',
                'intro' => 'All parking and traffic violations shall be subjected to the following sanctions:',
                'items' => [
                    '1st Offense — Issuance of a warning ticket by Security Guards.',
                    '2nd Offense — Suspension of Parking Permit for six (6) months by endorsement of Security Guards to GSU.',
                    '3rd Offense — Revocation of Parking Privileges by endorsement of GSU to VPAF.',
                    'The apprehending Security Guard reports the issued apprehension tickets to the GSU Office, and the Head of the GSU verifies the report and later endorses it to the VPAF for approval.',
                    [
                        'text' => 'List of Traffic Violations:',
                        'children' => [
                            'Wrong Parking — Vehicles are not parked at the designated parking area.',
                            'Over Speeding — The driver has violated the approved speed limit within the College premises, which is 15 kph.',
                            'Use of Motorcycle Mufflers — Mufflers are strictly prohibited inside the College premises.',
                            'Explicit disrespect to Security Personnel implementing the Policy.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'other-provisions',
                'title' => 'Other Provisions/Conditions',
                'paragraphs' => [
                    'All CSPC employees, students, visitors, and guests are hereby encouraged to extend full support and compliance with this Policy.',
                ],
            ],
            [
                'id' => 'separability-clause',
                'title' => 'Separability Clause',
                'paragraphs' => [
                    'In case any provision in this Internal Policy and Guidelines shall be invalid, illegal or unenforceable, the validity, legality, and enforceability of the remaining provisions shall not in any way be affected or impaired thereby.',
                ],
            ],
            [
                'id' => 'repealing-clause',
                'title' => 'Repealing Clause',
                'paragraphs' => [
                    'All guidelines, sections, and sub-sections thereof inconsistent with the provisions of this Policy are hereby repealed or modified accordingly.',
                ],
            ],
            [
                'id' => 'effectivity',
                'title' => 'Effectivity',
                'paragraphs' => [
                    'To provide ample time for orientation and dissemination, this Policy shall take effect 15 days after the date of approval of the SUC President III, as duly recommended by the Administrative Council.',
                ],
            ],
        ];
    }

    /**
     * Policy sections managed elsewhere in Admin Settings (official titles only).
     *
     * @return list<string>
     */
    public static function managedSectionIds(): array
    {
        return [
            'general-information',
            'stalled-vehicles',
            'parking-and-traffic-violation',
        ];
    }

    /**
     * Static policy text for Admin → Settings → Policy (no CRUD).
     *
     * @return list<array{id: string, title: string, intro?: string, paragraphs?: list<string>, items?: list<string|array{text: string, children?: list<string>}>}>
     */
    public static function staticSections(): array
    {
        $managed = self::managedSectionIds();

        return array_values(array_filter(
            self::sections(),
            static fn (array $section): bool => ! in_array($section['id'] ?? '', $managed, true)
        ));
    }
}
