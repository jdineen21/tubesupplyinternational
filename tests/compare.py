"""Compare two run_tests.py result folders (e.g. original code on PHP 5.6
against the upgraded code on PHP 8.5) and print every difference.

    python3 tests/compare.py /tmp/old /tmp/new

Exit code 0 means the pages, redirects, emails, database and files
matched step for step.
"""
import difflib
import os
import sys


def read(path):
    if not os.path.exists(path):
        return None
    with open(path, encoding='utf-8', errors='replace') as f:
        return f.read()


def main(old, new):
    differences = 0
    names = sorted(set(os.listdir(os.path.join(old, 'steps'))) | set(os.listdir(os.path.join(new, 'steps'))))
    pairs = [(os.path.join('steps', n)) for n in names] + ['mail.txt', 'db.txt', 'files.txt']
    for rel in pairs:
        a, b = read(os.path.join(old, rel)), read(os.path.join(new, rel))
        if a == b:
            continue
        differences += 1
        print('=' * 30, rel)
        if a is None or b is None:
            print('only in', 'new' if a is None else 'old')
            continue
        for line in difflib.unified_diff(a.splitlines(), b.splitlines(), 'old', 'new', n=1, lineterm=''):
            print(line[:300])
    print('\n%d of %d outputs differ' % (differences, len(pairs)))
    return 1 if differences else 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1], sys.argv[2]))
