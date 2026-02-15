import { ref, type Ref } from 'vue';
import { useFormatters } from './useFormatters';

interface PaymentFormState {
    invoice_id: number | null;
    amount: number | string;
    payment_method: string;
    payment_date: string;
    reference: string;
    notes: string;
}

interface UsePaymentFormOptions {
    defaultMethod?: string;
}

export interface UsePaymentFormReturn {
    form: Ref<PaymentFormState>;
    errors: Ref<Record<string, string>>;
    isSubmitting: Ref<boolean>;
    isSuccess: Ref<boolean>;
    validate: (extraValidators?: () => Record<string, string>) => boolean;
    resetForm: () => void;
    setFullAmount: (amount: number) => void;
}

export function usePaymentForm(options: UsePaymentFormOptions = {}): UsePaymentFormReturn {
    const { defaultMethod = 'cash' } = options;
    const { todayAsISODate } = useFormatters();

    const createDefaultState = (): PaymentFormState => ({
        invoice_id: null,
        amount: '',
        payment_method: defaultMethod,
        payment_date: todayAsISODate(),
        reference: '',
        notes: '',
    });

    const form = ref<PaymentFormState>(createDefaultState());
    const errors = ref<Record<string, string>>({});
    const isSubmitting = ref(false);
    const isSuccess = ref(false);

    const validate = (extraValidators?: () => Record<string, string>): boolean => {
        const newErrors: Record<string, string> = {};

        if (!form.value.amount || Number(form.value.amount) <= 0) {
            newErrors.amount = 'Please enter a valid amount';
        }

        if (!form.value.payment_method) {
            newErrors.payment_method = 'Please select a payment method';
        }

        if (!form.value.payment_date) {
            newErrors.payment_date = 'Please select a payment date';
        }

        if (extraValidators) {
            Object.assign(newErrors, extraValidators());
        }

        errors.value = newErrors;
        return Object.keys(newErrors).length === 0;
    };

    const resetForm = (): void => {
        form.value = createDefaultState();
        errors.value = {};
        isSubmitting.value = false;
        isSuccess.value = false;
    };

    const setFullAmount = (amount: number): void => {
        form.value.amount = amount;
    };

    return {
        form,
        errors,
        isSubmitting,
        isSuccess,
        validate,
        resetForm,
        setFullAmount,
    };
}
