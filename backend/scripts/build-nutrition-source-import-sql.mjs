import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const backendRoot = path.resolve(__dirname, '..');
const dataDir = path.join(backendRoot, 'data');
const supabaseSqlDir = path.join(backendRoot, 'supabase', 'sql');

const sourceConfigs = {
  incap: {
    csvPath: path.join(dataDir, 'incap_extraido_validado_normalizado.csv'),
    outputPath: path.join(supabaseSqlDir, '20260530_import_incap_foods.generated.sql'),
    expectedRows: 1448,
    source: {
      code: 'incap',
      name: 'Tabla de Composicion de Alimentos INCAP',
      country: 'Centroamerica',
      version: 'validada',
      description: 'Fuente nutricional INCAP importada desde CSV validado.',
      metadata: { importer: 'nutrition_multi_source', expected_records: 1448 },
    },
    columns: {
      external_code: 'codigo_incap',
      category: 'categoria',
      name: 'alimento',
      portion_label: 'porcion',
      weight_g: 'peso_g',
      energy_kcal: 'energia_kcal',
      carbohydrates_g: 'carbohidratos_g',
      protein_g: 'proteina_g',
      fat_g: 'grasa_g',
      fiber_g: 'fibra_g',
      calcium_mg: 'calcio_mg',
      iron_mg: 'hierro_mg',
      cholesterol_mg: 'colesterol_mg',
      sodium_mg: 'sodio_mg',
      vitamin_c_mg: 'vitamina_c_mg',
      zinc_mg: 'zinc_mg',
      source_page: 'pagina_pdf',
    },
  },
};

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

    if (char === '"') quoted = true;
    else if (char === ',') {
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
  if (value === '' || value === null || value === undefined || String(value).toLowerCase() === 'nan') return 'null';
  return `'${String(value).replaceAll("'", "''")}'`;
};

const sqlNumber = (value) => {
  if (value === '' || value === null || value === undefined || String(value).toLowerCase() === 'nan') return 'null';
  return String(value);
};

const normalizeName = (value) => String(value || '')
  .normalize('NFD')
  .replace(/\p{Diacritic}/gu, '')
  .toLowerCase()
  .replace(/\s+/g, ' ')
  .trim();

const categorySort = (category) => {
  const match = String(category || '').match(/^(\d+)/);
  return match ? Number(match[1]) : null;
};

const categoryCode = (category) => {
  const match = String(category || '').match(/^(\d+)/);
  return match ? match[1] : null;
};

