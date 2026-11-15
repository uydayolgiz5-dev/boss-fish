/** Excel (.xls SpreadsheetML) — stil + tartibli jadvallar, kutubxonasiz. */

import { formatDisplayDate } from './date';

function escapeXml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

/**
 * @typedef {'title'|'section'|'header'|'text'|'money'|'total'|'metaLabel'|'metaValue'|'blank'} ExcelStyle
 * @typedef {{ v?: string|number|null, s?: ExcelStyle, merge?: number }} ExcelCell
 * @typedef {{ name: string, columns?: number[], freezeRow?: number, rows: Array<Array<string|number|ExcelCell|null|undefined>> }} ExcelSheet
 */

const STYLES = `
  <Styles>
    <Style ss:ID="Default" ss:Name="Normal">
      <Alignment ss:Vertical="Center"/>
      <Font ss:FontName="Arial" ss:Size="11"/>
    </Style>
    <Style ss:ID="title">
      <Alignment ss:Vertical="Center" ss:WrapText="1"/>
      <Font ss:FontName="Arial" ss:Size="16" ss:Bold="1" ss:Color="#0F172A"/>
    </Style>
    <Style ss:ID="section">
      <Alignment ss:Vertical="Center"/>
      <Font ss:FontName="Arial" ss:Size="12" ss:Bold="1" ss:Color="#0F766E"/>
      <Interior ss:Color="#ECFDF5" ss:Pattern="Solid"/>
    </Style>
    <Style ss:ID="header">
      <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
      <Font ss:FontName="Arial" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/>
      <Interior ss:Color="#0F766E" ss:Pattern="Solid"/>
      <Borders>
        <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#0B5F59"/>
        <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#0B5F59"/>
        <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#0B5F59"/>
        <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#0B5F59"/>
      </Borders>
    </Style>
    <Style ss:ID="text">
      <Alignment ss:Vertical="Center" ss:WrapText="1"/>
      <Font ss:FontName="Arial" ss:Size="11"/>
      <Borders>
        <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
        <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
        <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
        <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
      </Borders>
    </Style>
    <Style ss:ID="money">
      <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
      <Font ss:FontName="Arial" ss:Size="11"/>
      <NumberFormat ss:Format="#,##0.00"/>
      <Borders>
        <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
        <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
        <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
        <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
      </Borders>
    </Style>
    <Style ss:ID="total">
      <Alignment ss:Vertical="Center"/>
      <Font ss:FontName="Arial" ss:Size="11" ss:Bold="1"/>
      <Interior ss:Color="#F8FAFC" ss:Pattern="Solid"/>
      <Borders>
        <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
        <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
        <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
        <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
      </Borders>
    </Style>
    <Style ss:ID="totalMoney">
      <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
      <Font ss:FontName="Arial" ss:Size="11" ss:Bold="1"/>
      <NumberFormat ss:Format="#,##0.00"/>
      <Interior ss:Color="#F8FAFC" ss:Pattern="Solid"/>
      <Borders>
        <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
        <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
        <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
        <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
      </Borders>
    </Style>
    <Style ss:ID="metaLabel">
      <Alignment ss:Vertical="Center"/>
      <Font ss:FontName="Arial" ss:Size="11" ss:Bold="1" ss:Color="#475569"/>
    </Style>
    <Style ss:ID="metaValue">
      <Alignment ss:Vertical="Center" ss:WrapText="1"/>
      <Font ss:FontName="Arial" ss:Size="11" ss:Color="#0F172A"/>
    </Style>
    <Style ss:ID="blank">
      <Font ss:FontName="Arial" ss:Size="11"/>
    </Style>
  </Styles>
`;

function normalizeCell(cell) {
  if (cell && typeof cell === 'object' && !Array.isArray(cell) && ('v' in cell || 's' in cell || 'merge' in cell)) {
    return {
      v: cell.v ?? '',
      s: cell.s || 'text',
      merge: cell.merge || 0,
    };
  }
  if (typeof cell === 'number' && Number.isFinite(cell)) {
    return { v: cell, s: 'money', merge: 0 };
  }
  return { v: cell ?? '', s: 'text', merge: 0 };
}

