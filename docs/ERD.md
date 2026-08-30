# Smart Campus VMS — Entity Relationship Diagram

The system stores data in **MongoDB** (database `capstone`). Each Laravel model maps to a collection. Documents use a sequential integer `id` as the logical primary key; child documents store integer foreign keys. MongoDB does not enforce FK constraints — Laravel relationships enforce them at the application layer.

## Logical ER diagram (Crow's foot)

![Logical ER diagram](diagrams/fig12_er_logical.png)

## Conceptual ER diagram (Chen notation)

![Conceptual ER diagram](diagrams/fig11_er_conceptual.png)

## Mermaid ER diagram

```mermaid
erDiagram
    user_roles ||--o{ users : "has role"
    departments ||--o{ users : "belongs to"
    vehicles ||--o{ users : "primary type"
    vehicles ||--o{ user_vehicles : "type"
    users ||--o{ user_vehicles : "owns"
    users ||--o{ visitors : "registers"
    users ||--o{ visitor_rfid_cards : "creates"
    visitors ||--o| visitor_rfid_cards : "holds"
    vehicles ||--o{ visitors : "type"
    users ||--o{ gate_logs : "taps"
    visitors ||--o{ gate_logs : "taps"
    users ||--o{ violations_log : "cited"
    parking_areas ||--o{ violations_log : "location"
    parking_areas ||--o{ parking_slots : "contains"
    users ||--o| parking_slots : "occupies"
    visitors ||--o| parking_slots : "occupies"
    users ||--o{ notifications : "receives"
    users ||--o{ notifications : "sends"
    violations_log ||--o{ notifications : "triggers"
    users ||--o| user_suspensions : "locked by"

    user_roles {
        int id PK
        string role_name
    }

    departments {
        string departmentcode PK
        string departmentname
    }

    vehicles {
        int id PK
        string vehicle_name
    }

    users {
        int id PK
        string fullname
        string email
        string password
        string id_number
        string plate_number
        string rfid_uid
        string status
        string Gate_access
        int strike_count
        int user_role_id FK
        string department_code FK
        int vehicle_id FK
    }

    user_vehicles {
        int id PK
        int user_id FK
        int vehicle_id FK
        string plate_number
        string vehicle_model
        bool is_primary
    }

    visitors {
        int id PK
        string first_name
        string last_name
        string purpose
        string plate_number
        string status
        datetime time_in
        datetime time_out
        int vehicle_id FK
        int registered_by FK
        int visitor_rfid_card_id FK
        string rfid_uid
    }

    visitor_rfid_cards {
        int id PK
        string rfid_uid
        string status
        int visitor_id FK
        int created_by FK
        datetime assigned_at
        datetime returned_at
        datetime expires_at
    }

    gate_logs {
        int id PK
        int daily_log_id
        int user_id FK
        int visitor_id FK
        string action
        string gate_id
        string rfid_uid
        string result
        string reason
        datetime timestamp
    }

    parking_areas {
        int id PK
        string area_name
        int capacity
        array allowed_roles
        bool is_visible
        string designation_notes
    }

    parking_slots {
        int id PK
        int area_id FK
        string slot_number
        string status
        int parked_user_id FK
        int parked_visitor_id FK
    }

    violations_log {
        int id PK
        int user_id FK
        string violator_name
        string plate_number
        string violation_type
        array evidence_photos
        int guard_id
        int area_id FK
        string status
        datetime created_at
    }

    violation_types {
        int id PK
        string violation_name
        string description
        string status
    }

    notifications {
        int id PK
        int user_id FK
        int sender_id FK
        string title
        string message
        string type
        int violation_log_id FK
        bool is_read
        datetime created_at
    }

    user_suspensions {
        int id PK
        int user_id FK
        int strike_count
        bool is_suspended
        datetime suspended_until
    }

    parking_rules {
        int id PK
        string description
    }

    general_informations {
        int id PK
        string description
    }

    system_settings {
        int id PK
        string campus_name
        bool auto_lock_on_3rd_violation
        bool require_photo_evidence
        bool enable_visitor_time_limits
    }

    role_permissions {
        int id PK
        object matrix
    }

    violation_sanctions {
        int id PK
        string sanctions_name
    }

    stalled_vehicles {
        int id PK
        string description
    }
```

## Collection catalog

| Collection | Model | Purpose |
|---|---|---|
| `users` | User | Admin, Guard, Student, Staff accounts |
| `user_roles` | UserRole | Role names (Admin, Guard, Student, Staff) |
| `departments` | Department | College/office codes |
| `vehicles` | Vehicle | Vehicle type lookup (car, motorcycle, …) |
| `user_vehicles` | UserVehicle | Multiple vehicles per registered user |
| `visitors` | Visitor | Walk-in guests registered by Guard |
| `visitor_rfid_cards` | VisitorRfidCard | Temporary RFID pool for visits |
| `gate_logs` | GateLog | Every RFID tap (Entry/Exit, granted/denied) |
| `parking_areas` | ParkingArea | Parking lots/zones and allowed roles |
| `parking_slots` | ParkingSlot | Slot occupancy (user or visitor) |
| `violations_log` | ViolationLog | Citations and evidence (3-strike system) |
| `violation_types` | ViolationType | Citation type catalog (Admin settings) |
| `notifications` | Notification | In-app messages |
| `user_suspensions` | UserSuspension | Account lock after 3 strikes |
| `parking_rules` | ParkingRule | Rules shown on user dashboard |
| `general_informations` | GeneralInformation | Campus notices on user dashboard |
| `system_settings` | SystemSetting | Singleton app settings (`id = 1`) |
| `role_permissions` | RolePermission | Singleton permission matrix (`id = 1`) |
| `violation_sanctions` | ViolationSanction | Sanction name lookup |
| `stalled_vehicles` | StalledVehicle | Stalled-vehicle note lookup |

## Key relationships

- **users** → **user_roles**, **departments**, **vehicles** (lookup FKs on the user document)
- **users** → **user_vehicles** → **vehicles** (one user, many registered vehicles)
- **users** → **visitors** via `registered_by` (Guard registers guests)
- **visitors** ↔ **visitor_rfid_cards** (optional 0..1 card per visit)
- **gate_logs** links to either `user_id` **or** `visitor_id` (not both)
- **parking_slots** optionally occupied by `parked_user_id` **or** `parked_visitor_id`
- **violations_log.violation_type** stores the **name** from `violation_types` (logical link, not numeric FK)

## Regenerate diagrams

```powershell
python docs/generate_system_diagrams.py
```

Outputs:

- `docs/diagrams/fig11_er_conceptual.png` — Chen notation
- `docs/diagrams/fig12_er_logical.png` — Crow's foot with attributes
- `docs/Smart-Campus-VMS-Dashboards.docx` — Full documentation pack
