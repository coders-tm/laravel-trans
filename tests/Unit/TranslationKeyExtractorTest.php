<?php

use Nitro\Trans\Support\TranslationKeyExtractor;

it('extracts keys from single-quoted PHP __ function', function () {
    $extractor = new TranslationKeyExtractor;
    $content = "<?php echo __('Hello World'); ?>";
    $keys = $extractor->extractFromContent($content, 'php');

    expect($keys)->toBe(['Hello World']);
});

it('extracts keys from double-quoted PHP __ function', function () {
    $extractor = new TranslationKeyExtractor;
    $content = '<?php echo __("Hello World"); ?>';
    $keys = $extractor->extractFromContent($content, 'php');

    expect($keys)->toBe(['Hello World']);
});

it('extracts keys from Blade {{ __() }} syntax', function () {
    $extractor = new TranslationKeyExtractor;
    $content = '{{ __("Welcome back") }}';
    $keys = $extractor->extractFromContent($content, 'php');

    expect($keys)->toBe(['Welcome back']);
});

it('extracts multiple keys from mixed content', function () {
    $extractor = new TranslationKeyExtractor;
    $content = <<<'PHP'
    <?php
    echo __('First Key');
    echo __("Second Key");
    echo __('Third Key');
    PHP;
    $keys = $extractor->extractFromContent($content, 'php');

    expect($keys)->toBe(['First Key', 'Second Key', 'Third Key']);
});

it('extracts keys with escaped quotes', function () {
    $extractor = new TranslationKeyExtractor;
    $content = "t('user\\'s address')";
    $keys = $extractor->extractFromContent($content, 'js');

    expect($keys)->toContain("user's address");
});

it('extracts keys from JavaScript t function', function () {
    $extractor = new TranslationKeyExtractor;
    $content = "const msg = t('Submit Form');";
    $keys = $extractor->extractFromContent($content, 'js');

    expect($keys)->toBe(['Submit Form']);
});

it('extracts keys from TypeScript t function', function () {
    $extractor = new TranslationKeyExtractor;
    $content = "const msg: string = t('Dashboard Title');";
    $keys = $extractor->extractFromContent($content, 'js');

    expect($keys)->toBe(['Dashboard Title']);
});

it('filters out dynamic template literal keys', function () {
    $extractor = new TranslationKeyExtractor;
    $content = "t(\`hello \${name}\`)";
    $keys = $extractor->extractFromContent($content, 'js');

    expect($keys)->toBeEmpty();
});

it('filters out concat pattern keys', function () {
    $extractor = new TranslationKeyExtractor;
    $content = "t(someVar.concat('.suffix'))";
    $keys = $extractor->extractFromContent($content, 'js');

    expect($keys)->toBeEmpty();
});

it('normalizes placeholder format from {placeholder} to :placeholder', function () {
    $keys = [
        'Hello {name}',
        'Welcome back :user',
    ];
    $extractor = new TranslationKeyExtractor;
    $result = $extractor->processKeys($keys);

    expect($result)->toContain('Hello :name');
    expect($result)->toContain('Welcome back :user');
});

it('removes duplicate keys', function () {
    $extractor = new TranslationKeyExtractor;
    $content = <<<'PHP'
    <?php
    echo __('Hello');
    echo __("Hello");
    echo __('Hello');
    PHP;
    $keys = $extractor->extractFromContent($content, 'php');

    expect($keys)->toBe(['Hello']);
});

it('extracts keys from Vue template with t function', function () {
    $extractor = new TranslationKeyExtractor;
    $content = <<<'VUE'
    <template>
        <p>{{ t('Welcome to our app') }}</p>
    </template>
    VUE;
    $keys = $extractor->extractFromContent($content, 'js');

    expect($keys)->toBe(['Welcome to our app']);
});

it('extracts keys from mixed PHP and Blade content', function () {
    $extractor = new TranslationKeyExtractor;
    $content = <<<'BLADE'
    <div>
        <h1>{{ __('Dashboard') }}</h1>
        <p><?php echo __('Settings'); ?></p>
    </div>
    BLADE;
    $keys = $extractor->extractFromContent($content, 'php');

    expect($keys)->toContain('Dashboard');
    expect($keys)->toContain('Settings');
});
