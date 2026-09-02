# Laravel Trans

A complete Laravel translation toolchain — scan, export/import CSV, clean unused keys, and auto-wrap Blade/Vue/JS/PHP content with translation helpers.

## Features

- **Scan** — Scan codebase for translation keys and update `en.json`
- **Auto-wrap** — Automatically wrap plain text in Blade, Vue, JS/TS, and PHP files with translation helpers
- **Export/Import CSV** — Export all translations to CSV for editing, then import back
- **Clean** — Remove unused keys from translation files
- **Configurable** — All scan paths, translatable attributes, and translation keys configurable via config

## Installation

```bash
composer require coderstm/laravel-trans
```

Publish the config:

```bash
php artisan vendor:publish --tag=trans-config
```

## Commands

### Scan codebase for translation keys

```bash
php artisan trans:scan
php artisan trans:scan --module=Blog
php artisan trans:scan --exclude=vendor,node_modules
php artisan trans:scan --i18n=resources/js/i18n/en.js
```

### Auto-wrap content with translation helpers

```bash
php artisan trans:make-translatable
php artisan trans:make-translatable resources/views
php artisan trans:make-translatable app/Http/Controllers/DashboardController.php
php artisan trans:make-translatable --dry-run
```

### Clean unused keys

```bash
php artisan trans:clean
php artisan trans:clean --dry-run
```

### Export/Import CSV

```bash
php artisan trans:export-csv
php artisan trans:import-csv
```

## Configuration

```php
// config/trans.php
return [
    'scan_dirs' => ['app', 'config', 'routes', 'resources/views', 'modules', 'src'],
    'scan_js_dirs' => ['resources/js', 'modules', 'src'],
    'exclude_paths' => [],
    'default_locale' => 'en',
    'php_functions' => ['__', 'trans'],
    'js_functions' => ['t'],

    // Which HTML tag attributes to auto-translate
    'translatable_attributes' => [
        'blade' => ['placeholder', 'alt', 'title'],
        'vue' => ['placeholder', 'alt', 'title', 'label', 'message', 'hint', 'action-label'],
    ],

    // Which object keys should have their values wrapped
    'make_translatable' => [
        'php_keys' => ['error', 'success', 'message', 'warning', 'title'],
        'js_keys' => ['label', 'title', 'placeholder', 'description', 'message', 'subject'],
        'schema_keys' => ['label', 'name', 'placeholder', 'info', 'help'],
    ],
];
```

### Custom Attributes

Add custom HTML attributes to auto-translate:

```php
'translatable_attributes' => [
    'blade' => ['placeholder', 'alt', 'title', 'data-tooltip'],
    'vue' => ['placeholder', 'alt', 'title', 'label', 'message', 'hint', 'action-label', 'helper-text'],
],
```

## How It Works

### Auto-wrap (trans:make-translatable)

**Blade files:**

- `<p>Hello World</p>` → `<p>{{ __("Hello World") }}</p>`
- `<input placeholder="Enter name">` → `<input placeholder="{{ __("Enter name") }}">`

**Vue files:**

- `<p>Hello World</p>` → `<p>{{ t("Hello World") }}</p>`
- `<input placeholder="Enter name">` → `<input :placeholder="t('Enter name')">`
- Auto-injects `import { useLang } from '@/composables/use-lang'`

**JS/TS files:**

- `{ label: 'Submit' }` → `{ label: t("Submit") }`

**PHP files:**

- `'error' => 'Invalid input.'` → `'error' => __('Invalid input.')`
- `with('error', 'Oops')` → `with('error', __('Oops'))`

### Safety

The command skips:

- Validation rules and `$rules` arrays
- `@if`, `@foreach`, and other Blade directives
- `<script>`, `<style>` content
- `{{ }}` and `{!! !!}` echo blocks
- Material Icons content
- Shortcodes
- Already-translated strings
- Dynamic keys containing variables

### Ignore files

Create `.translateignore` in your project root:

```
resources/views/snippets/terms.blade.php
resources/views/snippets/privacy.blade.php
```

## Testing

```bash
composer install
vendor/bin/pest
```

## License

MIT