const buildImport = (config) => {
  const rows = parseCsv(fs.readFileSync(config.csvPath, 'utf8'));
  if (rows.length !== config.expectedRows) {
    throw new Error(`Expected ${config.expectedRows} rows for ${config.source.code}, got ${rows.length}`);
  }

  const categoryMap = new Map();
  for (const row of rows) {
    const name = row[config.columns.category];
    if (!categoryMap.has(name)) {
      categoryMap.set(name, {
        code: categoryCode(name),
        name,
        sortOrder: categorySort(name),
      });
    }
  }

  const categoryValues = [...categoryMap.values()].map((category) => `(
  ${sqlString(category.code)},
  ${sqlString(category.name)},
  ${sqlNumber(category.sortOrder)}
)`);

  const foodValues = rows.map((row) => {
    const get = (key) => row[config.columns[key]];
    const raw = JSON.stringify(row);

    return `(
  ${sqlString(get('category'))},
  ${sqlString(get('external_code'))},
  ${sqlString(get('name'))},
  ${sqlString(normalizeName(get('name')))},
  ${sqlString(get('portion_label'))},
  ${sqlNumber(get('weight_g'))},
  ${sqlNumber(get('energy_kcal'))},
  ${sqlNumber(get('carbohydrates_g'))},
  ${sqlNumber(get('protein_g'))},
  ${sqlNumber(get('fat_g'))},
  ${sqlNumber(get('fiber_g'))},
  ${sqlNumber(get('calcium_mg'))},
  ${sqlNumber(get('iron_mg'))},
  ${sqlNumber(get('cholesterol_mg'))},
  ${sqlNumber(get('sodium_mg'))},
  ${sqlNumber(get('vitamin_c_mg'))},
  ${sqlNumber(get('zinc_mg'))},
  ${sqlNumber(get('source_page'))},
  ${sqlString(raw)}::jsonb
)`;
  });

  return `begin;

with source as (
  insert into public.nutrition_sources (
    code,
    name,
    country,
    version,
    description,
    metadata,
    is_active
  )
  values (
    ${sqlString(config.source.code)},
    ${sqlString(config.source.name)},
    ${sqlString(config.source.country)},
    ${sqlString(config.source.version)},
    ${sqlString(config.source.description)},
    ${sqlString(JSON.stringify(config.source.metadata))}::jsonb,
    true
  )
  on conflict (code) do update
  set
    name = excluded.name,
    country = excluded.country,
    version = excluded.version,
    description = excluded.description,
    metadata = public.nutrition_sources.metadata || excluded.metadata,
    is_active = excluded.is_active,
    updated_at = now()
  returning id
)
insert into public.food_categories (
  source_id,
  code,
  name,
  sort_order
)
select
  source.id,
  category_rows.code,
  category_rows.name,
  category_rows.sort_order
from source
cross join (
  values
${categoryValues.join(',\n')}
) as category_rows(code, name, sort_order)
on conflict (source_id, name) do update
set
  code = excluded.code,
  sort_order = excluded.sort_order,
  updated_at = now();

with source as (
  select id from public.nutrition_sources where code = ${sqlString(config.source.code)}
)
insert into public.foods (
  source_id,
  category_id,
  external_code,
  name,
  normalized_name,
  portion_label,
  weight_g,
  energy_kcal,
  carbohydrates_g,
  protein_g,
  fat_g,
  fiber_g,
  calcium_mg,
  iron_mg,
  cholesterol_mg,
  sodium_mg,
  vitamin_c_mg,
  zinc_mg,
  source_page,
  raw_data
)
select
  source.id,
  category.category_id,
  food_rows.external_code,
  food_rows.name,
  food_rows.normalized_name,
  food_rows.portion_label,
  food_rows.weight_g,
  food_rows.energy_kcal,
  food_rows.carbohydrates_g,
  food_rows.protein_g,
  food_rows.fat_g,
  food_rows.fiber_g,
  food_rows.calcium_mg,
  food_rows.iron_mg,
  food_rows.cholesterol_mg,
  food_rows.sodium_mg,
  food_rows.vitamin_c_mg,
  food_rows.zinc_mg,
  food_rows.source_page,
  food_rows.raw_data
from source
cross join (
  values
${foodValues.join(',\n')}
) as food_rows(
  category_name,
  external_code,
  name,
  normalized_name,
  portion_label,
  weight_g,
  energy_kcal,
  carbohydrates_g,
  protein_g,
  fat_g,
  fiber_g,
  calcium_mg,
  iron_mg,
  cholesterol_mg,
  sodium_mg,
  vitamin_c_mg,
  zinc_mg,
  source_page,
  raw_data
)
cross join lateral (
  select id as category_id
  from public.food_categories
  where source_id = source.id and name = food_rows.category_name
) category
on conflict (source_id, external_code) do update
set
  category_id = excluded.category_id,
  name = excluded.name,
  normalized_name = excluded.normalized_name,
  portion_label = excluded.portion_label,
  weight_g = excluded.weight_g,
  energy_kcal = excluded.energy_kcal,
  carbohydrates_g = excluded.carbohydrates_g,
  protein_g = excluded protein_g,
  fat_g = excluded.fat_g,
  fiber_g = excluded.fiber_g,
  calcium_mg = excluded.calcium_mg,
  iron_mg = excluded.iron_mg,
  cholesterol_mg = excluded.cholesterol_mg,
  sodium_mg = excluded.sodium_mg,
  vitamin_c_mg = excluded.vitamin_c_mg,
  zinc_mg = excluded.zinc_mg,
  source_page = excluded.source_page,
  raw_data = excluded.raw_data,
  updated_at = now();

commit;
`;
};

const sourceCode = process.argv[2] || 'incap';
const config = sourceConfigs[sourceCode];
if (!config) throw new Error(`Unknown source config: ${sourceCode}`);

const sql = buildImport(config).replace('protein_g = excluded protein_g', 'protein_g = excluded.protein_g');
fs.writeFileSync(config.outputPath, sql);

console.log(JSON.stringify({
  source: sourceCode,
  outputPath: config.outputPath,
  rows: config.expectedRows,
}, null, 2));
