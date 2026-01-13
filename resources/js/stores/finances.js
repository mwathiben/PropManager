import { defineStore } from 'pinia';

export const useFinancesStore = defineStore('finances', {
    state: () => ({
        activeTab: 'overview',

        filters: {
            search: '',
            status: '',
            paymentMethod: '',
            buildingId: null,
            propertyId: null,
            dateRange: { from: null, to: null },
        },

        selectedItems: [],

        modals: {
            invoiceDetail: { show: false, id: null, data: null, loading: false },
            paymentDetail: { show: false, id: null, data: null, loading: false },
            recordPayment: { show: false, invoiceId: null, invoice: null },
            refund: { show: false, paymentId: null, payment: null },
            matchPayment: { show: false, paymentId: null, payment: null },
            generateInvoices: { show: false },
            sendReminders: { show: false },
            refundDeposit: { show: false, leaseId: null, deposit: null },
            forfeitDeposit: { show: false, leaseId: null, deposit: null },
        },

        buildings: [],
        properties: [],
        paymentMethods: [],

        isLoading: false,
        error: null,
    }),

    getters: {
        activeFiltersCount: (state) => {
            let count = 0;
            if (state.filters.search) count++;
            if (state.filters.status) count++;
            if (state.filters.paymentMethod) count++;
            if (state.filters.buildingId) count++;
            if (state.filters.propertyId) count++;
            if (state.filters.dateRange.from || state.filters.dateRange.to) count++;
            return count;
        },

        hasActiveFilters: (state) => {
            return !!(
                state.filters.search ||
                state.filters.status ||
                state.filters.paymentMethod ||
                state.filters.buildingId ||
                state.filters.propertyId ||
                state.filters.dateRange.from ||
                state.filters.dateRange.to
            );
        },

        hasSelectedItems: (state) => state.selectedItems.length > 0,

        selectedCount: (state) => state.selectedItems.length,

        isModalOpen: (state) => {
            return Object.values(state.modals).some(modal => modal.show);
        },
    },

    actions: {
        setTab(tab) {
            this.activeTab = tab;
            this.selectedItems = [];
        },

        updateFilter(key, value) {
            if (key in this.filters) {
                this.filters[key] = value;
            }
        },

        setDateRange(from, to) {
            this.filters.dateRange = { from, to };
        },

        clearFilters() {
            this.filters = {
                search: '',
                status: '',
                paymentMethod: '',
                buildingId: null,
                propertyId: null,
                dateRange: { from: null, to: null },
            };
        },

        openModal(name, data = {}) {
            if (this.modals[name]) {
                this.modals[name] = { show: true, ...data };
            }
        },

        closeModal(name) {
            if (this.modals[name]) {
                this.modals[name] = {
                    show: false,
                    id: null,
                    data: null,
                    loading: false,
                };
            }
        },

        closeAllModals() {
            Object.keys(this.modals).forEach(name => {
                this.closeModal(name);
            });
        },

        setModalLoading(name, loading) {
            if (this.modals[name]) {
                this.modals[name].loading = loading;
            }
        },

        setModalData(name, data) {
            if (this.modals[name]) {
                this.modals[name].data = data;
                this.modals[name].loading = false;
            }
        },

        selectItem(id) {
            if (!this.selectedItems.includes(id)) {
                this.selectedItems.push(id);
            }
        },

        deselectItem(id) {
            const index = this.selectedItems.indexOf(id);
            if (index > -1) {
                this.selectedItems.splice(index, 1);
            }
        },

        toggleSelectItem(id) {
            if (this.selectedItems.includes(id)) {
                this.deselectItem(id);
            } else {
                this.selectItem(id);
            }
        },

        selectAll(ids) {
            this.selectedItems = [...ids];
        },

        clearSelection() {
            this.selectedItems = [];
        },

        initFromProps(props) {
            if (props.buildings) this.buildings = props.buildings;
            if (props.properties) this.properties = props.properties;
            if (props.paymentMethods) this.paymentMethods = props.paymentMethods;
            if (props.activeTab) this.activeTab = props.activeTab;
        },

        setLoading(loading) {
            this.isLoading = loading;
        },

        setError(error) {
            this.error = error;
        },

        clearError() {
            this.error = null;
        },
    },
});
