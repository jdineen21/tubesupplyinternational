"""Checks for the database connection settings, outside the main crawl.

    python3 tests/extra_checks.py --php php8.5 --site sites/tubesupplyinternational.co.uk

1. Wrong database password: the site shows its own "Error connecting"
   message, nothing else, and logs the reason.
2. A config.php from before the upgrade (no $dbcharset) still works.
3. $dbcharset = 'utf8mb4' / 'latin1' / '' are applied to the connection.
4. Strict mode is off for the site's connection even on a strict server.
5. Passwords: an old md5 hash is upgraded to password_hash() at login, and
   if the password column is too short for that (VARCHAR(32)), the old hash
   is kept so the user can still log in, and the fix is logged.
"""
import argparse
import os
import re
import shutil
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import run_tests  # noqa: E402

DB = run_tests.DB


class Checks(run_tests.Runner):
    def write_config(self, password=DB['password'], charset=None):
        with open(os.path.join(self.root, 'common', 'config.php'), 'w') as f:
            f.write("<?php\n$dbhost = '%s';\n$dbname = '%s';\n$dbuser = '%s';\n$dbpass = '%s';\n"
                    % (DB['host'], DB['name'], DB['user'], password))
            if charset is not None:
                f.write("$dbcharset = '%s';\n" % charset)
        # a probe page that reports what the connection is using
        with open(os.path.join(self.root, 'probe.php'), 'w') as f:
            f.write("<?php require('./common/settings.php');\n"
                    "$r = db_fetch_assoc(db_query('SELECT @@character_set_client AS cs, @@session.sql_mode AS m'));\n"
                    "echo $r['cs'], '|', $r['m'], '|';\n"
                    "$t = db_fetch_assoc(db_query('SELECT title FROM a_tree WHERE branchId = 24'));\n"
                    "echo $t['title'];\n")

    def check(self, name, ok, detail=''):
        print('%-60s %s %s' % (name, 'ok' if ok else 'FAILED', '' if ok else detail))
        if not ok:
            self.failures.append(name)

    def run(self):
        self.reset_db()
        self.start_server()
        b = self.new_browser()
        try:
            # 1. wrong password
            self.write_config(password='wrong')
            open(self.errlog, 'w').close()
            status, h, body = b.request('/')
            log = open(self.errlog).read()
            self.check('wrong password shows the error message', body.strip() ==
                       b'Error connecting to database. Please try again later.', body[:200])
            self.check('wrong password is logged', 'Database connection failed' in log, log[:300])
            self.check('wrong password: no other PHP messages',
                       not re.search(r'PHP (Warning|Fatal|Deprecated|Notice)', log), log[:300])

            # 2. config.php without $dbcharset (from before the upgrade)
            self.write_config(charset=None)
            open(self.errlog, 'w').close()
            status, h, body = b.request('/probe.php')
            self.check('old config.php without $dbcharset works', status == 200 and b'|' in body, body[:200])
            self.check('old config.php: no PHP messages', open(self.errlog).read() == '', open(self.errlog).read())

            # 3. character sets
            for cs, title in (('utf8mb4', 'Новости: new mill in Spain'), ('latin1', None), ('', None)):
                self.write_config(charset=cs)
                status, h, body = b.request('/probe.php')
                got = body.decode('utf-8', 'replace').split('|')
                if cs:
                    self.check("$dbcharset = '%s' is used" % cs, got[0] == cs, body[:200])
                if title:
                    self.check("$dbcharset = '%s' reads Cyrillic correctly" % cs, got[2] == title, got[2])
                # 4. strict mode off
                self.check("strict mode off ($dbcharset = '%s')" % cs, 'STRICT' not in got[1], got[1])
            self.check('charset checks: no PHP messages', open(self.errlog).read() == '', open(self.errlog).read())

            # 5. password column too short for the new hashes, then widened
            self.write_config()
            mysql = ['mysql', DB['name'], '-N', '-e']
            stored = lambda: run_tests.subprocess.run(mysql + ['SELECT password FROM a_users WHERE userId = 1'],
                                                      capture_output=True, text=True).stdout.strip()
            login = lambda pw: self.new_browser().request('/admin/login.htm', data=[('email', 'admin@example.com'),
                                                                                    ('password', pw)])[2].strip()
            run_tests.subprocess.run(mysql + ['ALTER TABLE a_users MODIFY password VARCHAR(32) NOT NULL'], check=True)
            open(self.errlog, 'w').close()
            self.check('short column: login works', login('secret1') == b'1')
            self.check('short column: old hash kept', len(stored()) == 32, stored())
            self.check('short column: fix is logged', 'ALTER TABLE a_users' in open(self.errlog).read())
            self.check('short column: login again works', login('secret1') == b'1')
            self.check('short column: wrong password fails', login('nope!!') == b'0')
            run_tests.subprocess.run(mysql + ['ALTER TABLE a_users MODIFY password VARCHAR(255) NOT NULL'], check=True)
            open(self.errlog, 'w').close()
            self.check('wide column: login works', login('secret1') == b'1')
            self.check('wide column: hash upgraded to bcrypt', stored().startswith('$2y$'), stored())
            self.check('wide column: login with new hash works', login('secret1') == b'1')
            self.check('wide column: wrong password fails', login('nope!!') == b'0')
            self.check('password checks: no PHP messages', open(self.errlog).read() == '', open(self.errlog).read())
        finally:
            self.stop()
            shutil.rmtree(self.tmp, ignore_errors=True)
        print('%s: %s' % (self.php, 'all checks passed' if not self.failures else '%d FAILED' % len(self.failures)))
        return 1 if self.failures else 0


if __name__ == '__main__':
    ap = argparse.ArgumentParser()
    ap.add_argument('--php', required=True)
    ap.add_argument('--site', required=True)
    args = ap.parse_args()
    args.out = os.path.join(run_tests.tempfile.mkdtemp(prefix='atlasextra-'), 'out')
    args.display_errors = False
    sys.exit(Checks(args).run())
