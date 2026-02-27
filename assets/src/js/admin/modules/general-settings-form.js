const INHERIT = 'inherit';
const DEFAULT_SIZE_VALUE = 16;
const TYPOGRAPHY_ROLES = ['header', 'label', 'body'];

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

const safeConfig = (config) => {
  const min = Number(config && config.min);
  const max = Number(config && config.max);
  const step = Number(config && config.step);

  return {
    min: Number.isFinite(min) ? min : 12,
    max: Number.isFinite(max) ? max : 72,
    step: Number.isFinite(step) ? step : 1,
  };
};

const safeHexColor = (value, fallback) => {
  if (typeof value !== 'string') {
    return fallback;
  }

  const normalized = value.trim().toLowerCase();
  if (/^#[0-9a-f]{6}$/.test(normalized)) {
    return normalized;
  }

  return fallback;
};

const normalizeFontSize = (value, config) => {
  if (value === null || value === '' || value === INHERIT) {
    return null;
  }

  const parsed = Number(value);
  if (!Number.isFinite(parsed)) {
    return null;
  }

  const integer = Math.round(parsed);
  if (integer < config.min || integer > config.max) {
    return null;
  }

  return integer;
};

const normalizeFontFamily = (value, fontOptions) => {
  if (typeof value !== 'string' || value === '') {
    return INHERIT;
  }

  if (value === INHERIT) {
    return INHERIT;
  }

  const allowed = new Set((fontOptions || []).map((option) => option.value));
  if (!allowed.has(value)) {
    return INHERIT;
  }

  return value;
};

