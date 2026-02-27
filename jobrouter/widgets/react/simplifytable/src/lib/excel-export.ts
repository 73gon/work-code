import type { Column, TableRow } from './types';

/**
 * Generates and downloads an Excel (.xlsx) file from the table data.
 * Uses the Office Open XML SpreadsheetML format (a single-sheet minimal .xlsx)
 * built entirely client-side without external dependencies.
 */

// ── helpers ──────────────────────────────────────────────────────────

function escapeXml(value: string): string {
  return value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&apos;');
}

function colLetter(index: number): string {
  let letter = '';
  let i = index;
  while (i >= 0) {
    letter = String.fromCharCode(65 + (i % 26)) + letter;
    i = Math.floor(i / 26) - 1;
  }
  return letter;
}

function formatCellValue(value: unknown, type: string): string {
  if (value === null || value === undefined || value === '') return '';

  if (type === 'date') {
    const d = new Date(value as string);
    if (isNaN(d.getTime())) return String(value);
    return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  if (type === 'currency') {
    const num = typeof value === 'number' ? value : parseFloat(String(value));
    if (isNaN(num)) return String(value);
    return num.toLocaleString('de-DE', { style: 'currency', currency: 'EUR', minimumFractionDigits: 2 });
  }

  if (type === 'status') {
    return String(value);
  }

  return String(value);
}

// ── XLSX builder (minimal Office Open XML) ───────────────────────────

function buildXlsx(columns: Column[], rows: TableRow[]): Blob {
  // Filter out the actions column for export
  const exportCols = columns.filter((c) => c.type !== 'actions');
  const colCount = exportCols.length;
  const rowCount = rows.length + 1; // +1 for header
  const lastCol = colLetter(colCount - 1);

  // Build shared strings
  const sharedStrings: string[] = [];
  const ssIndex = new Map<string, number>();

  function addSharedString(s: string): number {
    const existing = ssIndex.get(s);
    if (existing !== undefined) return existing;
    const idx = sharedStrings.length;
    sharedStrings.push(s);
    ssIndex.set(s, idx);
    return idx;
  }

  // Pre-populate with header labels
  exportCols.forEach((col) => addSharedString(col.label));

  // Pre-populate cell values
  const cellData: number[][] = [];
  for (const row of rows) {
    const rowData: number[] = [];
    for (const col of exportCols) {
      const raw = row[col.id];
      const formatted = formatCellValue(raw, col.type);
      rowData.push(addSharedString(formatted));
    }
    cellData.push(rowData);
  }

  // ── XML parts ──

  const contentTypes = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>`;

  const rels = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>`;

  const workbookRels = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>`;

  const workbook = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Daten" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>`;

  const styles = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/></font>
  </fonts>
  <fills count="3">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFFCC00"/></patternFill></fill>
  </fills>
  <borders count="1"><border/></borders>
  <cellStyleXfs count="1"><xf/></cellStyleXfs>
  <cellXfs count="2">
    <xf fontId="0" fillId="0" borderId="0"/>
    <xf fontId="1" fillId="2" borderId="0" applyFont="1" applyFill="1"/>
  </cellXfs>
</styleSheet>`;

  // Build sheet rows
  let sheetRows = '';

  // Header row
  sheetRows += `<row r="1">`;
  exportCols.forEach((col, ci) => {
    const ref = `${colLetter(ci)}1`;
    const idx = ssIndex.get(col.label)!;
    sheetRows += `<c r="${ref}" t="s" s="1"><v>${idx}</v></c>`;
  });
  sheetRows += `</row>`;

  // Data rows
  cellData.forEach((rowData, ri) => {
    const rowNum = ri + 2;
    sheetRows += `<row r="${rowNum}">`;
    rowData.forEach((ssIdx, ci) => {
      const ref = `${colLetter(ci)}${rowNum}`;
      sheetRows += `<c r="${ref}" t="s"><v>${ssIdx}</v></c>`;
    });
    sheetRows += `</row>`;
  });

  const sheet = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <dimension ref="A1:${lastCol}${rowCount}"/>
  <sheetData>${sheetRows}</sheetData>
  <autoFilter ref="A1:${lastCol}${rowCount}"/>
</worksheet>`;

  // Shared strings XML
  let ssXml = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="${sharedStrings.length}" uniqueCount="${sharedStrings.length}">`;
  for (const s of sharedStrings) {
    ssXml += `<si><t>${escapeXml(s)}</t></si>`;
  }
  ssXml += `</sst>`;

  // ── ZIP builder (minimal, no compression) ──
  return createZipBlob({
    '[Content_Types].xml': contentTypes,
    '_rels/.rels': rels,
    'xl/_rels/workbook.xml.rels': workbookRels,
    'xl/workbook.xml': workbook,
    'xl/styles.xml': styles,
    'xl/worksheets/sheet1.xml': sheet,
    'xl/sharedStrings.xml': ssXml,
  });
}

// ── Minimal ZIP (store-only, no compression – perfectly valid) ───────

function createZipBlob(files: Record<string, string>): Blob {
  const encoder = new TextEncoder();
  const entries: { name: Uint8Array; data: Uint8Array; offset: number }[] = [];
  const parts: Uint8Array[] = [];
  let offset = 0;

  for (const [name, content] of Object.entries(files)) {
    const nameBytes = encoder.encode(name);
    const dataBytes = encoder.encode(content);
    const crc = crc32(dataBytes);

    // Local file header
    const header = new ArrayBuffer(30 + nameBytes.length);
    const hv = new DataView(header);
    hv.setUint32(0, 0x04034b50, true); // signature
    hv.setUint16(4, 20, true); // version needed
    hv.setUint16(6, 0, true); // flags
    hv.setUint16(8, 0, true); // compression (store)
    hv.setUint16(10, 0, true); // mod time
    hv.setUint16(12, 0, true); // mod date
    hv.setUint32(14, crc, true); // crc32
    hv.setUint32(18, dataBytes.length, true); // compressed size
    hv.setUint32(22, dataBytes.length, true); // uncompressed size
    hv.setUint16(26, nameBytes.length, true); // name length
    hv.setUint16(28, 0, true); // extra length

    const headerArr = new Uint8Array(header);
    headerArr.set(nameBytes, 30);

    entries.push({ name: nameBytes, data: dataBytes, offset });
    parts.push(headerArr, dataBytes);
    offset += headerArr.length + dataBytes.length;
  }

  // Central directory
  const cdParts: Uint8Array[] = [];
  let cdSize = 0;

  for (const entry of entries) {
    const cd = new ArrayBuffer(46 + entry.name.length);
    const cv = new DataView(cd);
    const crc = crc32(entry.data);

    cv.setUint32(0, 0x02014b50, true); // central dir signature
    cv.setUint16(4, 20, true); // version made by
    cv.setUint16(6, 20, true); // version needed
    cv.setUint16(8, 0, true); // flags
    cv.setUint16(10, 0, true); // compression
    cv.setUint16(12, 0, true); // mod time
    cv.setUint16(14, 0, true); // mod date
    cv.setUint32(16, crc, true); // crc32
    cv.setUint32(20, entry.data.length, true); // compressed size
    cv.setUint32(24, entry.data.length, true); // uncompressed size
    cv.setUint16(28, entry.name.length, true); // name length
    cv.setUint16(30, 0, true); // extra length
    cv.setUint16(32, 0, true); // comment length
    cv.setUint16(34, 0, true); // disk number start
    cv.setUint16(36, 0, true); // internal attrs
    cv.setUint32(38, 0, true); // external attrs
    cv.setUint32(42, entry.offset, true); // offset of local header

    const cdArr = new Uint8Array(cd);
    cdArr.set(entry.name, 46);
    cdParts.push(cdArr);
    cdSize += cdArr.length;
  }

  // End of central directory
  const eocd = new ArrayBuffer(22);
  const ev = new DataView(eocd);
  ev.setUint32(0, 0x06054b50, true); // signature
  ev.setUint16(4, 0, true); // disk number
  ev.setUint16(6, 0, true); // disk with cd
  ev.setUint16(8, entries.length, true); // entries on this disk
  ev.setUint16(10, entries.length, true); // total entries
  ev.setUint32(12, cdSize, true); // cd size
  ev.setUint32(16, offset, true); // cd offset
  ev.setUint16(20, 0, true); // comment length

  return new Blob([...parts, ...cdParts, new Uint8Array(eocd)] as BlobPart[], {
    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  });
}

// ── CRC-32 (standard, no lookup table needed at startup) ────────────

function crc32(data: Uint8Array): number {
  const table = (() => {
    const t = new Uint32Array(256);
    for (let i = 0; i < 256; i++) {
      let c = i;
      for (let j = 0; j < 8; j++) {
        c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
      }
      t[i] = c;
    }
    return t;
  })();

  let crc = 0xffffffff;
  for (let i = 0; i < data.length; i++) {
    crc = table[(crc ^ data[i]) & 0xff] ^ (crc >>> 8);
  }
  return (crc ^ 0xffffffff) >>> 0;
}

// ── Public API ───────────────────────────────────────────────────────

export function exportToExcel(columns: Column[], data: TableRow[], filename?: string) {
  const blob = buildXlsx(columns, data);
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename || `SimpTrack_Export_${new Date().toISOString().slice(0, 10)}.xlsx`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}
