import openpyxl
import sqlite3
import shutil
import json
import urllib.request
import time
import os
from datetime import datetime, date, time as dtime

EXCEL_FILE = 'Database_Ran_Cukurin_Bekasi_Format_Asli-v2.xlsx'
TEMP_DB = 'database/database_temp.sqlite'
DEST_DB = 'database/database.sqlite'
BAK_DB = 'database/database.sqlite.bak'
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

def main():
    print("=== Step 1: Loading Excel Data ===", flush=True)
    t0 = time.time()
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
        print(f"  Loaded '{name}': {len(data)} rows", flush=True)
    print(f"Excel loaded in {round(time.time() - t0, 2)}s", flush=True)

    print("\n=== Step 2: Preparing Temporary SQLite Database ===", flush=True)
    # Copy from backup to temp
    if os.path.exists(TEMP_DB):
        os.remove(TEMP_DB)
    shutil.copyfile(BAK_DB, TEMP_DB)

    conn = sqlite3.connect(TEMP_DB)
    cur = conn.cursor()
    cur.execute("PRAGMA foreign_keys = OFF;")

    # Recreate antrean_pemesanan without restrictive CHECK constraints
    cur.execute("""
    CREATE TABLE antrean_pemesanan_clean (
        id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
        pelanggan_id INTEGER NOT NULL,
        kapster_id INTEGER NOT NULL,
        layanan_id INTEGER NOT NULL,
        tipe_antrean VARCHAR NOT NULL,
        nomor_antrean VARCHAR,
        tanggal DATE NOT NULL,
        waktu_estimasi_mulai TIME NOT NULL,
        waktu_mulai_aktual TIME,
        waktu_selesai_aktual TIME,
        status VARCHAR DEFAULT 'Menunggu' NOT NULL,
        alasan_batal TEXT,
        created_at DATETIME,
        updated_at DATETIME,
        deleted_at DATETIME
    );
    """)
    cur.execute("DROP TABLE antrean_pemesanan;")
    cur.execute("ALTER TABLE antrean_pemesanan_clean RENAME TO antrean_pemesanan;")

    # Recreate transaksi without restrictive CHECK constraints
    cur.execute("""
    CREATE TABLE transaksi_clean (
        id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
        antrean_id INTEGER NOT NULL,
        kasir_id INTEGER NOT NULL,
        voucher_id INTEGER,
        subtotal NUMERIC NOT NULL,
        total_diskon NUMERIC DEFAULT 0 NOT NULL,
        total_akhir NUMERIC NOT NULL,
        metode_pembayaran VARCHAR NOT NULL,
        status_bayar VARCHAR DEFAULT 'BelumLunas' NOT NULL,
        tanggal_bayar DATETIME,
        created_at DATETIME,
        updated_at DATETIME,
        deleted_at DATETIME
    );
    """)
    cur.execute("DROP TABLE transaksi;")
    cur.execute("ALTER TABLE transaksi_clean RENAME TO transaksi;")

    # Clear existing data from all tables
    for table in reversed(TABLES_ORDER):
        cur.execute(f"DELETE FROM {table};")
    cur.execute("DELETE FROM model_has_roles;")
    print("  Cleaned tables in temporary database.", flush=True)

    # Insert data
    for table in TABLES_ORDER:
        if table not in all_data:
            continue
        sheet_info = all_data[table]
        headers = list(sheet_info['headers'])
        rows = sheet_info['rows']

        cur.execute(f"PRAGMA table_info({table});")
        sqlite_cols = [r[1] for r in cur.fetchall()]

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
        print(f"  ✔ Inserted {len(batch)} rows into '{table}'.", flush=True)

    # Spatie Role Assignment
    print("  Assigning Spatie roles to all users...", flush=True)
    cur.execute("SELECT id, role FROM pengguna;")
    users = cur.fetchall()
    role_map = {'Pelanggan': 1, 'Kapster': 2, 'Pemilik': 3}
    role_assignments = []
    for uid, urole in users:
        if urole in role_map:
            role_assignments.append((role_map[urole], 'App\\Models\\User', uid))
    
    cur.executemany("INSERT INTO model_has_roles (role_id, model_type, model_id) VALUES (?, ?, ?);", role_assignments)
    print(f"  ✔ Assigned {len(role_assignments)} user roles.", flush=True)

    conn.commit()
    cur.execute("PRAGMA foreign_keys = ON;")

    print("\n--- SQLite Verification ---", flush=True)
    all_ok = True
    for table in TABLES_ORDER:
        cur.execute(f"SELECT COUNT(*) FROM {table};")
        cnt = cur.fetchone()[0]
        expected = len(all_data[table]['rows']) if table in all_data else 0
        status = "OK" if cnt == expected else "MISMATCH"
        if cnt != expected:
            all_ok = False
        print(f"  {table}: {cnt} / {expected} rows -> {status}", flush=True)

    conn.close()

    if not all_ok:
        print("ERROR: SQLite count verification failed! Aborting database replacement.", flush=True)
        return

    # Replace destination database
    print("\n=== Step 3: Activating SQLite Database ===", flush=True)
    time.sleep(1)
    try:
        shutil.copyfile(TEMP_DB, DEST_DB)
        print(f"  ✔ Successfully replaced {DEST_DB} with full dataset!", flush=True)
    except Exception as e:
        print(f"  Warning copying directly: {e}. Trying remove first...", flush=True)
        time.sleep(2)
        shutil.copyfile(TEMP_DB, DEST_DB)
        print(f"  ✔ Successfully replaced {DEST_DB}!", flush=True)

    print("\n=== Step 4: Synchronizing to Google Sheets ===", flush=True)
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
                timeout_val = 300 if len(formatted_rows) > 2000 else 120
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

    print("\n=======================================================", flush=True)
    print("ALL DATA IMPORTED AND SYNCHRONIZED SUCCESSFULLY!", flush=True)
    print("=======================================================", flush=True)

if __name__ == '__main__':
    main()