export default function generalSettingsForm() {
  return {
    colors: {
      primary: '#111111',
      secondary: '#64748b',
      accent: '#3b82f6',
    },
    typography: {
      header_font_family: INHERIT,
      label_font_family: INHERIT,
      body_font_family: INHERIT,
      header_font_size: null,
      label_font_size: null,
      body_font_size: null,
    },
    customCss: '',
    fontOptions: [],
    config: {
      min: 12,
      max: 72,
      step: 1,
    },
    sizeValues: {
      header: DEFAULT_SIZE_VALUE,
      label: DEFAULT_SIZE_VALUE,
      body: DEFAULT_SIZE_VALUE,
    },
    isSaving: false,
    fieldErrors: {},

    init() {
      this.hydrateFromBootstrap();
    },

    getFamilyField(role) {
      return `${role}_font_family`;
    },

    getSizeField(role) {
      return `${role}_font_size`;
    },

    getTypographyErrorKey(role, type) {
      if (type === 'family') {
        return `typography.${this.getFamilyField(role)}`;
      }

      return `typography.${this.getSizeField(role)}`;
    },

    isSupportedRole(role) {
      return TYPOGRAPHY_ROLES.includes(role);
    },

    hydrateFromBootstrap() {
      const bootstrap = getBootstrapData();
      this.fontOptions = Array.isArray(bootstrap.generalFontKit)
        ? bootstrap.generalFontKit.filter(
            (option) => option && typeof option.value === 'string' && typeof option.label === 'string'
          )
        : [];

      if (!this.fontOptions.find((option) => option.value === INHERIT)) {
        this.fontOptions.unshift({ value: INHERIT, label: 'Inherit' });
      }

      this.config = safeConfig(bootstrap.generalConfig || {});

      const settings = bootstrap.generalSettings && typeof bootstrap.generalSettings === 'object'
        ? bootstrap.generalSettings
        : {};

      const colors = settings.colors && typeof settings.colors === 'object' ? settings.colors : {};
      const typography = settings.typography && typeof settings.typography === 'object' ? settings.typography : {};

      this.colors.primary = safeHexColor(colors.primary, '#111111');
      this.colors.secondary = safeHexColor(colors.secondary, '#64748b');
      this.colors.accent = safeHexColor(colors.accent, '#3b82f6');

      this.hydrateTypography(typography);
      this.customCss = typeof settings.custom_css === 'string' ? settings.custom_css : '';
    },

    hydrateTypography(typography) {
      TYPOGRAPHY_ROLES.forEach((role) => {
        const familyField = this.getFamilyField(role);
        const sizeField = this.getSizeField(role);

        this.typography[familyField] = normalizeFontFamily(typography[familyField], this.fontOptions);

        const normalizedSize = normalizeFontSize(typography[sizeField], this.config);
        this.typography[sizeField] = normalizedSize;
        this.sizeValues[role] = normalizedSize === null ? DEFAULT_SIZE_VALUE : normalizedSize;
      });
    },

    firstSelectableFont() {
      const first = this.fontOptions.find((option) => option.value !== INHERIT);
      return first ? first.value : 'inter';
    },

    clearFieldError(field) {
      if (Object.prototype.hasOwnProperty.call(this.fieldErrors, field)) {
        delete this.fieldErrors[field];
      }
    },

    fieldError(field) {
      const message = this.fieldErrors[field];
      return typeof message === 'string' ? message : '';
    },

    isFontInherited(role) {
      if (!this.isSupportedRole(role)) {
        return true;
      }

      return this.typography[this.getFamilyField(role)] === INHERIT;
    },

    toggleFontInherit(role, event) {
      if (!this.isSupportedRole(role)) {
        return;
      }

      const field = this.getFamilyField(role);
      const checked = Boolean(event.target && event.target.checked);

      if (checked) {
        this.typography[field] = INHERIT;
      } else if (this.typography[field] === INHERIT) {
        this.typography[field] = this.firstSelectableFont();
      }

      this.clearFieldError(this.getTypographyErrorKey(role, 'family'));
    },

    isSizeInherited(role) {
      if (!this.isSupportedRole(role)) {
        return true;
      }

      return this.typography[this.getSizeField(role)] === null;
    },

    sizeValue(role) {
      if (!this.isSupportedRole(role)) {
        return DEFAULT_SIZE_VALUE;
      }

      return this.sizeValues[role] ?? DEFAULT_SIZE_VALUE;
    },

    sizeLabel(role) {
      if (!this.isSupportedRole(role)) {
        return 'inherit';
      }

      const size = this.typography[this.getSizeField(role)];
      if (size === null) {
        return 'inherit';
      }

      return `${size}px`;
    },

    toggleSizeInherit(role, event) {
      if (!this.isSupportedRole(role)) {
        return;
      }

      const field = this.getSizeField(role);
      const checked = Boolean(event.target && event.target.checked);

      if (checked) {
        this.typography[field] = null;
      } else {
        const rawValue = Number(this.sizeValues[role]);
        const fallbackValue = Number.isFinite(rawValue) ? rawValue : DEFAULT_SIZE_VALUE;
        const clampedValue = Math.min(this.config.max, Math.max(this.config.min, Math.round(fallbackValue)));

        this.typography[field] = clampedValue;
        this.sizeValues[role] = clampedValue;
      }

      this.clearFieldError(this.getTypographyErrorKey(role, 'size'));
    },

    onSizeInput(role, event) {
      if (!this.isSupportedRole(role)) {
        return;
      }

      const value = Number(event.target && event.target.value);
      if (!Number.isFinite(value)) {
        return;
      }

      const clampedValue = Math.min(this.config.max, Math.max(this.config.min, Math.round(value)));

      this.sizeValues[role] = clampedValue;
      this.typography[this.getSizeField(role)] = clampedValue;
      this.clearFieldError(this.getTypographyErrorKey(role, 'size'));
    },

    hydrateFromResponse(settings) {
      if (!settings || typeof settings !== 'object') {
        return;
      }

      const colors = settings.colors && typeof settings.colors === 'object' ? settings.colors : {};
      const typography = settings.typography && typeof settings.typography === 'object' ? settings.typography : {};

      this.colors.primary = safeHexColor(colors.primary, this.colors.primary);
      this.colors.secondary = safeHexColor(colors.secondary, this.colors.secondary);
      this.colors.accent = safeHexColor(colors.accent, this.colors.accent);

      this.hydrateTypography(typography);
      this.customCss = typeof settings.custom_css === 'string' ? settings.custom_css : this.customCss;
    },

    async saveSettings() {
      if (this.isSaving) {
        return;
      }

      this.isSaving = true;

      try {
        const typographyPayload = {};

        TYPOGRAPHY_ROLES.forEach((role) => {
          typographyPayload[this.getFamilyField(role)] = this.typography[this.getFamilyField(role)];
          typographyPayload[this.getSizeField(role)] = this.typography[this.getSizeField(role)];
        });

        const payload = {
          colors: {
            primary: this.colors.primary,
            secondary: this.colors.secondary,
            accent: this.colors.accent,
          },
          typography: typographyPayload,
          custom_css: this.customCss,
        };

        const response = await requestJson('general-settings', {
          method: 'POST',
          body: JSON.stringify(payload),
        });

        this.fieldErrors = {};
        this.hydrateFromResponse(response.data);

        dispatchAlert({
          variant: 'success',
          title: 'Saved',
          replace: true,
          message:
            (response && typeof response.message === 'string' && response.message) ||
            'General settings saved successfully.',
        });
      } catch (error) {
        const fields =
          error &&
          error.payload &&
          error.payload.data &&
          typeof error.payload.data.fields === 'object' &&
          error.payload.data.fields !== null
            ? error.payload.data.fields
            : {};

        this.fieldErrors = fields;

        dispatchAlert({
          variant: 'error',
          title: 'Save failed',
          replace: true,
          message:
            error instanceof Error && error.message
              ? error.message
              : 'Unable to save General settings.',
        });
      } finally {
        this.isSaving = false;
      }
    },
  };
}
