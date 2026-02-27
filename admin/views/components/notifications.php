<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<section x-data class="be-admin-notifications mb-5" aria-label="<?php echo esc_attr__('Notifications', 'barefoot-engine'); ?>">
    <div class="be-columns be-notifications-list" aria-live="assertive" aria-atomic="true">
        <template x-for="alert in $store.beNotifications.alerts" :key="alert.id">
            <div
                x-show="alert.visible"
                x-transition.opacity.duration.200ms
                class="be-alert be-alert-default"
                :class="$store.beNotifications.alertPanelClass(alert.variant)"
                role="alert"
            >
                <div class="be-rows be-alert-row">
                    <div class="be-alert-icon-wrap">
                        <svg class="be-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path x-show="alert.variant === 'success'" stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                            <path x-show="alert.variant === 'error'" stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3Z" />
                            <path x-show="alert.variant === 'info'" stroke-linecap="round" stroke-linejoin="round" d="M12 8h.01M11 12h1v4h1m-9 0a9 9 0 1 0 18 0 9 9 0 0 0-18 0Z" />
                        </svg>
                    </div>

                    <div class="be-columns be-alert-content">
                        <p class="be-heading be-paragraph be-alert-title" x-text="alert.title"></p>
                        <p class="be-paragraph be-alert-message" x-text="alert.message"></p>
                    </div>

                    <button
                        type="button"
                        class="be-alert-dismiss"
                        @click="$store.beNotifications.dismissAlert(alert.id)"
                        aria-label="<?php echo esc_attr__('Dismiss alert', 'barefoot-engine'); ?>"
                    >
                        <svg class="be-alert-dismiss-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </template>
    </div>
</section>
