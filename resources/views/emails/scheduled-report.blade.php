@component('mail::message')
# {{ $reportName }}

Your {{ $cadence }} report is attached as an Excel spreadsheet.

@component('mail::button', ['url' => url('/reports/builder')])
Open report builder
@endcomponent

To unsubscribe from this scheduled report, visit your reports settings.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
