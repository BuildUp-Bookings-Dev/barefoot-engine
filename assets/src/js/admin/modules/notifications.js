const DEFAULT_ALERT_TITLE = 'Notice';
const DEFAULT_ERROR_TITLE = 'Something went wrong';
const DEFAULT_INFO_MESSAGE = 'The action completed.';
const DEFAULT_ERROR_MESSAGE = 'Please try again.';

const normalizeVariant = (variant, fallback = 'info') => {
  if (variant === 'success' || variant === 'error' || variant === 'info') {
    return variant;
  }

  return fallback;
};

const getMessage = (payload, fallbackMessage) => {
  if (payload && typeof payload.message === 'string' && payload.message.trim() !== '') {
    return payload.message.trim();
  }

  return fallbackMessage;
};

const getTitle = (payload, fallbackTitle) => {
  if (payload && typeof payload.title === 'string' && payload.title.trim() !== '') {
    return payload.title.trim();
  }

  return fallbackTitle;
};

export default function registerNotifications(Alpine) {
  const existingStore = Alpine.store('beNotifications');
  if (existingStore) {
    return existingStore;
  }

  Alpine.store('beNotifications', {
    nextId: 1,
    alerts: [],
    alertPanelClass(variant) {
      const safeVariant = normalizeVariant(variant);
      const classes = {
        success: 'be-alert-success',
        error: 'be-alert-error',
        info: 'be-alert-info',
      };

      return classes[safeVariant] || classes.info;
    },
    pushAlert(payload = {}) {
      if (payload.replace === true) {
        this.alerts = [];
      }

      const id = this.nextId++;
      const variant = normalizeVariant(payload.variant, 'info');
      const fallbackMessage = variant === 'error' ? DEFAULT_ERROR_MESSAGE : DEFAULT_INFO_MESSAGE;
      const fallbackTitle = variant === 'error' ? DEFAULT_ERROR_TITLE : DEFAULT_ALERT_TITLE;

      this.alerts.push({
        id,
        variant,
        title: getTitle(payload, fallbackTitle),
        message: getMessage(payload, fallbackMessage),
        visible: true,
      });
    },
    dismissAlert(id) {
      this.alerts = this.alerts.filter((alert) => alert.id !== id);
    },
    clearAlerts() {
      this.alerts = [];
    },
  });

  if (!window.__beNotificationsEventsBound) {
    window.addEventListener('be:alert', (event) => {
      Alpine.store('beNotifications').pushAlert(event.detail || {});
    });

    window.__beNotificationsEventsBound = true;
  }

  return Alpine.store('beNotifications');
}
