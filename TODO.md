# BUNHS Login Debug & Fix Plan

## Status: Analysis Complete - Schema Probe Pending

### 1. Information Gathered
```
login_otp.php: fetch_user() fails on students 'username' column (line 73)
index.php: $cert_card1/2/3 undefined → PHP warnings break AJAX JSON
Admin flow: index.php → login_otp.php (AJAX) → dashboard
Test cred: Admin_SchoolHead_BUNHS (likely in admin table, NOT students)
```

### 2. Plan (Step-by-Step)
1. Probe DB schema: confirm students columns, admin/sub_admin structure
2. Fix login_otp.php: 
   - fetch_user(): detect students cols dynamically OR skip for admin creds
   - Early-exit admin check before students
3. Fix index.php:
   - Define $cert_card defaults before usage
   - Remove legacy PHP login handler (use only AJAX)
4. Test: Login with provided creds → OTP → admin_dashboard.php
5. Security: Validate sessions, no output before headers/JSON

### 3. Dependent Files
```
REQUIRED:
- login_otp.php (main fix)
- index.php (warnings + handler)

PROBE:
- DB: students, admin, sub_admin tables

OPTIONAL:
- cache_helper.php (ensure admin:{username} works)
```

### 4. Follow-up Steps
```
1. `tail -f logs/php_errors.log` during login test
2. XAMPP Apache running + DB accessible
3. Test: POST Admin_SchoolHead_BUNHS / BUNHS_Admin_DEPED_buyoan
4. Verify redirect: admin_account/admin_dashboard.php
```

## Next Action: Await DB schema + user approval to edit files.

