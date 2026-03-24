import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import { toast } from 'vue-sonner';

export function useFlashToast() {
    const page = usePage();

    watch(
        () => page.props.flash as Record<string, string | null> | undefined,
        (flash) => {
            if (!flash) return;
            if (flash.success) toast.success(flash.success);
            if (flash.warning) toast.warning(flash.warning);
            if (flash.error)   toast.error(flash.error);
            if (flash.info)    toast.info(flash.info);
        },
        { deep: true },
    );

    watch(
        () => page.props.errors as Record<string, string> | undefined,
        (errors) => {
            if (!errors) return;
            const messages = Object.values(errors).filter(Boolean);
            if (messages.length > 0) {
                toast.error(messages.length === 1 ? messages[0] : messages.join('\n'));
            }
        },
        { deep: true },
    );
}
