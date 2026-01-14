import { ref, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useFormatters } from './useFormatters';
import { useStatusColors } from './useStatusColors';
import type { Invoice, PaymentMethod as PaymentMethodType } from '@/types/finances';

declare function route(name: string, params?: unknown): string;

interface PaymentMethodInfo {
    label: string;
    icon: string;
}

interface StatusInfo {
    label: string;
    description: string;
}

interface PaymentData {
    amount: number | string;
    payment_method: string;
    payment_date: string;
    reference?: string;
    notes?: string;
}

interface RefundData {
    amount: number | string;
    reason: string;
    refund_method?: string;
    notes?: string;
}

interface ActionOptions {
    onSuccess?: () => void;
    onError?: (errors: Record<string, string>) => void;
}

interface MpesaResponse {
    success: boolean;
    checkout_request_id?: string;
    message?: string;
}

interface MpesaStatusResponse {
    success: boolean;
    status: string;
    message?: string;
}

interface PaystackResponse {
    success: boolean;
    authorization_url?: string;
    message?: string;
}

export interface UsePaymentsReturn {
    isProcessing: Ref<boolean>;
    error: Ref<string | null>;
    paymentMethods: Record<string, PaymentMethodInfo>;
    invoiceStatuses: Record<string, StatusInfo>;
    refundStatuses: Record<string, StatusInfo>;
    getPaymentMethodLabel: (method: string) => string;
    getPaymentMethodIcon: (method: string) => string;
    getInvoiceStatusLabel: (status: string) => string;
    getRefundStatusLabel: (status: string) => string;
    invoiceStatusColor: (status: string) => string;
    paymentMethodColor: (method: string) => string;
    refundStatusColor: (status: string) => string;
    calculatePaymentProgress: (invoice: Invoice | null | undefined) => number;
    calculateBalance: (invoice: Invoice | null | undefined) => number;
    isFullyPaid: (invoice: Invoice | null | undefined) => boolean;
    isOverdue: (invoice: Invoice | null | undefined) => boolean;
    getDaysUntilDue: (invoice: Invoice | null | undefined) => number | null;
    formatInvoiceNumber: (invoice: Invoice | null | undefined) => string;
    formatPaymentReference: (payment: { reference?: string; mpesa_transaction_id?: string; id?: number } | null | undefined) => string;
    initiateMpesaPayment: (invoiceId: number, amount: number, phoneNumber: string) => Promise<MpesaResponse>;
    checkMpesaStatus: (checkoutRequestId: string) => Promise<MpesaStatusResponse>;
    initiatePaystackPayment: (invoiceId: number, amount: number) => Promise<PaystackResponse>;
    recordManualPayment: (invoiceId: number, paymentData: PaymentData, options?: ActionOptions) => Promise<void>;
    generateInvoices: (month: number, year: number, options?: ActionOptions) => Promise<void>;
    sendInvoice: (invoiceId: number, options?: ActionOptions) => Promise<void>;
    sendReminder: (invoiceId: number, options?: ActionOptions) => Promise<void>;
    createRefund: (paymentId: number, refundData: RefundData, options?: ActionOptions) => Promise<void>;
    downloadReceipt: (paymentId: number) => void;
    sendReceipt: (paymentId: number, options?: ActionOptions) => Promise<void>;
    downloadInvoice: (invoiceId: number) => void;
    previewInvoice: (invoiceId: number) => void;
    reissueInvoice: (invoiceId: number, options?: ActionOptions) => Promise<void>;
    voidInvoice: (invoiceId: number, reason: string, options?: ActionOptions) => Promise<void>;
    voidPayment: (paymentId: number, reason: string, options?: ActionOptions) => Promise<void>;
}

/**
 * Composable for payment-related logic and utilities
 * Consolidates payment functions from multiple files
 */
