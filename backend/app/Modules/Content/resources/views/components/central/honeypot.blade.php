{{-- Spam trap: bots fill "website"; humans never see it. Plus the form start time. --}}
<div class="site-hp" aria-hidden="true">
    <label>{{ __('content::site.contact.honeypot') }}<input type="text" name="website" tabindex="-1" autocomplete="off" value=""></label>
    <input type="hidden" name="form_started_at" value="{{ now()->timestamp }}">
</div>
