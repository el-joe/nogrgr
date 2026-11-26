{{-- 12 px mini icon of a payment method chip (old components/payment-method-icon). --}}
@props(['icon' => 'generic'])
@switch($icon)
    @case('card')<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>@break
    @case('visa')<span class="text-[8px] font-black tracking-[-0.5px] text-[#1a1f71]">VISA</span>@break
    @case('mastercard')<svg width="12" height="12" viewBox="0 0 38 24" aria-hidden="true"><circle cx="15" cy="12" r="10" fill="#EB001B"/><circle cx="23" cy="12" r="10" fill="#F79E1B"/><path d="M19 5a10 10 0 0 1 0 14 10 10 0 0 1 0-14z" fill="#FF5F00"/></svg>@break
    @case('amex')<span class="text-[8px] font-black tracking-[-0.5px] text-[#006fcf]">AMEX</span>@break
    @case('apple_pay')<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.7 9.05 7.4c1.3.07 2.22.74 2.98.8 1.12-.24 2.2-.93 3.38-.84 1.44.12 2.53.72 3.24 1.84-3.02 1.84-2.27 5.88.2 7.04-.57 1.38-1.31 2.75-1.8 4.04zM13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg>@break
    @case('google_pay')<span class="text-[9px] font-bold"><span class="text-[#4285F4]">G</span><span class="text-[#EA4335]">p</span><span class="text-[#FBBC04]">a</span><span class="text-[#34A853]">y</span></span>@break
    @case('mada')<span class="text-[8px] font-black text-[#006F51]">mada</span>@break
    @case('stc_pay')<span class="text-[8px] font-black text-[#6D1FEE]">STC</span>@break
    @case('knet')<span class="text-[8px] font-black text-[#00539B]">KNET</span>@break
    @case('paypal')<svg width="12" height="12" viewBox="0 0 24 24" fill="#003087" aria-hidden="true"><path d="M20.067 8.478c.492.315.844.825.974 1.42.342 1.576-.594 3.07-2.16 3.485a3.5 3.5 0 0 1-.847.1h-2.35l-.588 3.722H13l1.78-11.3h4.08c1.005 0 1.74.237 2.207.573zM8.933 4.9H5.2L3 16.9h1.985l.489-3.1h2.5c2.8 0 4.5-1.5 4.5-4.2 0-2.3-1.5-3.7-3.54-3.7z"/></svg>@break
    @case('klarna')<span class="rounded-sm bg-black px-0.5 text-[8px] font-black text-[#FFB3C7]">K</span>@break
    @case('bnpl')<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 3M3.055 11A9 9 0 1 0 21 12"/></svg>@break
    @case('bank')<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11M8 10v11M12 10v11M16 10v11M20 10v11"/></svg>@break
    @case('wallet')<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2zM16 13a1 1 0 1 1 2 0 1 1 0 0 1-2 0z"/><path d="M20 7V5a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v2"/></svg>@break
    @default<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
@endswitch