export function usePayments(): UsePaymentsReturn {
    const { formatMoney, formatDate } = useFormatters();
    const { invoiceStatusColor, paymentMethodColor, refundStatusColor } = useStatusColors();

    const isProcessing = ref(false);
    const error = ref<string | null>(null);

    /**
     * Payment method labels
     */
    const paymentMethods: Record<string, PaymentMethodInfo> = {
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
    const getPaymentMethodLabel = (method: string): string => {
        return paymentMethods[method]?.label ||
            method?.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) || '-';
    };

    /**
     * Get payment method icon name
     */
    const getPaymentMethodIcon = (method: string): string => {
        return paymentMethods[method]?.icon || 'CreditCardIcon';
    };

    /**
     * Invoice status labels
     */
    const invoiceStatuses: Record<string, StatusInfo> = {
        draft: { label: 'Draft', description: 'Not yet sent to tenant' },
        sent: { label: 'Sent', description: 'Delivered to tenant' },
        partial: { label: 'Partial', description: 'Some payment received' },
        paid: { label: 'Paid', description: 'Fully paid' },
        overdue: { label: 'Overdue', description: 'Past due date' },
    };

    /**
     * Get human-readable invoice status label
     */
    const getInvoiceStatusLabel = (status: string): string => {
        return invoiceStatuses[status]?.label ||
            status?.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) || '-';
    };

    /**
     * Refund status labels
     */
    const refundStatuses: Record<string, StatusInfo> = {
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
    const getRefundStatusLabel = (status: string): string => {
        return refundStatuses[status]?.label ||
            status?.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) || '-';
    };

    /**
     * Calculate payment progress for an invoice
     */
    const calculatePaymentProgress = (invoice: Invoice | null | undefined): number => {
        if (!invoice || !invoice.total_due) return 0;
        const paid = invoice.amount_paid || 0;
        return Math.min(100, Math.round((paid / invoice.total_due) * 100));
    };

    /**
     * Calculate remaining balance for an invoice
     */
    const calculateBalance = (invoice: Invoice | null | undefined): number => {
        if (!invoice) return 0;
        return (invoice.total_due || 0) - (invoice.amount_paid || 0);
    };

    /**
     * Check if invoice is fully paid
     */
    const isFullyPaid = (invoice: Invoice | null | undefined): boolean => {
        return invoice?.status === 'paid' || calculateBalance(invoice) <= 0;
    };

    /**
     * Check if invoice is overdue
     */
    const isOverdue = (invoice: Invoice | null | undefined): boolean => {
        if (!invoice?.due_date) return false;
        if (invoice.status === 'paid') return false;
        return new Date(invoice.due_date) < new Date();
    };

    /**
     * Get days until due or days overdue
     */
    const getDaysUntilDue = (invoice: Invoice | null | undefined): number | null => {
        if (!invoice?.due_date) return null;
        const due = new Date(invoice.due_date);
        const now = new Date();
        const diffTime = due.getTime() - now.getTime();
        return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    };

    /**
     * Format invoice number for display
     */
    const formatInvoiceNumber = (invoice: Invoice | null | undefined): string => {
        return invoice?.invoice_number || `INV-${invoice?.id || '???'}`;
    };

    /**
     * Format payment reference for display
     */
    const formatPaymentReference = (
        payment: { reference?: string; mpesa_transaction_id?: string; id?: number } | null | undefined
    ): string => {
        return payment?.reference || payment?.mpesa_transaction_id || `PAY-${payment?.id || '???'}`;
    };

    /**
     * Initiate M-Pesa STK push payment
     */
    const initiateMpesaPayment = async (
        invoiceId: number,
        amount: number,
        phoneNumber: string
    ): Promise<MpesaResponse> => {
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
            const message = err instanceof Error ? err.message : 'Payment initiation failed';
            error.value = message;
            throw err;
        } finally {
            isProcessing.value = false;
        }
    };

    /**
     * Check M-Pesa STK push status
     */
    const checkMpesaStatus = async (checkoutRequestId: string): Promise<MpesaStatusResponse> => {
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
            const message = err instanceof Error ? err.message : 'Status check failed';
            return { success: false, status: 'error', message };
        }
    };

    /**
     * Initiate Paystack payment
     */
    const initiatePaystackPayment = async (
        invoiceId: number,
        amount: number
    ): Promise<PaystackResponse> => {
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
            const message = err instanceof Error ? err.message : 'Payment initiation failed';
            error.value = message;
            throw err;
        } finally {
            isProcessing.value = false;
        }
    };

    /**
     * Record a manual payment (cash/bank transfer)
     */
    const recordManualPayment = (
        invoiceId: number,
        paymentData: PaymentData,
        options: ActionOptions = {}
    ): Promise<void> => {
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
                    if (options.onError) options.onError(errors as Record<string, string>);
                },
            });
        });
    };

    /**
     * Generate invoices for active leases
     */
    const generateInvoices = (
        month: number,
        year: number,
        options: ActionOptions = {}
    ): Promise<void> => {
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
                    if (options.onError) options.onError(errors as Record<string, string>);
                },
            });
        });
    };

    /**
     * Send invoice (changes status from draft to sent, triggers email)
     */
    const sendInvoice = (invoiceId: number, options: ActionOptions = {}): Promise<void> => {
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
                    if (options.onError) options.onError(errors as Record<string, string>);
                },
            });
        });
    };

    /**
     * Send payment reminder
     */
    const sendReminder = (invoiceId: number, options: ActionOptions = {}): Promise<void> => {
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
                    if (options.onError) options.onError(errors as Record<string, string>);
                },
            });
        });
    };

    /**
     * Create a refund request
     */
    const createRefund = (
        paymentId: number,
        refundData: RefundData,
        options: ActionOptions = {}
    ): Promise<void> => {
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
                    if (options.onError) options.onError(errors as Record<string, string>);
                },
            });
        });
    };

    /**
     * Download payment receipt
     */
    const downloadReceipt = (paymentId: number): void => {
        window.open(route('payments.receipt', paymentId), '_blank');
    };

    /**
     * Send payment receipt via email
     */
    const sendReceipt = (paymentId: number, options: ActionOptions = {}): Promise<void> => {
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
                    if (options.onError) options.onError(errors as Record<string, string>);
                },
            });
        });
    };

    /**
     * Download invoice PDF
     */
    const downloadInvoice = (invoiceId: number): void => {
        window.open(route('invoices.download', invoiceId), '_blank');
    };

    /**
     * Preview invoice PDF in browser
     */
    const previewInvoice = (invoiceId: number): void => {
        window.open(route('invoices.preview', invoiceId), '_blank');
    };

    /**
     * Reissue a voided invoice
     */
    const reissueInvoice = (invoiceId: number, options: ActionOptions = {}): Promise<void> => {
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
                    if (options.onError) options.onError(errors as Record<string, string>);
                },
            });
        });
    };

    /**
     * Void an invoice
     */
    const voidInvoice = (
        invoiceId: number,
        reason: string,
        options: ActionOptions = {}
    ): Promise<void> => {
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
                    if (options.onError) options.onError(errors as Record<string, string>);
                },
            });
        });
    };

    /**
     * Void a payment
     */
    const voidPayment = (
        paymentId: number,
        reason: string,
        options: ActionOptions = {}
    ): Promise<void> => {
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
                    if (options.onError) options.onError(errors as Record<string, string>);
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