function cellXml(raw) {
  const cell = normalizeCell(raw);
  const style = cell.s || 'text';
  const merge = cell.merge > 0 ? ` ss:MergeAcross="${cell.merge}"` : '';
  const value = cell.v;

  if (value === null || value === undefined || value === '') {
    return `<Cell${merge} ss:StyleID="${style}"><Data ss:Type="String"></Data></Cell>`;
  }

  if (typeof value === 'number' && Number.isFinite(value)) {
    let numStyle = 'money';
    if (style === 'total' || style === 'totalMoney') numStyle = 'totalMoney';
    else if (style === 'money') numStyle = 'money';
    else if (style === 'header' || style === 'section' || style === 'title' || style === 'text' || style === 'metaLabel' || style === 'metaValue') {
      numStyle = style === 'text' ? 'text' : style;
    }
    return `<Cell${merge} ss:StyleID="${numStyle}"><Data ss:Type="Number">${value}</Data></Cell>`;
  }

  return `<Cell${merge} ss:StyleID="${style}"><Data ss:Type="String">${escapeXml(value)}</Data></Cell>`;
}

function rowXml(row, index, headerIndexes) {
  const height = headerIndexes.has(index) ? ' ss:Height="24"' : '';
  return `<Row${height}>${(row || []).map(cellXml).join('')}</Row>`;
}

function sheetXml(sheet) {
  const safeName = escapeXml(String(sheet.name || 'Sheet').slice(0, 31));
  const rows = sheet.rows || [];
  const headerIndexes = new Set(
    rows
      .map((row, idx) => {
        const first = normalizeCell((row || [])[0]);
        return first.s === 'header' ? idx : -1;
      })
      .filter((idx) => idx >= 0),
  );

  const columns = (sheet.columns || [])
    .map((w) => `<Column ss:AutoFitWidth="0" ss:Width="${Number(w) || 80}"/>`)
    .join('');

  const freezeRow = Number(sheet.freezeRow) || 0;
  const freeze = freezeRow > 0
    ? `<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><FreezePanes/><FrozenNoSplit/><SplitHorizontal>${freezeRow}</SplitHorizontal><TopRowBottomPane>${freezeRow}</TopRowBottomPane><ActivePane>2</ActivePane></WorksheetOptions>`
    : '';

  const body = rows.map((row, idx) => rowXml(row, idx, headerIndexes)).join('');
  return `<Worksheet ss:Name="${safeName}"><Table>${columns}${body}</Table>${freeze}</Worksheet>`;
}

/** @param {string} filename @param {ExcelSheet[]} sheets */
export function downloadExcelWorkbook(filename, sheets) {
  const workbook = `<?xml version="1.0" encoding="UTF-8"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook
  xmlns="urn:schemas-microsoft-com:office:spreadsheet"
  xmlns:o="urn:schemas-microsoft-com:office:office"
  xmlns:x="urn:schemas-microsoft-com:office:excel"
  xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
  xmlns:html="http://www.w3.org/TR/REC-html40">
${STYLES}
${(sheets || []).map((s) => sheetXml(s)).join('\n')}
</Workbook>`;

  const blob = new Blob([workbook], { type: 'application/vnd.ms-excel;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  const safeName = String(filename || 'hisobot').replace(/[\\/:*?"<>|]+/g, '_');
  link.href = url;
  link.download = safeName.endsWith('.xls') ? safeName : `${safeName}.xls`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export function excelMoney(value) {
  const n = Number(value);
  if (!Number.isFinite(n)) return 0;
  return Math.round(n * 100) / 100;
}

export function excelDateStamp(date = new Date()) {
  const d = date instanceof Date ? date : new Date(date);
  return formatDisplayDate(d);
}

export function excelCell(value, style = 'text', merge = 0) {
  return { v: value, s: style, merge };
}

export function excelBlankRow() {
  return [];
}
