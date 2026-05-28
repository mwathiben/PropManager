<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: tenant online payment / checkout page. Mirror en/sw/ar.
 */
return [
    'page_title' => 'Pay Invoice {invoice_number}',
    'heading' => 'Pay Invoice',
    'amount_due' => 'Amount Due',
    'unit' => 'Unit',
    'due_date' => 'Due Date',
    'breakdown' => 'Breakdown',
    'rent' => 'Rent',
    'water' => 'Water',
    'arrears' => 'Arrears',
    'paid' => 'Paid',
    'select_payment_method' => 'Select Payment Method',
    'payment_details' => 'Payment Details',
    'bank' => 'Bank:',
    'account_name' => 'Account Name:',
    'account_number' => 'Account Number:',
    'paybill' => 'Paybill:',
    'account' => 'Account:',
    'copied_to_clipboard' => 'Copied to clipboard!',
    'mpesa_phone_number' => 'M-Pesa Phone Number',
    'phone_placeholder' => '0712345678',
    'stk_push_hint' => "You'll receive an STK push to this number",
    'check_phone_for_prompt' => 'Check your phone for the M-Pesa prompt',
    'redirecting_to_finances' => 'Redirecting to your finances...',
    'try_again' => 'Try again',
    'processing' => 'Processing...',
    'pay_amount' => 'Pay {amount}',
    'cash_instruction' => 'Pay cash to your landlord or caretaker',
    'bank_transfer_instruction' => 'Transfer the amount to the account above',
    'recorded_once_confirmed' => 'Your payment will be recorded once confirmed',
    'cancel' => 'Cancel',
    'messages' => [
        'payment_received_success' => 'Payment received successfully!',
        'payment_cancelled' => 'Payment was cancelled',
        'payment_failed' => 'Payment failed',
        'sending_stk_push' => 'Sending STK push to your phone...',
        'enter_mpesa_pin' => 'Please enter your M-Pesa PIN on your phone',
        'failed_to_initiate_mpesa' => 'Failed to initiate M-Pesa request — please try again',
        'payment_timed_out' => 'Payment timed out. Please try again.',
        'payment_processing' => 'Payment is being processed...',
    ],
];
