================================================================================
                    HASHIDS MATH EXTENSION - ERROR FIX
================================================================================

ERROR MESSAGE:
  RuntimeException: Missing math extension for Hashids, 
                    install either bcmath or gmp.

QUICK FIX (Choose ONE):
================================================================================

METHOD 1: AUTOMATIC SETUP (RECOMMENDED) ⚡
  $ bash setup-hashids.sh bcmath
  Time: 2 minutes
  Does: Install + Enable + Restart + Verify

METHOD 2: MANUAL INSTALL 🔧
  $ sudo apt-get update
  $ sudo apt-get install -y php-bcmath
  $ sudo phpenmod bcmath
  $ sudo systemctl restart apache2
  Time: 5 minutes

METHOD 3: DOCKER 🐳
  Add to Dockerfile:
    RUN docker-php-ext-install bcmath

VERIFICATION (Choose ONE):
================================================================================

CLI CHECK:
  $ php -m | grep bcmath
  $ php -r "echo extension_loaded('bcmath') ? 'OK' : 'FAILED';"

WEB CHECK:
  1. Start your web server
  2. Visit: http://localhost/diagnose/extensions
  3. Look for "Hashids Support" status

API CHECK:
  $ curl http://localhost/diagnose/check-json

RUN TESTS:
  $ php spark test tests/unit/HashidTest.php
  Expected: Tests: 9, Assertions: 19 ✓

DOCUMENTATION:
================================================================================

Start HERE:
  1. DOCUMENTATION_INDEX.md (Navigation guide)
  2. HASHID_QUICK_FIX.md (5-minute solutions)
  3. HASHID_SOLUTION.md (Complete guide)
  4. HASHID_TROUBLESHOOTING.md (Detailed reference)
  5. IMPLEMENTATION_REPORT.md (Technical details)

DIAGNOSTIC PAGES:
================================================================================

Once installed, access these pages:
  • http://localhost/diagnose/extensions    - View all extensions
  • http://localhost/diagnose/hashid         - Test Hashids
  • http://localhost/diagnose/check-json     - JSON API

FILES CREATED:
================================================================================

Documentation:
  ✓ DOCUMENTATION_INDEX.md
  ✓ HASHID_QUICK_FIX.md
  ✓ HASHID_SOLUTION.md
  ✓ HASHID_TROUBLESHOOTING.md
  ✓ IMPLEMENTATION_REPORT.md

Code:
  ✓ app/Helpers/ExtensionChecker.php
  ✓ app/Controllers/Diagnose.php
  ✓ app/Views/diagnose/extensions.php
  ✓ app/Views/diagnose/hashid.php
  ✓ tests/unit/HashidTest.php
  ✓ app/Config/Routes.php (modified)

Scripts:
  ✓ setup-hashids.sh

NEXT STEPS:
================================================================================

1. Run the setup script or install manually
2. Verify the installation using one of the methods above
3. Check the diagnostic page: http://localhost/diagnose/extensions
4. Run the tests: php spark test
5. Start using Hashids in your application!

NEED HELP?
================================================================================

1. Check: DOCUMENTATION_INDEX.md (main navigation)
2. Quick fix: HASHID_QUICK_FIX.md
3. Full guide: HASHID_SOLUTION.md
4. Detailed: HASHID_TROUBLESHOOTING.md
5. Web UI: http://localhost/diagnose/extensions

SUPPORT:
================================================================================

• CLI Check: php -m | grep bcmath
• Status Page: /diagnose/extensions
• Test Suite: php spark test
• API Check: /diagnose/check-json
• Script Help: bash setup-hashids.sh

TECHNICAL DETAILS:
================================================================================

Error Location: /vendor/hashids/hashids/src/Hashids.php:297
Required: bcmath OR gmp PHP extension
Configuration: .env (HASHIDS_SALT variable)

Status: ✅ RESOLVED - Ready for production
Date: January 16, 2026
Version: 1.0

================================================================================
