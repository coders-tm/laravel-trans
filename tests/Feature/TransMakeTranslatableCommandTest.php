<?php

use Illuminate\Support\Facades\File;

it('wraps plain text in Blade <p> tags with __()', function () {
    $file = $this->factory()->blade('test.blade.php', '<p>Hello World</p>');

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain('{{ __("Hello World") }}');
});

it('wraps plain text in Blade <span> tags with __()', function () {
    $file = $this->factory()->blade('test.blade.php', '<span>Click here</span>');

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain('{{ __("Click here") }}');
});

it('translates Blade placeholder attribute', function () {
    $file = $this->factory()->blade('test.blade.php', '<input placeholder="Enter your name">');

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain('{{ __("Enter your name") }}');
});

it('translates Blade alt attribute', function () {
    $file = $this->factory()->blade('test.blade.php', '<img alt="Profile photo">');

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain('{{ __("Profile photo") }}');
});

it('does not translate inside <script> tags in Blade', function () {
    $file = $this->factory()->blade('test.blade.php', '<p>Hello</p><script>var x = "World";</script>');

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain('{{ __("Hello") }}');
    expect($content)->toContain('var x = "World";');
});

it('does not translate inside <style> tags in Blade', function () {
    $file = $this->factory()->blade('test.blade.php', '<p>Hello</p><style>.a { color: red; }</style>');

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain('{{ __("Hello") }}');
    expect($content)->toContain('.a { color: red; }');
});

it('does not translate inside Blade {{ }} echo', function () {
    $file = $this->factory()->blade('test.blade.php', '<p>{{ $name }} Hello</p>');

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain('{{ $name }}');
    expect($content)->toContain('{{ __("Hello") }}');
});

it('does not translate inside {!! !!} raw echo', function () {
    $file = $this->factory()->blade('test.blade.php', '<p>{!! $html !!} Welcome</p>');

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain('{!! $html !!}');
    expect($content)->toContain('{{ __("Welcome") }}');
});

it('does not translate Material Icons content', function () {
    $file = $this->factory()->blade('test.blade.php', '<span class="material-icons">home</span>');

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->not->toContain('{{ __("home") }}');
});

it('does not translate inside @if Blade directive', function () {
    $file = $this->factory()->blade('test.blade.php', "@if(true)\n<p>Conditional</p>\n@endif");

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain('@if(true)');
    expect($content)->toContain('{{ __("Conditional") }}');
    expect($content)->toContain('@endif');
});

it('does not translate @foreach Blade directive', function () {
    $file = $this->factory()->blade('test.blade.php', "@foreach(\$items as \$item)\n<p>Item</p>\n@endforeach");

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain('@foreach');
    expect($content)->toContain('@endforeach');
    expect($content)->toContain('{{ __("Item") }}');
});

it('wraps Vue template text with t()', function () {
    $file = $this->factory()->vue('test.vue', <<<'VUE'
<template>
    <p>Hello World</p>
</template>
VUE
    );

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain('{{ t("Hello World") }}');
});

it('translates Vue tag attributes (placeholder)', function () {
    $file = $this->factory()->vue('test.vue', <<<'VUE'
<template>
    <input placeholder="Enter name">
</template>
VUE
    );

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain("t(")->toContain("Enter name");
});

it('translates Vue tag attributes (label)', function () {
    $file = $this->factory()->vue('test.vue', <<<'VUE'
<template>
    <q-btn label="Submit">
</template>
VUE
    );

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain("t(")->toContain("Submit");
});

it('does not translate inside <script> in Vue', function () {
    $file = $this->factory()->vue('test.vue', <<<'VUE'
<template>
    <p>Hello</p>
</template>
<script setup>
const msg = 'World';
</script>
VUE
    );

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain('{{ t("Hello") }}');
    expect($content)->toContain("const msg = 'World';");
});

it('does not translate inside <style> in Vue', function () {
    $file = $this->factory()->vue('test.vue', <<<'VUE'
<template>
    <p>Hello</p>
</template>
<style scoped>
.a { color: red; }
</style>
VUE
    );

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain('{{ t("Hello") }}');
    expect($content)->toContain('.a { color: red; }');
});

it('injects useLang import in Vue script setup when translations added', function () {
    $file = $this->factory()->vue('test.vue', <<<'VUE'
<template>
    <p>Hello</p>
</template>
<script setup>
const msg = 'World';
</script>
VUE
    );

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain("import { useLang } from '@/composables/use-lang'");
});

it('processes .js files - wraps label key with t()', function () {
    $file = $this->factory()->js('test.js', "{ label: 'Submit Form' }");

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain('label: t("Submit Form")');
});

it('processes .js files - wraps title key with t()', function () {
    $file = $this->factory()->js('test.js', "{ title: 'Dashboard' }");

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain('title: t("Dashboard")');
});

it('processes .js files - wraps placeholder key with t()', function () {
    $file = $this->factory()->js('test.js', "{ placeholder: 'Search...' }");

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain('placeholder: t("Search...")');
});

