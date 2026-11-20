/**
 * GOOGLE APPS SCRIPT UNTUK DATABASE GOOGLE SPREADSHEETS & PENGIRIMAN EMAIL (RAN CUKURIN APP)
 * 
 * CARA PEMASANGAN / PEMBARUAN (Hanya butuh 1-2 menit):
 * 1. Buka spreadsheet Anda:
 *    https://docs.google.com/spreadsheets/d/16oP7uh_2RwNdQwW7ZBJFk12gq0Lv94dH4xPxU54ub0k/edit
 * 2. Klik menu "Extensions" (Ekstensi) > "Apps Script".
 * 3. Hapus seluruh kode lama di editor, lalu tempel (paste) seluruh isi file kode ini.
 * 4. Klik tombol "Save" (ikon disket).
 * 
 * --- [PENTING: AKTIVASI IZIN EMAIL (Wajib 1x)] ---
 * 5. Di toolbar atas sebelah tombol "Debug", perhatikan dropdown fungsi.
 *    Pilih fungsi: "otorisasiIzinEmail"
 * 6. Klik tombol "Run" (Jalankan).
 * 7. Google akan memunculkan dialog "Authorization required" (Otorisasi diperlukan):
 *    - Klik "Review Permissions" (Tinjau Izin).
 *    - Pilih Akun Google Anda.
 *    - Klik "Advanced" (Lanjutan) di kiri bawah.
 *    - Klik "Go to Ran Cukurin API (unsafe)" / "Buka Ran Cukurin (tidak aman)".
 *    - Klik "Allow" (Izinkan).
 *    (Email uji coba akan langsung masuk ke Gmail Anda).
 * 
 * --- [DEPLOY VERSI BARU KE WEB APP] ---
 * 8. Klik tombol "Deploy" (di kanan atas) > pilih "Manage deployments" (Kelola penerapan).
 * 9. Klik ikon Pensil (Edit) di samping penerapan aktif Anda.
 * 10. Di dropdown "Version", pilih "New version" (Versi baru).
 * 11. Klik tombol "Deploy". Selesai!
 */

/**
 * Fungsi pembantu untuk memicu dialog izin kirim email Google (MailApp).
 * Jalankan fungsi ini 1x dari editor Apps Script sebelum melakukan deploy!
 */
