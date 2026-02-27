const getBootstrapData = () => window.BarefootEngineAdmin || {};

const buildEndpoint = (path) => {
  const bootstrap = getBootstrapData();
  const base =
    typeof bootstrap.restBase === 'string' && bootstrap.restBase !== ''
      ? bootstrap.restBase
      : '/wp-json/barefoot-engine/v1/';

  return `${base.replace(/\/+$/, '')}/${path.replace(/^\/+/, '')}`;
};

const getRestNonce = () => {
  const bootstrap = getBootstrapData();
  if (typeof bootstrap.restNonce === 'string') {
    return bootstrap.restNonce;
  }

  return '';
};

const parseResponseBody = async (response) => {
  const bodyText = await response.text();
  if (!bodyText) {
    return {};
  }

  try {
    return JSON.parse(bodyText);
  } catch (error) {
    return {};
  }
};

const requestJson = async (path, options = {}) => {
  const nonce = getRestNonce();
  const headers = {
    'Content-Type': 'application/json',
    ...options.headers,
  };

  if (nonce !== '') {
    headers['X-WP-Nonce'] = nonce;
  }

  const response = await fetch(buildEndpoint(path), {
    credentials: 'same-origin',
    ...options,
    headers,
  });

  const payload = await parseResponseBody(response);
  if (!response.ok) {
    const message =
      (payload && typeof payload.message === 'string' && payload.message) ||
      (payload &&
        payload.data &&
        typeof payload.data.message === 'string' &&
        payload.data.message) ||
      'Request failed.';

    const error = new Error(message);
    error.status = response.status;
    error.payload = payload;
    throw error;
  }

  return payload;
};

const dispatchAlert = (detail) => {
  window.dispatchEvent(new CustomEvent('be:alert', { detail }));
};

export default function apiIntegrationForm() {
  return {
    api: {
      username: '',
      company_id: '',
    },
    passwordInput: '',
    hasPassword: false,
    showPassword: false,
    isSaving: false,
    isTesting: false,
    fieldErrors: {},
    init() {
      this.hydrateFromBootstrap();
    },
    hydrateFromBootstrap() {
      const bootstrap = getBootstrapData();
      const data =
        bootstrap &&
        bootstrap.apiIntegration &&
        bootstrap.apiIntegration.api &&
        typeof bootstrap.apiIntegration.api === 'object'
          ? bootstrap.apiIntegration.api
          : {};

      this.api.username =
        typeof data.username === 'string' ? data.username : '';
      this.api.company_id =
        typeof data.company_id === 'string' ? data.company_id : '';
      this.hasPassword = Boolean(data.has_password);
      this.passwordInput = '';
    },
    clearFieldError(field) {
      if (Object.prototype.hasOwnProperty.call(this.fieldErrors, field)) {
        delete this.fieldErrors[field];
      }
    },
    async saveSettings() {
      if (this.isSaving) {
        return;
      }

      this.isSaving = true;

      try {
        const payload = {
          api: {
            username: this.api.username,
            company_id: this.api.company_id,
            password: this.passwordInput,
          },
        };

        const response = await requestJson('api-integration', {
          method: 'POST',
          body: JSON.stringify(payload),
        });

        if (response && response.data && response.data.api) {
          const api = response.data.api;
          if (typeof api.username === 'string') {
            this.api.username = api.username;
          }
          if (typeof api.company_id === 'string') {
            this.api.company_id = api.company_id;
          }

          this.hasPassword = Boolean(api.has_password);
        }

        this.passwordInput = '';
        this.showPassword = false;
        this.fieldErrors = {};

        dispatchAlert({
          variant: 'success',
          title: 'Saved',
          replace: true,
          message:
            (response && typeof response.message === 'string' && response.message) ||
            'API Integration settings saved successfully.',
        });
      } catch (error) {
        dispatchAlert({
          variant: 'error',
          title: 'Save failed',
          replace: true,
          message:
            error instanceof Error && error.message
              ? error.message
              : 'Unable to save API Integration settings.',
        });
      } finally {
        this.isSaving = false;
      }
    },
    async testConnection() {
      if (this.isTesting) {
        return;
      }

      this.isTesting = true;

      try {
        const response = await requestJson('api-integration/test', {
          method: 'POST',
          body: JSON.stringify({}),
        });

        dispatchAlert({
          variant: 'success',
          title: 'Connection test',
          replace: true,
          message:
            (response && typeof response.message === 'string' && response.message) ||
            'Mock connection test succeeded.',
        });
      } catch (error) {
        dispatchAlert({
          variant: 'error',
          title: 'Connection test failed',
          replace: true,
          message:
            error instanceof Error && error.message
              ? error.message
              : 'Unable to test connection.',
        });
      } finally {
        this.isTesting = false;
      }
    },
  };
}
