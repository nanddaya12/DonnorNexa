import re
import sys
import urllib.parse
import urllib.request
import http.cookiejar

BASE_URL = "http://localhost/DonorNexa/public/index.php"

class LiveHTTPTester:
    def __init__(self):
        self.cj = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cj))
        self.passed = 0
        self.failed = 0

    def assert_test(self, condition, name, detail=""):
        if condition:
            self.passed += 1
            print(f"  [PASS] {name}")
        else:
            self.failed += 1
            print(f"  [FAIL] {name}: {detail}")

    def get_csrf(self, html):
        m = re.search(r'name="_csrf"\s+value="([a-f0-9]{64})"', html)
        return m.group(1) if m else None

    def run(self):
        print("=== MANUAL LIVE HTTP TESTING ON LOCALHOST ===")

        # Test 1: Check Live Login Page
        print("\n1. Verifying Live Login Page Render:")
        resp = self.opener.open(f"{BASE_URL}?r=login")
        login_html = resp.read().decode('utf-8')
        self.assert_test(resp.status == 200, "Login page returns HTTP 200 OK")
        self.assert_test("Quick Demo Accounts" not in login_html, "No demo credential box rendered in HTML")
        self.assert_test("btn-demo-fill" not in login_html, "No demo fill buttons in HTML")
        self.assert_test("Admin@123" not in login_html, "No plain passwords exposed in HTML")
        self.assert_test("theme-toggle" in login_html, "Theme toggle button present in live header")
        self.assert_test("Web Engineering CEP" not in login_html, "Footer contains NO academic CEP references")
        self.assert_test("Official Production Portal" in login_html, "Footer contains clean production branding")
        csrf_token = self.get_csrf(login_html)
        self.assert_test(csrf_token is not None and len(csrf_token) == 64, "Dynamic CSRF token embedded in login form")

        # Test 2: Attempt Login with Invalid Credentials
        print("\n2. Live Negative Login Test (Invalid Password):")
        invalid_data = urllib.parse.urlencode({
            '_csrf': csrf_token,
            'email': 'admin@donornexa.pk',
            'password': 'WrongPassword123'
        }).encode('utf-8')
        req = urllib.request.Request(f"{BASE_URL}?r=login/submit", data=invalid_data)
        resp_invalid = self.opener.open(req)
        invalid_html = resp_invalid.read().decode('utf-8')
        self.assert_test("Incorrect email or password" in invalid_html, "Rejects wrong password with error flash")

        # Test 3: Live Administrator Login
        print("\n3. Live Administrator Authentication (admin@donornexa.pk / Admin@123):")
        # Fetch fresh CSRF token
        resp_login_page = self.opener.open(f"{BASE_URL}?r=login")
        fresh_csrf = self.get_csrf(resp_login_page.read().decode('utf-8'))
        admin_data = urllib.parse.urlencode({
            '_csrf': fresh_csrf,
            'email': 'admin@donornexa.pk',
            'password': 'Admin@123'
        }).encode('utf-8')
        req_admin = urllib.request.Request(f"{BASE_URL}?r=login/submit", data=admin_data)
        resp_admin_login = self.opener.open(req_admin)
        admin_page_html = resp_admin_login.read().decode('utf-8')
        self.assert_test(resp_admin_login.status == 200, "Admin login redirects and loads dashboard successfully")
        self.assert_test("Administrator Console" in admin_page_html or "Dashboard" in admin_page_html, "Admin Dashboard content rendered")
        self.assert_test("Verify Hospitals" in admin_page_html or "hospitals" in admin_page_html, "Admin hospital management navigation accessible")

        # Test 4: Live Access to Admin-Only Routes with Session
        print("\n4. Live Access to Protected Admin Modules:")
        resp_audit = self.opener.open(f"{BASE_URL}?r=admin/audit")
        audit_html = resp_audit.read().decode('utf-8')
        self.assert_test(resp_audit.status == 200 and "Audit" in audit_html, "Access to admin/audit succeeds with admin session")

        # Test 5: Live Logout
        print("\n5. Live Logout Verification:")
        logout_csrf = self.get_csrf(audit_html)
        logout_data = urllib.parse.urlencode({'_csrf': logout_csrf}).encode('utf-8')
        req_logout = urllib.request.Request(f"{BASE_URL}?r=logout", data=logout_data)
        resp_logout = self.opener.open(req_logout)
        logout_html = resp_logout.read().decode('utf-8')
        self.assert_test("You have been logged out" in logout_html, "Logout flash message displayed and session invalidated")

        # Test 6: Verify Guest Blocked from Admin Dashboard after Logout
        print("\n6. Verifying Post-Logout Access Control:")
        try:
            resp_check = self.opener.open(f"{BASE_URL}?r=admin/dashboard")
            # Router redirects unauthorized users to login page
            final_url = resp_check.geturl()
            self.assert_test("r=login" in final_url or "Sign in" in resp_check.read().decode('utf-8'), "Accessing admin after logout redirects back to login")
        except urllib.error.HTTPError as e:
            self.assert_test(e.code in (401, 403), f"Accessing admin after logout is blocked with HTTP {e.code}")

        # Test 7: Hospital Login (hospital.luh@donornexa.pk / Hospital@123)
        print("\n7. Live Hospital Authentication:")
        resp_hosp_page = self.opener.open(f"{BASE_URL}?r=login")
        hosp_csrf = self.get_csrf(resp_hosp_page.read().decode('utf-8'))
        hosp_data = urllib.parse.urlencode({
            '_csrf': hosp_csrf,
            'email': 'hospital.luh@donornexa.pk',
            'password': 'Hospital@123'
        }).encode('utf-8')
        resp_hosp_login = self.opener.open(urllib.request.Request(f"{BASE_URL}?r=login/submit", data=hosp_data))
        hosp_html = resp_hosp_login.read().decode('utf-8')
        self.assert_test("Liaquat University Hospital" in hosp_html, "Hospital Dashboard loaded with hospital identity")
        self.assert_test("New Blood Request" in hosp_html or "New request" in hosp_html, "Emergency request creation enabled for hospital")

        # Test 8: Public APIs
        print("\n8. Live Public API Endpoints:")
        resp_stats = self.opener.open("http://localhost/DonorNexa/api/stats.php")
        stats_data = resp_stats.read().decode('utf-8')
        self.assert_test(resp_stats.status == 200 and "registered_donors" in stats_data, "GET /api/stats.php returns valid JSON stats")

        resp_reqs = self.opener.open("http://localhost/DonorNexa/api/active_requests.php")
        reqs_data = resp_reqs.read().decode('utf-8')
        self.assert_test(resp_reqs.status == 200 and "requests" in reqs_data, "GET /api/active_requests.php returns emergency board list")

        print(f"\n=======================================================")
        print(f"LIVE HTTP TESTING SUMMARY: {self.passed} Passed, {self.failed} Failed")
        print(f"=======================================================")
        return self.failed == 0

if __name__ == '__main__':
    tester = LiveHTTPTester()
    success = tester.run()
    sys.exit(0 if success else 1)
