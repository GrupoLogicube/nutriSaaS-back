import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const backendRoot = path.resolve(__dirname, '..');
const dataDir = path.join(backendRoot, 'data');
const supabaseSqlDir = path.join(backendRoot, 'supabase', 'sql');
const mainCsvPath = path.join(dataDir, 'tca_ecuador_2021_corregido.csv');
const issuesCsvPath = path.join(dataDir, 'tca_ecuador_2021_corregido_issues.csv');
const outputPath = path.join(supabaseSqlDir, '20260530_tca_ecuador_2021_import.generated.sql');

const parseCsv = (text) => {
  const rows = [];
  let row = [];
  let value = '';
  let quoted = false;

  for (let i = 0; i < text.length; i += 1) {
    const char = text[i];
    const next = text[i + 1];

    if (quoted) {
      if (char === '"' && next === '"') {
        value += '"';
        i += 1;
      } else if (char === '"') {
        quoted = false;
      } else {
        value += char;
      }
      continue;
    }

    if (char === '"') {
      quoted = true;
    } else if (char === ',') {
      row.push(value);
      value = '';
    } else if (char === '\n') {
      row.push(value);
      rows.push(row);
      row = [];
      value = '';
    } else if (char !== '\r') {
      value += char;
    }
  }

  if (value.length > 0 || row.length > 0) {
    row.push(value);
    rows.push(row);
  }

  const [headers, ...records] = rows;
  const cleanHeaders = headers.map((header) => header.replace(/^\uFEFF/, ''));

  return records
    .filter((record) => record.some((cell) => cell !== ''))
    .map((record) => Object.fromEntries(cleanHeaders.map((header, index) => [header, record[index] ?? ''])));
};

const sqlString = (value) => {
  if (value === '') return 'null';
  return `'${String(value).replaceAll("'", "''")}'`;
};

const sqlNumber = (value) => {
  if (value === '') return 'null';
  return String(value);
};

const mainColumns = [
  'id',
  'fuente',
  'grupo',
  'alimento',
  'nombre_ingles',
  'porcion_base',
  'cantidad_base',
  'energia_kcal',
  'proteina_g',
  'grasa_total_g',
  'carbohidratos_g',
  'fibra_g',
  'ags_g',
  'agm_g',
  'agpi_g',
  'colesterol_mg',
  'calcio_mg',
  'fosforo_mg',
  'hierro_mg',
  'potasio_mg',
  'sodio_mg',
  'zinc_mg',
  'vitamina_c_mg',
  'vitamina_a_ug_ere',
  'folatos_ug',
  'vitamina_b12_ug',
  'pagina_pdf',
];

const numericColumns = new Set([
  'id',
  'fuente',
  'cantidad_base',
  'energia_kcal',
  'proteina_g',
  'grasa_total_g',
  'carbohidratos_g',
  'fibra_g',
  'ags_g',
  'agm_g',
  'agpi_g',
  'colesterol_mg',
  'calcio_mg',
  'fosforo_mg',
  'hierro_mg',
  'potasio_mg',
  'sodio_mg',
  'zinc_mg',
  'vitamina_c_mg',
  'vitamina_a_ug_ere',
  'folatos_ug',
  'vitamina_b12_ug',
  'pagina_pdf',
]);

const mainRows = parseCsv(fs.readFileSync(mainCsvPath, 'utf8'));
const issueRows = parseCsv(fs.readFileSync(issuesCsvPath, 'utf8'));

const mainValues = mainRows.map((row) => {
  const values = mainColumns.map((column) => (
    numericColumns.has(column) ? sqlNumber(row[column]) : sqlString(row[column])
  ));
  return `(${values.join(', ')})`;
});

const updateSet = mainColumns
  .filter((column) => column !== 'id')
  .map((column) => `${column} = excluded.${column}`)
  .concat([
    "estado_validacion = 'validado'",
    "fuente_documental = 'Tabla de composicion quimica de los alimentos: basada en nutrientes de interes para la poblacion ecuatoriana, USFQ, 2021'",
    'updated_at = now()',
  ])
  .join(',\n  ');

const issueValues = issueRows.map((row) => `(${sqlNumber(row.id)}, ${sqlNumber(row.pagina_pdf)}, ${sqlString(row.tipo)}, ${sqlString(row.detalle)})`);

const sql = `begin;

insert into public.tca_ecuador_2021 (
  ${mainColumns.join(',\n  ')}
)
values
${mainValues.join(',\n')}
on conflict (id) do update
set
  ${updateSet};

insert into public.tca_ecuador_2021_observaciones (
  alimento_id,
  pagina_pdf,
  tipo,
  detalle
)
values
${issueValues.join(',\n')}
on conflict (alimento_id, tipo, detalle) do update
set
  pagina_pdf = excluded.pagina_pdf,
  accion = 'conservado sin modificacion por coincidencia con la fuente original';

commit;
`;

fs.writeFileSync(outputPath, sql);

console.log(JSON.stringify({
  outputPath,
  mainRows: mainRows.length,
  issueRows: issueRows.length,
}, null, 2));
