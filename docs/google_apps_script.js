/**
 * GOOGLE APPS SCRIPT UNTUK DATABASE GOOGLE SPREADSHEETS (RAN CUKURIN APP)
 * 
 * CARA PEMASANGAN (Hanya butuh 1 menit):
 * 1. Buka spreadsheet Anda:
 *    https://docs.google.com/spreadsheets/d/16oP7uh_2RwNdQwW7ZBJFk12gq0Lv94dH4xPxU54ub0k/edit
 * 2. Klik menu "Extensions" (Ekstensi) > "Apps Script".
 * 3. Hapus kode default (jika ada), lalu tempel (paste) seluruh isi kode ini.
 * 4. Klik tombol "Save" (ikon disket) di atas.
 * 5. Klik tombol "Deploy" (di kanan atas) > pilih "New deployment".
 * 6. Di jendela deployment:
 *    - Klik ikon gerigi (Select type) > pilih "Web app".
 *    - Description: "Ran Cukurin API".
 *    - Execute as: "Me" (email Anda).
 *    - Who has access: "Anyone" (Siapa saja). -> PENTING agar Laravel bisa kirim data!
 * 7. Klik "Deploy". Jika diminta otorisasi izin ("Authorize access"), pilih akun Google Anda dan klik "Allow".
 * 8. Salin "Web app URL" (bentuknya: https://script.google.com/macros/s/.../exec).
 * 9. Tempelkan URL tersebut ke file .env aplikasi Laravel Anda:
 *    GOOGLE_SHEETS_WEBHOOK_URL="https://script.google.com/macros/s/..."
 * 10. Jalankan perintah di terminal Laravel untuk sinkronisasi otomatis:
 *    php artisan sheets:sync
 */

function doPost(e) {
  var lock = LockService.getScriptLock();
  lock.tryLock(10000);

  try {
    var contents = e.postData.contents;
    var payload = JSON.parse(contents);
    var action = payload.action;
    var tableName = payload.table || "default";
    var ss = SpreadsheetApp.getActiveSpreadsheet();
    
    if (action === "sync_table") {
      var rows = payload.rows || [];
      var sheet = getOrCreateSheet(ss, tableName);
      sheet.clearContents();

      if (rows.length > 0) {
        var headers = Object.keys(rows[0]);
        var dataMatrix = [headers];

        for (var i = 0; i < rows.length; i++) {
          var rowData = [];
          for (var j = 0; j < headers.length; j++) {
            var val = rows[i][headers[j]];
            if (val === null || val === undefined) {
              val = "";
            } else if (typeof val === "object") {
              val = JSON.stringify(val);
            }
            rowData.push(val);
          }
          dataMatrix.push(rowData);
        }

        sheet.getRange(1, 1, dataMatrix.length, headers.length).setValues(dataMatrix);
        
        // Format Header
        var headerRange = sheet.getRange(1, 1, 1, headers.length);
        headerRange.setFontWeight("bold");
        headerRange.setBackground("#2c3e50");
        headerRange.setFontColor("#ffffff");
        
        // Auto-fit kolom
        for (var c = 1; c <= headers.length; c++) {
          sheet.autoResizeColumn(c);
        }
      }

      return ContentService.createTextOutput(JSON.stringify({
        success: true,
        message: "Tabel " + tableName + " berhasil disinkronkan (" + rows.length + " baris)."
      })).setMimeType(ContentService.MimeType.JSON);
    }

    if (action === "append_row") {
      var row = payload.data || {};
      var sheet = getOrCreateSheet(ss, tableName);
      var lastRow = sheet.getLastRow();

      if (lastRow === 0) {
        // Belum ada data sama sekali, tulis header dulu
        var headers = Object.keys(row);
        sheet.appendRow(headers);
        var headerRange = sheet.getRange(1, 1, 1, headers.length);
        headerRange.setFontWeight("bold");
        headerRange.setBackground("#2c3e50");
        headerRange.setFontColor("#ffffff");
      }

      // Ambil header dari baris pertama
      var currentHeaders = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
      var newRowData = [];
      for (var h = 0; h < currentHeaders.length; h++) {
        var hName = currentHeaders[h];
        var val = row[hName] !== undefined ? row[hName] : "";
        if (typeof val === "object" && val !== null) {
          val = JSON.stringify(val);
        }
        newRowData.push(val);
      }

      sheet.appendRow(newRowData);

      return ContentService.createTextOutput(JSON.stringify({
        success: true,
        message: "Baris baru berhasil ditambahkan ke " + tableName
      })).setMimeType(ContentService.MimeType.JSON);
    }

    return ContentService.createTextOutput(JSON.stringify({
      success: false,
      message: "Action tidak dikenali"
    })).setMimeType(ContentService.MimeType.JSON);

  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({
      success: false,
      error: err.toString()
    })).setMimeType(ContentService.MimeType.JSON);
  } finally {
    lock.releaseLock();
  }
}

function doGet(e) {
  try {
    var ss = SpreadsheetApp.getActiveSpreadsheet();
    var action = (e && e.parameter && e.parameter.action) ? e.parameter.action : "";
    var tableName = (e && e.parameter && e.parameter.table) ? e.parameter.table : "";

    if (action === "get_table" && tableName) {
      var sheet = ss.getSheetByName(tableName);
      if (!sheet) {
        return ContentService.createTextOutput(JSON.stringify({
          success: false,
          message: "Sheet tidak ditemukan: " + tableName
        })).setMimeType(ContentService.MimeType.JSON);
      }

      var data = sheet.getDataRange().getValues();
      if (data.length <= 1) {
        return ContentService.createTextOutput(JSON.stringify({
          success: true,
          data: []
        })).setMimeType(ContentService.MimeType.JSON);
      }

      var headers = data[0];
      var result = [];
      for (var i = 1; i < data.length; i++) {
        var obj = {};
        for (var j = 0; j < headers.length; j++) {
          obj[headers[j]] = data[i][j];
        }
        result.push(obj);
      }

      return ContentService.createTextOutput(JSON.stringify({
        success: true,
        data: result
      })).setMimeType(ContentService.MimeType.JSON);
    }

    // Default status check
    return ContentService.createTextOutput(JSON.stringify({
      status: "online",
      spreadsheetName: ss.getName(),
      sheets: ss.getSheets().map(function(s) { return s.getName(); })
    })).setMimeType(ContentService.MimeType.JSON);

  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({
      success: false,
      error: err.toString()
    })).setMimeType(ContentService.MimeType.JSON);
  }
}

function getOrCreateSheet(ss, sheetName) {
  var sheet = ss.getSheetByName(sheetName);
  if (!sheet) {
    sheet = ss.insertSheet(sheetName);
  }
  // Hapus Sheet1 bawaan jika sudah ada sheet lain dan Sheet1 masih kosong
  var defaultSheet = ss.getSheetByName("Sheet1");
  if (defaultSheet && ss.getSheets().length > 1 && defaultSheet.getLastRow() === 0) {
    try {
      ss.deleteSheet(defaultSheet);
    } catch (e) {}
  }
  return sheet;
}
