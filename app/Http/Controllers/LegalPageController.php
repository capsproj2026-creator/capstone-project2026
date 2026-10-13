<?php

namespace App\Http\Controllers;

use App\Support\CampusParkingPolicy;
use Illuminate\View\View;

class LegalPageController extends Controller
{
    public function privacy(): View
    {
        return $this->page(
            'Privacy Notice',
            'How Smart Campus VMS handles personal information for Camarines Sur Polytechnic Colleges.',
            [
                [
                    'heading' => 'What we collect',
                    'paragraphs' => [
                        'When you register a campus vehicle, we collect your name, address, contact number, email, campus ID number, driver’s license details, vehicle plate, model, and color, plus photos of your license, school ID, profile, Official Receipt, and Certificate of Registration.',
                        'When you pre-register as a visitor or check in as a delivery rider, we collect the name, contact number, purpose or company, plate number, and the office or person you are visiting.',
                        'Gate and parking records may include the time you enter or leave, the RFID card assigned to a visit, and the camera or gate that recorded the event.',
                    ],
                ],
                [
                    'heading' => 'Why we use it',
                    'paragraphs' => [
                        'This information is used for campus vehicle registration, gate access, parking and security monitoring, and official CSPC records. It is not sold and it is not used for advertising.',
                    ],
                ],
                [
                    'heading' => 'Who can see it',
                    'paragraphs' => [
                        'Authorized campus personnel can see the records they need for their work. That includes the General Services Unit, security guards at the gate, and system administrators. Students and staff can see their own registration, entry, and violation records after they sign in.',
                    ],
                ],
                [
                    'heading' => 'How long it is kept',
                    'paragraphs' => [
                        'Records are kept according to CSPC institutional policy and only for as long as they are needed for campus security and official records.',
                    ],
                ],
                [
                    'heading' => 'Your browser',
                    'paragraphs' => [
                        'The site stores a login session in your browser so you stay signed in, and it remembers whether you chose light or dark mode on this device. These are not used to track you across other websites.',
                    ],
                ],
                [
                    'heading' => 'Your rights',
                    'paragraphs' => [
                        'This notice is given under the Data Privacy Act of 2012 (Republic Act No. 10173). For privacy concerns, contact the CSPC Data Protection Officer or the campus administration office.',
                    ],
                ],
            ],
        );
    }

    public function terms(): View
    {
        return $this->page(
            'Terms of Use',
            'Rules for using Smart Campus VMS.',
            [
                [
                    'heading' => 'Your account',
                    'paragraphs' => [
                        'Use your own campus account. Sign in with the email you registered, and keep your password private. Do not share an RFID card or let another person use your vehicle registration to enter the campus.',
                        'The General Services Unit reviews a vehicle registration before gate access is granted. Access can be refused, suspended, or revoked when the registration is incomplete or the campus vehicle policy is not followed.',
                    ],
                ],
                [
                    'heading' => 'Visitors and delivery riders',
                    'paragraphs' => [
                        'Give your real name, contact number, and plate number. After you check in, show the reference code to the guard. Going back to the form clears what you typed so the next person does not see your details.',
                    ],
                ],
                [
                    'heading' => 'Acceptable use',
                    'paragraphs' => [
                        'Do not submit another person’s documents, reuse a plate that is not yours, or try to bypass the gate, cameras, or RFID readers. Do not interfere with security staff who are applying the campus rules.',
                    ],
                ],
            ],
        );
    }

    public function policy(): View
    {
        return $this->page(
            'Campus Vehicle Policy',
            CampusParkingPolicy::TITLE.' ('.CampusParkingPolicy::REFERENCE.').',
            [
                [
                    'heading' => 'Parking',
                    'paragraphs' => [
                        'Designated parking areas are first come, first served. A parking sticker lets you use a vacant designated space. It does not reserve one. Park only in those areas, and park the vehicle properly.',
                        'Overnight parking from 10:00 p.m. to 5:00 a.m. is not allowed unless the General Services Unit has approved it. Do not leave a vehicle idling on campus.',
                    ],
                ],
                [
                    'heading' => 'On campus roads',
                    'paragraphs' => [
                        'Keep to 15 kph inside the compound and give way to pedestrians. Motorcycle mufflers are not allowed on the premises. Do not litter, turn carelessly, or repair a vehicle in the lot except in an emergency such as a jump start.',
                        'The College is not liable for loss or damage to a vehicle using a campus parking space, except where CSPC, its employees, or its students were negligent.',
                    ],
                ],
                [
                    'heading' => 'If a vehicle stalls',
                    'paragraphs' => [
                        'Tell a security officer right away: your name, the plate number, and where the vehicle is. A grace period of up to 12 hours may be allowed. A lost or broken key is treated as a stalled vehicle. If the vehicle cannot be moved in that time, the owner arranges towing at their own expense.',
                    ],
                ],
                [
                    'heading' => 'Violations',
                    'paragraphs' => [
                        'Wrong parking, driving over 15 kph, using a motorcycle muffler, and disrespect toward security personnel are traffic violations. A first offense is a warning. A second offense can suspend the parking permit for six months. A third offense can revoke parking privileges.',
                        'Students and staff who are signed in can open the full policy from their portal. Visitors and riders should follow the guard’s instructions at the gate.',
                    ],
                ],
            ],
        );
    }

    public function accessibility(): View
    {
        return $this->page(
            'Accessibility',
            'Using Smart Campus VMS with a keyboard, a screen reader, or your own display settings.',
            [
                [
                    'heading' => 'What to expect',
                    'paragraphs' => [
                        'Public pages can be used with a keyboard. Buttons and links have names that a screen reader can read. You can enlarge text with your browser, and the light/dark control on these pages keeps the choice on this device.',
                    ],
                ],
                [
                    'heading' => 'If a page is hard to use',
                    'paragraphs' => [
                        'Contact the General Services Unit or the campus administration office and say which page and what got in the way. Include the device and browser you were using if you can.',
                    ],
                ],
            ],
        );
    }

    /**
     * @param  list<array{heading: string, paragraphs: list<string>}>  $sections
     */
    private function page(string $title, string $intro, array $sections): View
    {
        return view('legal.page', [
            'title' => $title,
            'intro' => $intro,
            'sections' => $sections,
        ]);
    }
}
