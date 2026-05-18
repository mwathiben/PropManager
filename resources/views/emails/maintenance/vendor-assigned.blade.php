<x-mail::message>
# {{ __('maintenance.vendor_assigned.heading') }}

{{ __('maintenance.vendor_assigned.greeting', ['name' => $vendor->contact_person ?: $vendor->name]) }}

{{ __('maintenance.vendor_assigned.body', [
    'landlord' => $ticket->landlord?->name ?? config('app.name'),
    'title' => $ticket->title,
    'priority' => $ticket->priority,
]) }}

@if ($ticket->description)
**{{ __('maintenance.vendor_assigned.scope_label') }}**

{{ $ticket->description }}
@endif

@if ($note)
**{{ __('maintenance.vendor_assigned.note_label') }}**

{{ $note }}
@endif

{{ __('maintenance.vendor_assigned.contact_note') }}

{{ __('maintenance.vendor_assigned.signoff', ['app' => config('app.name')]) }}
</x-mail::message>
