# ISCVMS Turnover Checklist

## SOURCE CODE
- [ ] Secrets removed from README/start banners
- [ ] `.env.example` complete (no real passwords/tokens)
- [ ] Dependencies documented (INSTALLATION / README)
- [ ] Codebase documented (`docs/`)

## DATABASE
- [ ] Backup tested (`scripts/backup-system.ps1`)
- [ ] Restore documented (`scripts/restore-system.ps1`, MAINTENANCE)

## WEB
- [ ] Admin login tested
- [ ] Guard login tested
- [ ] Student/Staff login tested
- [ ] Admin System Health page opens

## RFID
- [ ] Valid RFID
- [ ] Invalid RFID
- [ ] Gate/barrier tested

## AI
- [ ] Camera feed
- [ ] Vehicle detection
- [ ] Parking zones
- [ ] Plate OCR
- [ ] Manual plate entry
- [ ] User lookup
- [ ] Wrong parking detection
- [ ] Duplicate prevention observed/understood
- [ ] Recovery when AI restarts

## DOCUMENTATION
- [ ] Installation Guide
- [ ] Codebase Guide
- [ ] File Structure
- [ ] Modules
- [ ] Database
- [ ] API
- [ ] Admin Manual
- [ ] Guard Guide
- [ ] Maintenance
- [ ] Troubleshooting
- [ ] Turnover Audit

## TURNOVER
- [ ] Admin trained
- [ ] Technical personnel trained
- [ ] Credentials transferred securely (not via git/chat)
- [ ] Backup copy provided
- [ ] Default passwords changed on target machine
- [ ] `RFID_API_TOKEN` / `AI_PARKING_API_TOKEN` rotated
