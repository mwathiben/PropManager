import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useFormatters } from './useFormatters';
import { useStatusColors } from './useStatusColors';

/**
 * Composable for payment-related logic and utilities
 * Consolidates payment functions from multiple files
 */
export function usePayments() {
    const { formatMoney, formatDate } = useFormatters();
    const { invoiceStatusColor, paymentMethodColor, refundStatusColor } = useStatusColors();

    const isProcessing = ref(false);
    const error = ref(null);

    /**
     * Payment method labels
     */
    const paymentMethods = {
        cash: { label: 'Cash', icon: 'BanknotesIcon' },
        bank_transfer: { label: 'Bank Transfer', icon: 'BuildingLibraryIcon' },
        mobile_money: { label: 'Mobile Money', icon: 'DevicePhoneMobileIcon' },
        mpesa: { label: 'M-Pesa', icon: 'DevicePhoneMobileIcon' },
        paystack: { label: 'Paystack', icon: 'CreditCardIcon' },
        stripe: { label: 'Card', icon: 'CreditCardIcon' },
    };

    /**
     * Get human-readable payment method label
     */
    const getPaymentMethodLabel = (method) => {
        return paymentMethods[method]?.label || method?.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) || '-';
    };

    /**
     * Get payment method icon name
     */
    const getPaymentMethodIcon = (method) => {
        return paymentMethods[method]?.icon || 'CreditCardIcon';
    };

    /**
     * Invoice status labels
     */
    const invoiceStatuses = {
        draft: { label: 'Draft', description: 'Not yet sent to tenant' },
        sent: { label: 'Sent', description: 'Delivered to tenant' },
        partial: { label: 'Partial', description: 'Some payment received' },
        paid: { label: 'Paid', description: 'Fully paid' },
        overdue: { label: 'Overdue', description: 'Past due date' },
    };

    /**
     * Get human-readable invoice status label
     */
    const getInvoiceStatusLabel = (status) => {
        return invoiceStatuses[status]?.label || status?.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) || '-';
    };

    /**
     * Refund status labels
     */
    const refundStatuses = {
        pending: { label: 'Pending', description: 'Awaiting approval' },
        approved: { label: 'Approved', description: 'Approved for processing' },
        processing: { label: 'Processing', description: 'Being processed' },
        completed: { label: 'Completed', description: 'Refund issued' },
        failed: { label: 'Failed', description: 'Refund failed' },
        cancelled: { label: 'Cancelled', description: 'Refund cancelled' },
    };

    /**
     * Get human-readable refund status label
     */
    const getRefundStatusLabel = (status) => {
        return refundStatuses[status]?.label || status?.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) || '-';
    };

    /**
     * Calculate payment progress for an invoice
     */
    const calculatePaymentProgress = (invoice) => {
        if (!invoice || !invoice.total_amount) return 0;
        const paid = invoice.amount_paid || 0;
        return Math.min(100, Math.round((paid / invoice.total_amount) * 100));
    };

    /**
     * Calculate remaining balance for an invoice
     */
    const calculateBalance = (invoice) => {
        if (!invoice) return 0;
        return (invoice.total_amount || 0) - (invoice.amount_paid || 0);
    };

    /**
     * Check if invoice is fully paid
     */
    const isFullyPaid = (invoice) => {
        return invoice?.status === 'paid' || calculateBalance(invoice) <= 0;
    };

    /**
     * Check if invoice is overdue
     */
    const isOverdue = (invoice) => {
        if (!invoice?.due_date) return false;
        if (invoice.status === 'paid') return false;
        return new Date(invoice.due_date) < new Date();
    };

    /**
     * Get days until due or days overdue
     */
    const getDaysUntilDue = (invoice) => {
        if (!invoice?.due_date) return null;
        const due = new Date(invoice.due_date);
        const now = new Date();
        const diffTime = due.getTime() - now.getTime();
        return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    };

    /**
     * Format invoice number for display
     */
    const formatInvoiceNumber = (invoice) => {
        return invoice?.invoice_number || `INV-${invoice?.id || '???'}`;
    };

    /**
     * Format payment reference for display
     */
    const formatPaymentReference = (payment) => {
        return payment?.reference || payment?.mpesa_transaction_id || `PAY-${payment?.id || '???'}`;
    };

    /**
     * Initiate M-Pesa STK push payment
     */
    const initiateMpesaPayment = async (invoiceId, amount, phoneNumber) => {
        isProcessing.value = true;
        error.value = null;

        try {
            const response = await fetch(route('api.v1.tenant.payments.mpesa.initiate'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    invoice_id: invoiceId,
                    amount,
                    phone: phoneNumber,
                }),
            });

            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Payment initiation failed');
            return data;
        } catch (err) {
            error.value = err.message;
            throw err;
        } finally {
            isProcessing.value = false;
        }
    };

    /**
     * Check M-Pesa STK push status
     */
    const checkMpesaStatus = async (checkoutRequestId) => {
        try {
            const response = await fetch(route('api.v1.tenant.payments.mpesa.status'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    checkout_request_id: checkoutRequestId,
                }),
            });

            const data = await response.json();
            return data;
        } catch (err) {
            return { success: false, status: 'error', message: err.message };
        }
    };

    /**
     * Initiate Paystack payment
     */
    const initiatePaystackPayment = async (invoiceId, amount) => {
        isProcessing.value = true;
        error.value = null;

        try {
            const response = await fetch(route('api.v1.tenant.payments.paystack.initiate'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    invoice_id: invoiceId,
                    amount,
                }),
            });

            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Payment initiation failed');

            if (data.authorization_url) {
                window.location.href = data.authorization_url;
            }
            return data;
        } catch (err) {
            error.value = err.message;
            throw err;
        } finally {
            isProcessing.value = false;
        }
    };

    /**
     * Record a manual payment (cash/bank transfer)
     */
    const recordManualPayment = (invoiceId, paymentData, options = {}) => {
        isProcessing.value = true;
        error.value = null;

        return new Promise((resolve, reject) => {
            router.post(route('invoices.recordPayment', invoiceId), paymentData, {
                preserveScroll: true,
                onSuccess: () => {
                    isProcessing.value = false;
                    resolve();
                    if (options.onSuccess) options.onSuccess();
                },
                onError: (errors) => {
                    isProcessing.value = false;
                    error.value = Object.values(errors)[0] || 'Payment recording failed';
                    reject(errors);
                    if (options.onError) options.onError(errors);
                },
            });
        });
    };

    /**
     * Generate invoices for active leases
     */
    const generateInvoices = (month, year, options = {}) => {
        isProcessing.value = true;
        error.value = null;

        return new Promise((resolve, reject) => {
            router.post(route('invoices.generate'), { month, year }, {
                preserveScroll: true,
                onSuccess: () => {
                    isProcessing.value = false;
                    resolve();
                    if (options.onSuccess) options.onSuccess();
                },
                onError: (errors) => {
                    isProcessing.value = false;
                    error.value = Object.values(errors)[0] || 'Invoice generation failed';
                    reject(errors);
                    if (options.onError) options.onError(errors);
                },
            });
        });
    };

    /**
     * Send invoice (changes status from draft to sent, triggers email)
     */
    const sendInvoice = (invoiceId, options = {}) => {
        isProcessing.value = true;
        error.value = null;

        return new Promise((resolve, reject) => {
            router.put(route('invoices.updateStatus', invoiceId), { status: 'sent' }, {
                preserveScroll: true,
                onSuccess: () => {
                    isProcessing.value = false;
                    resolve();
                    if (options.onSuccess) options.onSuccess();
                },
                onError: (errors) => {
                    isProcessing.value = false;
                    error.value = Object.values(errors)[0] || 'Failed to send invoice';
                    reject(errors);
                    if (options.onError) options.onError(errors);
                },
            });
        });
    };

    /**
     * Send payment reminder
     */
    const sendReminder = (invoiceId, options = {}) => {
        isProcessing.value = true;
        error.value = null;

        return new Promise((resolve, reject) => {
            router.post(route('invoices.send-reminder', invoiceId), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    isProcessing.value = false;
                    resolve();
                    if (options.onSuccess) options.onSuccess();
                },
                onError: (errors) => {
                    isProcessing.value = false;
                    error.value = Object.values(errors)[0] || 'Failed to send reminder';
                    reject(errors);
                    if (options.onError) options.onError(errors);
                },
            });
        });
    };

    /**
     * Create a refund request
     */
    const createRefund = (paymentId, refundData, options = {}) => {
        isProcessing.value = true;
        error.value = null;

        return new Promise((resolve, reject) => {
            router.post(route('refunds.store', paymentId), refundData, {
                preserveScroll: true,
                onSuccess: () => {
                    isProcessing.value = false;
                    resolve();
                    if (options.onSuccess) options.onSuccess();
                },
                onError: (errors) => {
                    isProcessing.value = false;
                    error.value = Object.values(errors)[0] || 'Refund creation failed';
                    reject(errors);
                    if (options.onError) options.onError(errors);
                },
            });
        });
    };

    /**
     * Download payment receipt
     */
    const downloadReceipt = (paymentId) => {
        window.open(route('payments.receipt', paymentId), '_blank');
    };

    /**
     * Send payment receipt via email
     */
    const sendReceipt = (paymentId, options = {}) => {
        isProcessing.value = true;
        error.value = null;

        return new Promise((resolve, reject) => {
            router.post(route('payments.send-receipt', paymentId), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    isProcessing.value = false;
                    resolve();
                    if (options.onSuccess) options.onSuccess();
                },
                onError: (errors) => {
                    isProcessing.value = false;
                    error.value = Object.values(errors)[0] || 'Failed to send receipt';
                    reject(errors);
                    if (options.onError) options.onError(errors);
                },
            });
        });
    };

    /**
     * Download invoice PDF
     */
    const downloadInvoice = (invoiceId) => {
        window.open(route('invoices.download', invoiceId), '_blank');
    };

    /**
     * Preview invoice PDF in browser
     */
    const previewInvoice = (invoiceId) => {
        window.open(route('invoices.preview', invoiceId), '_blank');
    };

    /**
     * Reissue a voided invoice
     */
    const reissueInvoice = (invoiceId, options = {}) => {
        isProcessing.value = true;
        error.value = null;

        return new Promise((resolve, reject) => {
            router.post(route('invoices.reissue', invoiceId), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    isProcessing.value = false;
                    resolve();
                    if (options.onSuccess) options.onSuccess();
                },
                onError: (errors) => {
                    isProcessing.value = false;
                    error.value = Object.values(errors)[0] || 'Failed to reissue invoice';
                    reject(errors);
                    if (options.onError) options.onError(errors);
                },
            });
        });
    };

    /**
     * Void an invoice
     */
    const voidInvoice = (invoiceId, reason, options = {}) => {
        isProcessing.value = true;
        error.value = null;

        return new Promise((resolve, reject) => {
            router.post(route('invoices.void', invoiceId), { reason }, {
                preserveScroll: true,
                onSuccess: () => {
                    isProcessing.value = false;
                    resolve();
                    if (options.onSuccess) options.onSuccess();
                },
                onError: (errors) => {
                    isProcessing.value = false;
                    error.value = Object.values(errors)[0] || 'Failed to void invoice';
                    reject(errors);
                    if (options.onError) options.onError(errors);
                },
            });
        });
    };

    /**
     * Void a payment
     */
    const voidPayment = (paymentId, reason, options = {}) => {
        isProcessing.value = true;
        error.value = null;

        return new Promise((resolve, reject) => {
            router.post(route('payments.void', paymentId), { reason }, {
                preserveScroll: true,
                onSuccess: () => {
                    isProcessing.value = false;
                    resolve();
                    if (options.onSuccess) options.onSuccess();
                },
                onError: (errors) => {
                    isProcessing.value = false;
                    error.value = Object.values(errors)[0] || 'Failed to void payment';
                    reject(errors);
                    if (options.onError) options.onError(errors);
                },
            });
        });
    };

    return {
        isProcessing,
        error,

        paymentMethods,
        invoiceStatuses,
        refundStatuses,

        getPaymentMethodLabel,
        getPaymentMethodIcon,
        getInvoiceStatusLabel,
        getRefundStatusLabel,

        invoiceStatusColor,
        paymentMethodColor,
        refundStatusColor,

        calculatePaymentProgress,
        calculateBalance,
        isFullyPaid,
        isOverdue,
        getDaysUntilDue,
        formatInvoiceNumber,
        formatPaymentReference,

        initiateMpesaPayment,
        checkMpesaStatus,
        initiatePaystackPayment,
        recordManualPayment,
        generateInvoices,
        sendInvoice,
        sendReminder,
        createRefund,
        downloadReceipt,
        sendReceipt,
        downloadInvoice,
        previewInvoice,
        reissueInvoice,
        voidInvoice,
        voidPayment,
    };
}
