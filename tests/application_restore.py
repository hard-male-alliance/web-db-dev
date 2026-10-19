"""Export a running application DB, restore a fresh DB, compare every table.

Uses actual mysql/mysqldump clients, never deletes or changes the source database.
DB_* environment matches the application. User needs CREATE/DROP on the generated
application_restore_* database. Dumps remain in project .temp for inspection.
"""
import argparse
import hashlib
import os
from pathlib import Path
import re
import subprocess
import uuid


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--mysql-bin', type=Path, default=Path('.'))
    parser.add_argument('--keep-restored', action='store_true', help='Retain and print isolated DB for HTTP validation')
    args = parser.parse_args()
    suffix = '.exe' if os.name == 'nt' else ''
    client = str(args.mysql_bin / ('mysql' + suffix)) if args.mysql_bin != Path('.') else 'mysql' + suffix
    dump = str(args.mysql_bin / ('mysqldump' + suffix)) if args.mysql_bin != Path('.') else 'mysqldump' + suffix
    source = os.environ.get('DB_DATABASE', 'team_website')
    target = 'application_restore_' + uuid.uuid4().hex[:12]
    assert re.fullmatch(r'application_restore_[a-f0-9]{12}', target)
    env = dict(os.environ, MYSQL_PWD=os.environ.get('DB_PASSWORD', 'local-demo-only'))
    options = ['--no-defaults', '--host=' + os.environ.get('DB_HOST', '127.0.0.1'),
               '--port=' + os.environ.get('DB_PORT', '3306'),
               '--user=' + os.environ.get('DB_USERNAME', 'team'), '--default-character-set=utf8mb4']

    def sql(database, query):
        return subprocess.run([client, *options, '--batch', '--raw', '--skip-column-names', database],
                              input=query.encode(), env=env, check=True, capture_output=True).stdout

    tables = sql(source, 'SHOW TABLES').decode().splitlines()
    assert len(tables) > 10, tables
    assert all(re.fullmatch(r'[a-z_]+', table) for table in tables)
    snapshot = subprocess.run([dump, *options, '--single-transaction', '--no-tablespaces',
                               '--set-gtid-purged=OFF', '--skip-comments', source],
                              env=env, check=True, capture_output=True).stdout
    directory = Path(__file__).resolve().parents[1] / '.temp' / 'application-restore'
    directory.mkdir(parents=True, exist_ok=True)
    (directory / 'snapshot.sql').write_bytes(snapshot)
    sql(source, f'CREATE DATABASE `{target}` CHARACTER SET utf8mb4')
    try:
        subprocess.run([client, *options, target], input=snapshot, env=env, check=True, capture_output=True)
        for table in tables:
            columns = [row.split(b'\t', 1)[0].decode() for row in sql(source, f'SHOW COLUMNS FROM `{table}`').splitlines()]
            assert all(re.fullmatch(r'[a-z_]+', column) for column in columns)
            # JSON preserves NULL, tabs and embedded newlines as part of each row.
            fields = ','.join(f'`{column}`' for column in columns)
            query = f'SELECT JSON_ARRAY({fields}) FROM `{table}`'
            before = sorted(sql(source, query).splitlines())
            after = sorted(sql(target, query).splitlines())
            assert before == after, f'Restored data differs: {table}'
            print(f'PASS restored {table}: {len(before)} rows')
        print(f'PASS fresh-database restore; {len(tables)} tables; dump SHA256 {hashlib.sha256(snapshot).hexdigest()}')
    finally:
        if args.keep_restored:
            print('RESTORED_DATABASE=' + target)
        else:
            sql(source, f'DROP DATABASE `{target}`')


if __name__ == '__main__':
    main()
