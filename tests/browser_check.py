"""Run tests/browser_test.js (a real browser in the admin) against one site.

    python3 tests/browser_check.py --php php8.5 --site sites/tubesupplyinternational.co.uk --out /tmp/browser

Needs Node with the playwright package and a Chromium it can find (set
CHROMIUM_PATH to use a particular one). Screenshots and results go to --out.
"""
import argparse
import os
import shutil
import subprocess
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import run_tests  # noqa: E402

HERE = os.path.dirname(os.path.abspath(__file__))


class BrowserCheck(run_tests.Runner):
    def run(self):
        self.reset_db()
        self.start_server()
        image = os.path.join(self.tmp, 'photo.jpg')
        with open(image, 'wb') as f:
            f.write(self.jpeg)
        env = dict(os.environ)
        env['NODE_PATH'] = subprocess.run(['npm', 'root', '-g'], capture_output=True, text=True).stdout.strip()
        try:
            code = subprocess.run(['node', os.path.join(HERE, 'browser_test.js'), 'http://127.0.0.1:%d' % self.port,
                                   self.out, image], env=env).returncode
        finally:
            self.stop()
            log = open(self.errlog).read()
            if log:
                print('PHP log:\n' + log)
            shutil.rmtree(self.tmp, ignore_errors=True)
        return 1 if code or log else 0


if __name__ == '__main__':
    ap = argparse.ArgumentParser()
    ap.add_argument('--php', required=True)
    ap.add_argument('--site', required=True)
    ap.add_argument('--out', required=True)
    args = ap.parse_args()
    args.display_errors = False
    sys.exit(BrowserCheck(args).run())
