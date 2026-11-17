import openpyxl
import sqlite3
import json
import urllib.request
import time
import sys
from datetime import datetime, date, time as dtime

EXCEL_FILE = 'Database_Ran_Cukurin_Bekasi_Format_Asli-v2.xlsx'
DB_FILE = 'database/database.sqlite'
WEBHOOK_URL = 'https://script.google.com/macros/s/AKfycbzMopEjpSC3tplC2qZOvSc44llTtB-I6RpM9Gn-XXu27kTMEYpmjvG7qWxH0hvdwfAH/exec'

DEFAULT_PASSWORD_HASH = '$2y$12$Wof9YSKmKryCGK2uD4h2t.lM3fUo9E9oM4wB5Xl4xZzVp7X7u3J9G' # password123

TABLES_ORDER = [
    'layanan',
    'barang',
    'pengguna',
    'voucher',
    'antrean_pemesanan',
    'transaksi',
    'ulasan',
    'faqs',
    'banners',
    'galeris'
]

def format_val(val):
    if val is None:
        return None
    if isinstance(val, (datetime, date)):
        return val.strftime('%Y-%m-%d %H:%M:%S') if isinstance(val, datetime) else val.strftime('%Y-%m-%d')
    if isinstance(val, dtime):
        return val.strftime('%H:%M:%S')
    s = str(val).strip()
    if s == '' or s.lower() == 'null' or s.lower() == 'none':
        return None
    return val

def format_for_json(val):
    if val is None:
        return ''
    if isinstance(val, (datetime, date)):
        return val.strftime('%Y-%m-%d %H:%M:%S') if isinstance(val, datetime) else val.strftime('%Y-%m-%d')
    if isinstance(val, dtime):
        return val.strftime('%H:%M:%S')
    s = str(val).strip()
    if s == '' or s.lower() == 'null' or s.lower() == 'none':
        return ''
    return val

def load_excel_data():
    print(f"Loading Excel file: {EXCEL_FILE}...")
    wb = openpyxl.load_workbook(EXCEL_FILE, data_only=True)
    all_data = {}
    for name in wb.sheetnames:
        ws = wb[name]
        rows = list(ws.iter_rows(values_only=True))
        if not rows:
            continue
        headers = [str(c).strip() for c in rows[0] if c is not None]
        data = []
        for r in rows[1:]:
            if any(cell is not None and str(cell).strip() != '' for cell in r):
                row_dict = {}
                for idx, h in enumerate(headers):
                    row_dict[h] = r[idx] if idx < len(r) else None
                data.append(row_dict)
        all_data[name] = {
            'headers': headers,
            'rows': data
        }
        print(f"  Loaded sheet '{name}': {len(data)} rows, columns: {headers}")
    return all_data

