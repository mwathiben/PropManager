/**
 * Composable for status-to-color mappings
 * Centralizes color logic from 6+ duplicate implementations
 */

type ColorClasses = string;

export interface UseStatusColorsReturn {
    invoiceStatusColor: (status: string) => ColorClasses;
    unitStatusColor: (status: string) => ColorClasses;
    unitStatusBadgeColor: (status: string) => ColorClasses;
    notificationStatusColor: (status: string) => ColorClasses;
    ticketStatusColor: (status: string) => ColorClasses;
    ticketPriorityColor: (priority: string) => ColorClasses;
    kycStatusColor: (completed: boolean) => ColorClasses;
    documentTypeColor: (type: string) => ColorClasses;
    paymentMethodColor: (method: string) => ColorClasses;
    refundStatusColor: (status: string) => ColorClasses;
    reconciliationStatusColor: (status: string) => ColorClasses;
    getStatusColor: (status: string, colorMap: Record<string, ColorClasses>, fallback?: ColorClasses) => ColorClasses;
}

export function useStatusColors(): UseStatusColorsReturn {
    /**
     * Invoice status colors
     */
    const invoiceStatusColor = (status: string): ColorClasses => {
        const colors: Record<string, ColorClasses> = {
            'draft': 'bg-gray-100 text-gray-800',
            'sent': 'bg-blue-100 text-blue-800',
            'partial': 'bg-yellow-100 text-yellow-800',
            'paid': 'bg-green-100 text-green-800',
            'overdue': 'bg-red-100 text-red-800'
        };
        return colors[status] || 'bg-gray-100 text-gray-800';
    };

    /**
     * Unit status colors
     */
    const unitStatusColor = (status: string): ColorClasses => {
        const colors: Record<string, ColorClasses> = {
            'vacant': 'bg-gray-50 border-gray-200 text-gray-600',
            'occupied': 'bg-green-50 border-green-200 text-green-700',
            'maintenance': 'bg-orange-50 border-orange-200 text-orange-700',
            'arrears': 'bg-red-50 border-red-200 text-red-700'
        };
        return colors[status] || 'bg-gray-50 border-gray-200 text-gray-600';
    };

    /**
     * Unit status badge colors (smaller badges)
     */
    const unitStatusBadgeColor = (status: string): ColorClasses => {
        const colors: Record<string, ColorClasses> = {
            'vacant': 'bg-gray-100 text-gray-800',
            'occupied': 'bg-green-100 text-green-800',
            'maintenance': 'bg-orange-100 text-orange-800',
            'arrears': 'bg-red-100 text-red-800'
        };
        return colors[status] || 'bg-gray-100 text-gray-800';
    };

    /**
     * Notification/ticket status colors
     */
    const notificationStatusColor = (status: string): ColorClasses => {
        const colors: Record<string, ColorClasses> = {
            'pending': 'bg-yellow-100 text-yellow-800',
            'sent': 'bg-blue-100 text-blue-800',
            'delivered': 'bg-green-100 text-green-800',
            'failed': 'bg-red-100 text-red-800',
            'open': 'bg-blue-100 text-blue-800',
            'in_progress': 'bg-yellow-100 text-yellow-800',
            'resolved': 'bg-green-100 text-green-800',
            'closed': 'bg-gray-100 text-gray-800'
        };
        return colors[status] || 'bg-gray-100 text-gray-800';
    };

    /**
     * Ticket status colors (full ticket lifecycle)
     */
    const ticketStatusColor = (status: string): ColorClasses => {
        const colors: Record<string, ColorClasses> = {
            'open': 'bg-yellow-100 text-yellow-800',
            'acknowledged': 'bg-blue-100 text-blue-800',
            'in_progress': 'bg-purple-100 text-purple-800',
            'resolved': 'bg-green-100 text-green-800',
            'closed': 'bg-gray-100 text-gray-800',
            'cancelled': 'bg-red-100 text-red-800'
        };
        return colors[status] || 'bg-gray-100 text-gray-800';
    };

    /**
     * Ticket priority colors
     */
    const ticketPriorityColor = (priority: string): ColorClasses => {
        const colors: Record<string, ColorClasses> = {
            'low': 'bg-gray-100 text-gray-800',
            'medium': 'bg-blue-100 text-blue-800',
            'high': 'bg-orange-100 text-orange-800',
            'urgent': 'bg-red-100 text-red-800'
        };
        return colors[priority] || 'bg-gray-100 text-gray-800';
    };

    /**
     * KYC status colors
     */
    const kycStatusColor = (completed: boolean): ColorClasses => {
        return completed
            ? 'bg-green-100 text-green-800'
            : 'bg-yellow-100 text-yellow-800';
    };

    /**
     * Document type colors
     */
    const documentTypeColor = (type: string): ColorClasses => {
        const colors: Record<string, ColorClasses> = {
            'lease_agreement': 'bg-blue-100 text-blue-800',
            'tenant_id': 'bg-green-100 text-green-800',
            'tenant_passport': 'bg-purple-100 text-purple-800',
            'bank_statement': 'bg-yellow-100 text-yellow-800',
            'payslip': 'bg-orange-100 text-orange-800',
            'reference_letter': 'bg-pink-100 text-pink-800',
            'utility_bill': 'bg-indigo-100 text-indigo-800',
            'other': 'bg-gray-100 text-gray-800'
        };
        return colors[type] || 'bg-gray-100 text-gray-800';
    };

    /**
     * Payment method colors
     */
    const paymentMethodColor = (method: string): ColorClasses => {
        const colors: Record<string, ColorClasses> = {
            'cash': 'bg-green-100 text-green-800',
            'bank_transfer': 'bg-blue-100 text-blue-800',
            'mobile_money': 'bg-orange-100 text-orange-800',
            'paystack': 'bg-cyan-100 text-cyan-800',
        };
        return colors[method] || 'bg-gray-100 text-gray-800';
    };

    /**
     * Refund status colors
     */
    const refundStatusColor = (status: string): ColorClasses => {
        const colors: Record<string, ColorClasses> = {
            'pending': 'bg-yellow-100 text-yellow-800',
            'approved': 'bg-blue-100 text-blue-800',
            'processing': 'bg-indigo-100 text-indigo-800',
            'completed': 'bg-green-100 text-green-800',
            'failed': 'bg-red-100 text-red-800',
            'cancelled': 'bg-gray-100 text-gray-800'
        };
        return colors[status] || 'bg-gray-100 text-gray-800';
    };

    /**
     * Reconciliation queue status colors
     */
    const reconciliationStatusColor = (status: string): ColorClasses => {
        const colors: Record<string, ColorClasses> = {
            'pending': 'bg-yellow-100 text-yellow-800',
            'processing': 'bg-blue-100 text-blue-800',
            'matched': 'bg-green-100 text-green-800',
            'unmatched': 'bg-orange-100 text-orange-800',
            'error': 'bg-red-100 text-red-800'
        };
        return colors[status] || 'bg-gray-100 text-gray-800';
    };

    /**
     * Generic status color getter
     */
    const getStatusColor = (
        status: string,
        colorMap: Record<string, ColorClasses>,
        fallback: ColorClasses = 'bg-gray-100 text-gray-800'
    ): ColorClasses => {
        return colorMap[status] || fallback;
    };

    return {
        invoiceStatusColor,
        unitStatusColor,
        unitStatusBadgeColor,
        notificationStatusColor,
        ticketStatusColor,
        ticketPriorityColor,
        kycStatusColor,
        documentTypeColor,
        paymentMethodColor,
        refundStatusColor,
        reconciliationStatusColor,
        getStatusColor
    };
}