function otorisasiIzinEmail() {
  var emailSaya = Session.getActiveUser().getEmail();
  MailApp.sendEmail({
    to: emailSaya,
    subject: "Uji Coba Aktivasi Izin Email - Ran CukurIn",
    htmlBody: "<div style='font-family:sans-serif;padding:20px;background:#f3f4f6;border-radius:10px;'>" +
              "<h2 style='color:#f59e0b;'>Ran CukurIn Barbershop</h2>" +
              "<p>Selamat! Otorisasi pengiriman email Google Apps Script berhasil diaktifkan.</p>" +
              "<p>Aplikasi Ran CukurIn sekarang sudah dapat mengirim email reset password dan verifikasi pendaftaran langsung ke kotak masuk.</p>" +
              "</div>"
  });
  Logger.log("Email uji coba berhasil dikirim ke: " + emailSaya);
}

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

      // Otomatis tambahkan kolom header jika ada field baru yang belum ada
      for (var f in row) {
        if (currentHeaders.indexOf(f) === -1) {
          sheet.getRange(1, currentHeaders.length + 1).setValue(f);
          currentHeaders.push(f);
        }
      }

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

    if (action === "update_row") {
      var sheet = getOrCreateSheet(ss, tableName);
      var id = payload.id;
      var data = payload.data || {};
      var lastRow = sheet.getLastRow();

      if (lastRow > 1) {
        var lastCol = sheet.getLastColumn();
        var headers = sheet.getRange(1, 1, 1, lastCol).getValues()[0];
        var idColIdx = headers.indexOf("id");

        if (idColIdx !== -1) {
          var idValues = sheet.getRange(2, idColIdx + 1, lastRow - 1, 1).getValues();
          for (var r = 0; r < idValues.length; r++) {
            if (String(idValues[r][0]) === String(id)) {
              var rowIndex = r + 2;

              // Otomatis tambahkan kolom header jika ada field data yang belum ada kolomnya
              for (var key in data) {
                var cIdx = headers.indexOf(key);
                if (cIdx === -1) {
                  headers.push(key);
                  sheet.getRange(1, headers.length).setValue(key);
                  lastCol = headers.length;
                }
              }

              var rowValues = sheet.getRange(rowIndex, 1, 1, lastCol).getValues()[0];
              for (var key in data) {
                var cIdx = headers.indexOf(key);
                if (cIdx !== -1) {
                  var cellVal = data[key];
                  if (cellVal === null || cellVal === undefined) {
                    cellVal = "";
                  } else if (typeof cellVal === "object") {
                    cellVal = JSON.stringify(cellVal);
                  }
                  rowValues[cIdx] = cellVal;
                }
              }
              sheet.getRange(rowIndex, 1, 1, lastCol).setValues([rowValues]);
              return ContentService.createTextOutput(JSON.stringify({
                success: true,
                message: "Baris ID " + id + " berhasil diperbarui di " + tableName
              })).setMimeType(ContentService.MimeType.JSON);
            }
          }
        }
      }

      return ContentService.createTextOutput(JSON.stringify({
        success: false,
        message: "ID " + id + " tidak ditemukan di " + tableName
      })).setMimeType(ContentService.MimeType.JSON);
    }

    if (action === "send_email") {
      var to = payload.to;
      var subject = payload.subject || "Pemberitahuan Ran CukurIn";
      var html = payload.html || payload.body || "";
      var plainText = payload.text || html.replace(/<[^>]*>/g, "");
      var senderName = payload.from || "Ran CukurIn Barbershop";

      if (!to) {
        return ContentService.createTextOutput(JSON.stringify({
          success: false,
          message: "Parameter 'to' wajib diisi"
        })).setMimeType(ContentService.MimeType.JSON);
      }

      MailApp.sendEmail({
        to: to,
        subject: subject,
        htmlBody: html,
        body: plainText,
        name: senderName
      });

      return ContentService.createTextOutput(JSON.stringify({
        success: true,
        message: "Email berhasil dikirim ke " + to
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

    if (action === "get_user" && e.parameter.email) {
      var sheet = ss.getSheetByName("pengguna");
      if (!sheet) {
        return ContentService.createTextOutput(JSON.stringify({
          success: false,
          message: "Sheet pengguna tidak ditemukan"
        })).setMimeType(ContentService.MimeType.JSON);
      }

      var data = sheet.getDataRange().getValues();
      if (data.length <= 1) {
        return ContentService.createTextOutput(JSON.stringify({
          success: false,
          message: "Data pengguna kosong"
        })).setMimeType(ContentService.MimeType.JSON);
      }

      var headers = data[0];
      var emailIdx = headers.indexOf("email");
      if (emailIdx === -1) {
        return ContentService.createTextOutput(JSON.stringify({
          success: false,
          message: "Kolom email tidak ditemukan"
        })).setMimeType(ContentService.MimeType.JSON);
      }

      var searchEmail = String(e.parameter.email).toLowerCase().trim();
      for (var i = 1; i < data.length; i++) {
        if (String(data[i][emailIdx]).toLowerCase().trim() === searchEmail) {
          var userObj = {};
          for (var j = 0; j < headers.length; j++) {
            userObj[headers[j]] = data[i][j];
          }
          return ContentService.createTextOutput(JSON.stringify({
            success: true,
            user: userObj
          })).setMimeType(ContentService.MimeType.JSON);
        }
      }

      return ContentService.createTextOutput(JSON.stringify({
        success: false,
        message: "Pengguna tidak ditemukan"
      })).setMimeType(ContentService.MimeType.JSON);
    }

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
      version: "2.2-user-sync",
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
