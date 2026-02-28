<x-mail::message :unsubscribeUrl="$unsubscribeUrl ?? null">
    # Welcome to {{ $propertyName }}!

    Hello {{ $tenant->name }},

    Your tenant account has been created. Below are your login credentials.

    ## Your Login Details

    - **Email:** {{ $tenant->email }}
    - **Temporary Password:** {{ $temporaryPassword }}

    <x-mail::button :url="$loginUrl">
        Log In Now
    </x-mail::button>

    **Important:** Please change your password immediately after logging in for security.

    ## Your Lease Details

    - **Property:** {{ $propertyName }}
    @if ($buildingName)
        - **Building:** {{ $buildingName }}
    @endif
    - **Unit:** {{ $unitNumber }}
    - **Monthly Rent:** {{ $currencySymbol }} {{ number_format($lease->rent_amount, 2) }}
    - **Security Deposit:** {{ $currencySymbol }} {{ number_format($lease->deposit_amount, 2) }}
    - **Lease Start Date:** {{ $lease->start_date->format('F d, Y') }}

    ## Your Landlord

    - **Name:** {{ $landlord->name }}
    @if ($landlord->email)
        - **Email:** {{ $landlord->email }}
    @endif
    @if ($landlord->mobile_number)
        - **Phone:** {{ $landlord->mobile_number }}
    @endif

    If you have any questions, please contact your landlord.

    Thanks,<br>
    {{ config('app.name') }} Team
</x-mail::message>
