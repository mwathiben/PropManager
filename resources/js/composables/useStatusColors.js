/**
 * Composable for status-to-color mappings
 * Centralizes color logic from 6+ duplicate implementations
 */
export function useStatusColors() {
    /**
     * Invoice status colors
     * @param {string} status - draft, sent, partial, paid, overdue
     * @returns {string} Tailwind CSS classes
     */
    const invoiceStatusColor = (status) => {
        const colors = {
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
     * @param {string} status - vacant, occupied, maintenance, arrears
     * @returns {string} Tailwind CSS classes
     */
    const unitStatusColor = (status) => {
        const colors = {
            'vacant': 'bg-gray-50 border-gray-200 text-gray-600',
            'occupied': 'bg-green-50 border-green-200 text-green-700',
            'maintenance': 'bg-orange-50 border-orange-200 text-orange-700',
            'arrears': 'bg-red-50 border-red-200 text-red-700'
        };
        return colors[status] || 'bg-gray-50 border-gray-200 text-gray-600';
    };

    /**
     * Unit status badge colors (smaller badges)
     * @param {string} status - vacant, occupied, maintenance, arrears
     * @returns {string} Tailwind CSS classes
     */
    const unitStatusBadgeColor = (status) => {
        const colors = {
            'vacant': 'bg-gray-100 text-gray-800',
            'occupied': 'bg-green-100 text-green-800',
            'maintenance': 'bg-orange-100 text-orange-800',
            'arrears': 'bg-red-100 text-red-800'
        };
        return colors[status] || 'bg-gray-100 text-gray-800';
    };

    /**
     * Notification/ticket status colors
     * @param {string} status - pending, sent, delivered, failed, open, in_progress, resolved, closed
     * @returns {string} Tailwind CSS classes
     */
    const notificationStatusColor = (status) => {
        const colors = {
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
     * Ticket priority colors
     * @param {string} priority - low, medium, high, urgent
     * @returns {string} Tailwind CSS classes
     */
    const ticketPriorityColor = (priority) => {
        const colors = {
            'low': 'bg-gray-100 text-gray-800',
            'medium': 'bg-blue-100 text-blue-800',
            'high': 'bg-orange-100 text-orange-800',
            'urgent': 'bg-red-100 text-red-800'
        };
        return colors[priority] || 'bg-gray-100 text-gray-800';
    };

    /**
     * Document type colors
     * @param {string} type - lease_agreement, tenant_id, etc.
     * @returns {string} Tailwind CSS classes
     */
    const documentTypeColor = (type) => {
        const colors = {
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
     * @param {string} method - cash, bank_transfer, mobile_money, paystack, stripe
     * @returns {string} Tailwind CSS classes
     */
    const paymentMethodColor = (method) => {
        const colors = {
            'cash': 'bg-green-100 text-green-800',
            'bank_transfer': 'bg-blue-100 text-blue-800',
            'mobile_money': 'bg-orange-100 text-orange-800',
            'paystack': 'bg-cyan-100 text-cyan-800',
            'stripe': 'bg-purple-100 text-purple-800'
        };
        return colors[method] || 'bg-gray-100 text-gray-800';
    };

    /**
     * Refund status colors
     * @param {string} status - pending, approved, processing, completed, failed, cancelled
     * @returns {string} Tailwind CSS classes
     */
    const refundStatusColor = (status) => {
        const colors = {
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
     * @param {string} status - pending, processing, matched, unmatched, error
     * @returns {string} Tailwind CSS classes
     */
    const reconciliationStatusColor = (status) => {
        const colors = {
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
     * @param {string} status - The status value
     * @param {object} colorMap - Custom color mapping object
     * @param {string} fallback - Fallback color classes
     * @returns {string} Tailwind CSS classes
     */
    const getStatusColor = (status, colorMap, fallback = 'bg-gray-100 text-gray-800') => {
        return colorMap[status] || fallback;
    };

    return {
        invoiceStatusColor,
        unitStatusColor,
        unitStatusBadgeColor,
        notificationStatusColor,
        ticketPriorityColor,
        documentTypeColor,
        paymentMethodColor,
        refundStatusColor,
        reconciliationStatusColor,
        getStatusColor
    };
}