it('processes .js files - wraps message key with t()', function () {
    $file = $this->factory()->js('test.js', "{ message: 'Operation successful' }");

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain('message: t("Operation successful")');
});

it('does not double wrap JS keys already using t()', function () {
    $file = $this->factory()->js('test.js', "{ label: t('Already Wrapped') }");

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain("{ label: t('Already Wrapped') }");
});

it('processes .ts files - wraps label key with t()', function () {
    $file = $this->factory()->ts('test.ts', "{ label: 'Submit Form' }");

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain('label: t("Submit Form")');
});

it('processes .ts files - wraps title key with t()', function () {
    $file = $this->factory()->ts('test.ts', "{ title: 'Settings Page' }");

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain('title: t("Settings Page")');
});

it('does not wrap non-translatable JS/TS keys', function () {
    $file = $this->factory()->js('test.js', "{ count: 5, enabled: true }");

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain('{ count: 5, enabled: true }');
});

it('processes .php files - wraps error key in array', function () {
    $file = $this->factory()->php('Test.php', "<?php\n\$data = ['error' => 'Invalid input.'];");

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain("'error' => __('Invalid input.')");
});

it('processes .php files - wraps success key in array', function () {
    $file = $this->factory()->php('Test.php', "<?php\n\$data = ['success' => 'Record saved!'];");

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain("'success' => __('Record saved!')");
});

it('processes .php files - wraps message key in array', function () {
    $file = $this->factory()->php('Test.php', "<?php\n\$data = ['message' => 'Operation completed'];");

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain("'message' => __('Operation completed')");
});

it('processes .php files - wraps with() call', function () {
    $file = $this->factory()->php('Test.php', "<?php\nreturn with('error', 'Something went wrong');");

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain("with('error', __('Something went wrong'))");
});

it('processes .php files - wraps withError() call', function () {
    $file = $this->factory()->php('Test.php', "<?php\nreturn withError('Failed to process');");

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain("withError(__('Failed to process'))");
});

it('does not translate validation rules in PHP', function () {
    $file = $this->factory()->php('Test.php', <<<'PHP'
<?php
$rules = [
    'name' => 'required|string',
    'email' => 'required|email',
];
PHP
    );

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain("'name' => 'required|string'");
    expect($content)->toContain("'email' => 'required|email'");
});

it('does not translate $request->validate() blocks', function () {
    $file = $this->factory()->php('Test.php', <<<'PHP'
<?php
$request->validate([
    'name' => 'required|string',
]);
PHP
    );

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain("'name' => 'required|string'");
});

it('does not translate double-quoted PHP keys already using __()', function () {
    $file = $this->factory()->php('Test.php', '<?php $data = ["error" => __("Already translated")];');

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain('"error" => __("Already translated")');
});

it('processes @schema content in Blade - wraps label', function () {
    $file = $this->factory()->blade('test.blade.php', "@schema(['label' => 'Full Name'])");

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain("'label' => __('Full Name')");
});

it('skips shortcodes in Blade', function () {
    $file = $this->factory()->blade('test.blade.php', '<p>[blog]</p>');

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->not->toContain('{{ __("[blog]") }}');
});

it('skips Blade comments', function () {
    $file = $this->factory()->blade('test.blade.php', '<p>Hello {{-- this is a comment --}}</p>');

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain('{{-- this is a comment --}}');
});

it('respects .translateignore file', function () {
    $ignorePath = base_path('.translateignore');
    file_put_contents($ignorePath, "test.blade.php\n");

    $file = $this->factory()->blade('test.blade.php', '<p>Ignored Content</p>');

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toBe('<p>Ignored Content</p>');

    @unlink($ignorePath);
});

it('does not modify files in dry-run mode', function () {
    $file = $this->factory()->blade('test.blade.php', '<p>Hello World</p>');
    $originalContent = file_get_contents($file);

    $this->artisan('trans:make-translatable', ['path' => $file, '--dry-run' => true])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toBe($originalContent);
});

it('handles multiple Vue template attributes', function () {
    $file = $this->factory()->vue('test.vue', <<<'VUE'
<template>
    <q-input placeholder="Email" label="Email Address" hint="We won't share this">
</template>
VUE
    );

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain('t(')->toContain("Email");
    expect($content)->toContain('t(')->toContain("Email Address");
    expect($content)->toContain('t(')->toContain("We won");
});

it('handles multiple JS object keys in single file', function () {
    $file = $this->factory()->js('test.js', <<<'JS'
{
    label: 'Submit',
    title: 'Form',
    placeholder: 'Enter value',
    description: 'A helpful description',
    message: 'Success!',
    count: 5
}
JS
    );

    $this->artisan('trans:make-translatable', ['path' => $file])
        ->assertExitCode(0);

    $content = file_get_contents($file);
    expect($content)->toContain('label: t("Submit")');
    expect($content)->toContain('title: t("Form")');
    expect($content)->toContain('placeholder: t("Enter value")');
    expect($content)->toContain('description: t("A helpful description")');
    expect($content)->toContain('message: t("Success!")');
    expect($content)->toContain('count: 5');
});
