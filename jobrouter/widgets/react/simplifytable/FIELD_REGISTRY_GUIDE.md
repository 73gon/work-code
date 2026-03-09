# Field Registry Guide

## Table of Contents

- [Overview](#overview)
- [Filter Type References](#filter-type-references)
- [How to Add a New Field](#how-to-add-a-new-field)

---

## Overview

The **Declarative Field Registry** is a single source of truth that drives columns, filters, URL serialization, and backend query logic across the SimplifyTable widget.

**Before:** Adding a new field required ~14 scattered edits across 5 files (types, constants, context, data-table, query.php, init.php).

**Now:** Most fields need **3–6 single-line additions** in just 3–4 files.

---

## Filter Type References

When adding a filter to `getFilterDefs()` in [query.php](src/backend/query.php), choose the appropriate filter type:

| Type         | SQL Logic                      | Use For                            | Example                               |
| ------------ | ------------------------------ | ---------------------------------- | ------------------------------------- |
| `text_like`  | `LOWER(col) LIKE '%val%'`      | Free-text search, partial matching | Kreditor, Rechnungsnummer, DokumentId |
| `equality`   | `col = 'val'`                  | Exact match dropdown               | Rechnungstyp, Weiterbelasten          |
| `number_gte` | `col >= val`                   | Number range (from/minimum)        | Bruttobetrag From                     |
| `number_lte` | `col <= val`                   | Number range (to/maximum)          | Bruttobetrag To                       |
| `date_gte`   | `col >= 'val'`                 | Date range (from/start date)       | Rechnungsdatum From                   |
| `date_lte`   | `col <= 'val'`                 | Date range (to/end date)           | Rechnungsdatum To                     |
| `boolean_10` | `col = 1` OR `col = 0 OR NULL` | Ja/Nein mapped to 1/0              | Kostenübernahme, Weiterbelasten       |

### Special Filters (Manual SQL)

These filters have complex logic and are handled separately in `buildWhereClauses()`:

| Filter     | Logic                                                    | Location                              |
| ---------- | -------------------------------------------------------- | ------------------------------------- |
| `status`   | Complex date comparison with `TRY_CONVERT` + `GETDATE()` | Special case in `buildWhereClauses()` |
| `laufzeit` | DATEDIFF-based ranges with pattern matching              | Special case in `buildWhereClauses()` |
| `coor`     | Boolean flag with Ja/Nein mapping                        | Special case in `buildWhereClauses()` |

### Multi-Select Filters (IN-List)

Use `getListFilterDefs()` for filters that support multiple selections:

| Filter         | DB Column         | Cast to Int? |
| -------------- | ----------------- | ------------ |
| `gesellschaft` | `mandantnr`       | false        |
| `fonds`        | `fond_abkuerzung` | false        |
| `schritt`      | `step`            | true         |

---

## How to Add a New Field

### Example: Adding `priority` field (text column, dropdown filter)

**Requirement:** Add a new "Priorität" column with a dropdown filter offering: Hoch, Mittel, Niedrig.

### Step 1: Frontend Registry

**File:** [src/lib/field-registry.ts](src/lib/field-registry.ts)

Add a new object to the `FIELD_REGISTRY` array:

```ts
{
  id: 'priority',
  label: 'Priorität',
  type: 'text',
  align: 'center',
  filter: {
    type: 'dropdown',
    key: 'priority',
    defaultValue: 'all',
    optionsKey: 'priority'
  }
},
```

### Step 2: Fallback Dropdown Options

**File:** [src/lib/constants.ts](src/lib/constants.ts)

Add dropdown options to `DEFAULT_DROPDOWN_OPTIONS`:

```ts
priority: [
  { id: 'Hoch', label: 'Hoch' },
  { id: 'Mittel', label: 'Mittel' },
  { id: 'Niedrig', label: 'Niedrig' },
],
```

(Fallback for if the backend dropdown cache is unavailable; normally these come from `init.php`.)

### Step 3: Backend Column Definition

**File:** [src/backend/init.php](src/backend/init.php)

Add to `getColumns()` method:

```php
['id' => 'priority', 'label' => 'Priorität', 'type' => 'text', 'align' => 'center'],
```

Add to `fetchDropdownOptionsFromDB()` return array:

```php
'priority' => [
    ['id' => 'Hoch', 'label' => 'Hoch'],
    ['id' => 'Mittel', 'label' => 'Mittel'],
    ['id' => 'Niedrig', 'label' => 'Niedrig'],
],
```

### Step 4: Backend Query Logic

**File:** [src/backend/query.php](src/backend/query.php)

Add to `getFieldMap()` method (maps frontend ID → DB column):

```php
'priority' => 'priority_column_in_db',
```

Add to `getFilterDefs()` method (defines filter type and SQL logic):

```php
'priority' => ['priority_column_in_db', 'equality'],
```

### Done ✓

The following are automatically handled by the registry and **do NOT require changes**:

- ✅ `types.ts` — `Filters` type is `Record<string, string | string[]>`
- ✅ `simplify-table-context.tsx` — `getDefaultFilters()` and `buildFilterConfigFromRegistry()` handle the rest
- ✅ `data-table.tsx` — columns loop over registry
- ✅ `filter-bar.tsx` — filter controls generated from registry config
- ✅ `buildWhereClauses()` — loops over `getFilterDefs()` by filter type
- ✅ `mapRow()` — loops over `getFieldMap()` to populate results

---

## Variants: Column WITHOUT Filter

If you want to add a column **without any filter**:

1. Add entry to `FIELD_REGISTRY` (omit `filter` property)
2. Add entry to `init.php` `getColumns()`
3. Add entry to `query.php` `getFieldMap()`

**Note:** No `getFilterDefs()` entry needed, and no `DEFAULT_DROPDOWN_OPTIONS` in constants.ts.

---

## Adding a Multi-Select Filter

If you need a filter that supports multiple selections (like Gesellschaft, Fonds, Schritt):

**Frontend registry:**

```ts
{
  id: 'myMultiField',
  label: 'My Multi Field',
  type: 'text',
  align: 'left',
  filter: {
    type: 'multi_select',
    key: 'myMultiField',
    defaultValue: [],
    optionsKey: 'myMultiField',
  },
},
```

**Backend query.php:**

Add to `getListFilterDefs()` instead of `getFilterDefs()`:

```php
'myMultiField' => ['my_db_column', false], // false = don't cast to int; true = cast to int
```

The rest is the same (init.php columns, field map, dropdown options).

---

## Common Mistakes

❌ **Don't:**

- Manually add filters to `buildWhereClauses()` — use `getFilterDefs()` or `getListFilterDefs()`
- Manually add fields to `simplify-table-context.tsx` — derived from registry
- Forget to update `init.php` `fetchDropdownOptionsFromDB()` — this is what users see in the filter UI
- Add to `types.ts` — it's now a generic `Record` type

✅ **Do:**

- Always define filters in the registry first (frontend)
- Always add a `getFieldMap()` entry (for sorting + display)
- Keep `DEFAULT_DROPDOWN_OPTIONS` in sync with backend for resilience
- Use the correct filter type from the [Filter Type References](#filter-type-references) table

---

## File Checklist for Adding a Field

- [ ] [src/lib/field-registry.ts](src/lib/field-registry.ts) — Add to `FIELD_REGISTRY`
- [ ] [src/lib/constants.ts](src/lib/constants.ts) — Add to `DEFAULT_DROPDOWN_OPTIONS` (if dropdown)
- [ ] [src/backend/init.php](src/backend/init.php) — Add to `getColumns()` + `fetchDropdownOptionsFromDB()`
- [ ] [src/backend/query.php](src/backend/query.php) — Add to `getFieldMap()` + `getFilterDefs()` or `getListFilterDefs()`
- [ ] `npm run build` — Verify TypeScript compilation passes

---

## Questions?

Refer to the existing implementations in the registry files. The structure is consistent, and patterns are reusable across all field types.
