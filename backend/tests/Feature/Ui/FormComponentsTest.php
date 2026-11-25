<?php

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/*
| x-ui::* form controls: labels, a11y wiring and the 422 error contract
| ([data-error-for="<dotted.key>"] + aria-invalid).
*/

it('renders an input with label, required marker, helper and an empty error slot', function (): void {
    $view = $this->blade('<x-ui::input name="name" label="Product name" helper="Shown on all storefronts" required icon="mail" />');

    $view->assertSee('for="name"', false)
        ->assertSee('Product name')
        ->assertSee('aria-describedby="name-helper name-error"', false)
        ->assertSee('data-error-for="name"', false)
        ->assertSee('required', false)
        ->assertSee('data-icon="mail"', false)
        ->assertDontSee('aria-invalid', false);
});

it('binds nested names to the dotted error key and shows server errors', function (): void {
    $errors = (new ViewErrorBag)->put('default', new MessageBag(['items.0.qty' => ['The quantity is required.']]));
    view()->share('errors', $errors);

    $view = $this->blade('<x-ui::input name="items[0][qty]" label="Quantity" />');

    $view->assertSee('data-error-for="items.0.qty"', false)
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('The quantity is required.');
});

it('adds a show-password toggle to password inputs', function (): void {
    $this->blade('<x-ui::input name="password" type="password" label="Password" />')
        ->assertSee('data-password-toggle="#password"', false)
        ->assertSee(__('ui.show_password'));
});

it('renders a textarea with a live character counter', function (): void {
    $this->blade('<x-ui::textarea name="description" label="Description" :maxlength="500" value="Hello" />')
        ->assertSee('data-counter="#description-count"', false)
        ->assertSee('5 / 500');
});

it('renders a Select2 select with options, placeholder and multi selection', function (): void {
    $this->blade('<x-ui::select name="countries" label="Countries" :options="[\'sa\' => \'Saudi Arabia\', \'eg\' => \'Egypt\']" :selected="[\'eg\']" multiple />')
        ->assertSee('name="countries[]"', false)
        ->assertSee('data-select', false)
        ->assertSee('multiple', false)
        ->assertSee('<option value="eg" selected>Egypt</option>', false);

    $this->blade('<x-ui::select name="category" :options="[]" placeholder="Select category" />')
        ->assertSee('data-placeholder="Select category"', false);
});

it('renders an intl-tel-input phone field that can preset a country', function (): void {
    $this->blade('<x-ui::phone name="phone" label="Phone" country="EG" />')
        ->assertSee('data-phone', false)
        ->assertSee('type="tel"', false)
        ->assertSee('data-country="eg"', false);
});

it('renders single and range date pickers with hidden ISO inputs', function (): void {
    $this->blade('<x-ui::date name="start_date" label="Start" value="2026-11-24" />')
        ->assertSee('data-mode="single"', false)
        ->assertSee('value="2026-11-24"', false)
        ->assertSee('data-date-value="single"', false);

    $this->blade('<x-ui::daterange name="period" label="Period" from="2026-11-01" to="2026-11-24" filter />')
        ->assertSee('data-mode="range"', false)
        ->assertSee('name="period_from"', false)
        ->assertSee('name="period_to"', false)
        ->assertSee('data-filter', false)
        ->assertSee(__('ui.date.clear'));
});

it('renders checkbox, radio group and switch states', function (): void {
    $this->blade('<x-ui::checkbox name="featured" label="Featured" checked unchecked-value="0" />')
        ->assertSee('class="ui-check', false)
        ->assertSee('checked', false)
        ->assertSee('<input type="hidden" name="featured" value="0">', false);

    $this->blade('<x-ui::checkbox name="mixed" label="Mixed" indeterminate />')
        ->assertSee('aria-checked="mixed"', false);

    $this->blade('<x-ui::radio-group name="mode" label="Mode" :options="[\'a\' => \'A\', \'b\' => \'B\']" value="b" />')
        ->assertSee('<legend', false)
        ->assertSee('id="mode-b"', false);

    $this->blade('<x-ui::switch name="is_active" label="Active" url="/admin/x/1" />')
        ->assertSee('role="switch"', false)
        ->assertSee('data-switch-url="/admin/x/1"', false)
        ->assertSee('data-switch-field="is_active"', false);
});

it('renders the file dropzone and the image tiles', function (): void {
    $this->blade('<x-ui::file-upload name="files" label="Files" multiple upload-url="/upload" hint="PDF · max 10 MB" />')
        ->assertSee('data-upload-url="/upload"', false)
        ->assertSee('data-upload-name="files[]"', false)
        ->assertSee('data-dropzone', false)
        ->assertSee(__('ui.upload.click'))
        ->assertSee('PDF · max 10 MB');

    $this->blade('<x-ui::image-upload name="images" label="Images" :images="[[\'id\' => 7, \'url\' => \'/a.jpg\'], [\'id\' => 8, \'url\' => \'/b.jpg\']]" :max="2" />')
        ->assertSee('name="images_keep[]" value="7"', false)
        ->assertSee(__('ui.upload.main'))
        ->assertSee('data-image-add', false);
});

it('renders one locale tab and pane per enabled language, Arabic as RTL', function (): void {
    $view = $this->blade(<<<'BLADE'
        <x-ui::locale-tabs id="i18n">
            @foreach (\App\Shared\Support\Locale::enabled() as $code => $language)
                <x-ui::locale-pane parent="i18n" :locale="$code"><x-ui::input :name="'title['.$code.']'" /></x-ui::locale-pane>
            @endforeach
        </x-ui::locale-tabs>
        BLADE);

    $view->assertSee('id="i18n-en-tab"', false)
        ->assertSee('id="i18n-ar-tab"', false)
        ->assertSee('dir="rtl"', false)
        ->assertSee('data-error-for="title.ar"', false);
});
