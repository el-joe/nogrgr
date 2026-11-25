<x-ui::badge :tone="$language->direction === \App\Modules\Localization\Enums\TextDirection::Rtl ? 'info' : 'neutral'" dot>{{ strtoupper($language->direction->value) }}</x-ui::badge>
