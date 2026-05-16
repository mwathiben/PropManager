<x-mail::message>
# {{ __('pwa.digest.weekly_summary_heading') }}

{{ __('pwa.digest.greeting', ['name' => $landlord->name]) }}

**{{ __('pwa.digest.engagement_score_label') }}:** {{ $summary['engagement_score'] }}
@if(($summary['engagement_score_delta_7d'] ?? 0) !== 0)
({{ ($summary['engagement_score_delta_7d'] > 0 ? '+' : '') . $summary['engagement_score_delta_7d'] }} {{ __('pwa.digest.delta_7d_suffix') }})
@endif

@if(!empty($summary['usage_ratios']))
## {{ __('pwa.digest.usage_ratios_heading') }}

<x-mail::table>
| {{ __('pwa.digest.feature_column') }} | {{ __('pwa.digest.usage_column') }} | {{ __('pwa.digest.limit_column') }} |
| :--- | ---: | ---: |
@foreach($summary['usage_ratios'] as $row)
| {{ ucfirst($row['feature']) }} | {{ $row['usage'] }} | {{ $row['limit'] }} |
@endforeach
</x-mail::table>
@endif

**{{ __('pwa.digest.referrals_label') }}:** {{ $summary['referral_count_30d'] }}

@if(!empty($summary['current_plan_slug']))
**{{ __('pwa.digest.current_plan_label') }}:** {{ $summary['current_plan_slug'] }}
@endif

<x-mail::button :url="route('dashboard')">
{{ __('pwa.digest.cta_open_dashboard') }}
</x-mail::button>

{{ __('pwa.digest.signature', ['app' => config('app.name')]) }}

---

[{{ __('pwa.digest.opt_out_link_label') }}]({{ $optOutUrl }}) — {{ __('pwa.digest.opt_out_link_helper') }}
</x-mail::message>