def sync_to_sqlite(all_data):
    print("\n--- Starting SQLite Import ---")
    conn = sqlite3.connect(DB_FILE)
    cur = conn.cursor()
    cur.execute("PRAGMA foreign_keys = OFF;")

    for table in reversed(TABLES_ORDER):
        cur.execute(f"DELETE FROM {table};")
    cur.execute("DELETE FROM model_has_roles;")
    print("Cleared existing table records and model_has_roles.")

    for table in TABLES_ORDER:
        if table not in all_data:
            print(f"Warning: Sheet {table} not in Excel file!")
            continue

        sheet_info = all_data[table]
        headers = list(sheet_info['headers'])
        rows = sheet_info['rows']

        # Determine table columns in sqlite
        cur.execute(f"PRAGMA table_info({table});")
        sqlite_cols = [r[1] for r in cur.fetchall()]

        # Prepare insert columns
        insert_cols = [h for h in headers if h in sqlite_cols]
        if table == 'pengguna' and 'password' in sqlite_cols and 'password' not in insert_cols:
            insert_cols.append('password')

        col_placeholders = ', '.join(['?'] * len(insert_cols))
        col_names = ', '.join([f'"{c}"' for c in insert_cols])
        sql = f"INSERT INTO {table} ({col_names}) VALUES ({col_placeholders})"

        batch = []
        for r in rows:
            row_vals = []
            for col in insert_cols:
                if col == 'password':
                    row_vals.append(DEFAULT_PASSWORD_HASH)
                else:
                    v = format_val(r.get(col))
                    # Normalization for boolean is_active
                    if col == 'is_active':
                        if v in [1, '1', True, 'true', 'True', 'ya', 'yes']:
                            v = 1
                        elif v in [0, '0', False, 'false', 'False', 'tidak', 'no']:
                            v = 0
                        else:
                            v = 1 if v is not None else 1
                    row_vals.append(v)
            batch.append(tuple(row_vals))

        cur.executemany(sql, batch)
        print(f"  Inserted {len(batch)} rows into '{table}'.")

    # Spatie Role Assignment
    print("Assigning Spatie roles to users in model_has_roles...")
    cur.execute("SELECT id, role FROM pengguna;")
    users = cur.fetchall()
    role_map = {'Pelanggan': 1, 'Kapster': 2, 'Pemilik': 3}
    role_assignments = []
    for uid, urole in users:
        if urole in role_map:
            role_assignments.append((role_map[urole], 'App\\Models\\User', uid))
    
    cur.executemany("INSERT INTO model_has_roles (role_id, model_type, model_id) VALUES (?, ?, ?);", role_assignments)
    print(f"  Assigned {len(role_assignments)} user roles.")

    conn.commit()
    cur.execute("PRAGMA foreign_keys = ON;")

    # Verify counts
    print("\n--- SQLite Verification ---")
    for table in TABLES_ORDER:
        cur.execute(f"SELECT COUNT(*) FROM {table};")
        cnt = cur.fetchone()[0]
        expected = len(all_data[table]['rows']) if table in all_data else 0
        status = "OK" if cnt == expected else "MISMATCH"
        print(f"  {table}: {cnt} rows (Expected: {expected}) -> {status}")

    conn.close()

def sync_to_google_sheets(all_data):
    print("\n--- Starting Google Sheets Synchronization ---")
    sync_order = [
        'voucher',
        'banners',
        'layanan',
        'faqs',
        'galeris',
        'barang',
        'pengguna',
        'ulasan',
        'transaksi',
        'antrean_pemesanan'
    ]

    for table in sync_order:
        if table not in all_data:
            continue
        
        sheet_info = all_data[table]
        headers = sheet_info['headers']
        rows = sheet_info['rows']

        # Exclude sensitive columns like password
        send_headers = [h for h in headers if h not in ['password', 'remember_token']]
        
        formatted_rows = []
        for r in rows:
            d = {}
            for h in send_headers:
                d[h] = format_for_json(r.get(h))
            formatted_rows.append(d)

        print(f"Syncing '{table}' to Google Sheets ({len(formatted_rows)} rows)...", flush=True)
        payload = {
            'action': 'sync_table',
            'table': table,
            'rows': formatted_rows
        }

        start_time = time.time()
        success = False
        attempts = 0
        max_attempts = 3

        while attempts < max_attempts and not success:
            attempts += 1
            try:
                data_bytes = json.dumps(payload).encode('utf-8')
                req = urllib.request.Request(
                    WEBHOOK_URL,
                    data=data_bytes,
                    headers={'Content-Type': 'application/json', 'User-Agent': 'Mozilla/5.0'}
                )
                # Timeout: 180s for large tables
                timeout_val = 240 if len(formatted_rows) > 2000 else 120
                with urllib.request.urlopen(req, timeout=timeout_val) as resp:
                    res_body = resp.read().decode('utf-8')
                    elapsed = round(time.time() - start_time, 2)
                    print(f"  ✔ '{table}': {res_body} (Elapsed: {elapsed}s)", flush=True)
                    success = True
            except Exception as e:
                print(f"  ✖ Attempt {attempts} failed for '{table}': {e}", flush=True)
                if attempts < max_attempts:
                    time.sleep(5)

        if not success:
            print(f"ERROR: Could not sync '{table}' to Google Sheets after {max_attempts} attempts!", flush=True)

if __name__ == '__main__':
    data = load_excel_data()
    sync_to_sqlite(data)
    sync_to_google_sheets(data)
    print("\nALL TASKS COMPLETED SUCCESSFULLY!")
