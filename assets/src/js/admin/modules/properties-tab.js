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

const getPropertiesConfig = () => {
  const bootstrap = getBootstrapData();
  const config =
    bootstrap && bootstrap.propertiesConfig && typeof bootstrap.propertiesConfig === 'object'
      ? bootstrap.propertiesConfig
      : {};

  return {
    settingsEndpoint:
      typeof config.settingsEndpoint === 'string' && config.settingsEndpoint !== ''
        ? config.settingsEndpoint
        : 'properties/settings',
    aliasesEndpoint:
      typeof config.aliasesEndpoint === 'string' && config.aliasesEndpoint !== ''
        ? config.aliasesEndpoint
        : 'properties/aliases',
    syncEndpoint:
      typeof config.syncEndpoint === 'string' && config.syncEndpoint !== ''
        ? config.syncEndpoint
        : 'properties/sync',
  };
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

const emptySummary = () => ({
  created: 0,
  updated: 0,
  deactivated: 0,
  unchanged: 0,
  skipped: 0,
  total_seen: 0,
});

const emptySyncState = () => ({
  last_started_at: 0,
  last_finished_at: 0,
  last_started_human: 'Not available',
  last_finished_human: 'Not available',
  last_status: 'idle',
  last_error: '',
  summary: emptySummary(),
});

const normalizeSyncState = (state) => {
  const input = state && typeof state === 'object' ? state : {};
  const summaryInput = input.summary && typeof input.summary === 'object' ? input.summary : {};

  return {
    last_started_at: Number.isFinite(Number(input.last_started_at)) ? Number(input.last_started_at) : 0,
    last_finished_at: Number.isFinite(Number(input.last_finished_at)) ? Number(input.last_finished_at) : 0,
    last_started_human:
      typeof input.last_started_human === 'string' && input.last_started_human !== ''
        ? input.last_started_human
        : 'Not available',
    last_finished_human:
      typeof input.last_finished_human === 'string' && input.last_finished_human !== ''
        ? input.last_finished_human
        : 'Not available',
    last_status:
      typeof input.last_status === 'string' && input.last_status !== '' ? input.last_status : 'idle',
    last_error:
      typeof input.last_error === 'string' && input.last_error !== '' ? input.last_error : '',
    summary: {
      created: Number.isFinite(Number(summaryInput.created)) ? Number(summaryInput.created) : 0,
      updated: Number.isFinite(Number(summaryInput.updated)) ? Number(summaryInput.updated) : 0,
      deactivated: Number.isFinite(Number(summaryInput.deactivated))
        ? Number(summaryInput.deactivated)
        : 0,
      unchanged: Number.isFinite(Number(summaryInput.unchanged)) ? Number(summaryInput.unchanged) : 0,
      skipped: Number.isFinite(Number(summaryInput.skipped)) ? Number(summaryInput.skipped) : 0,
      total_seen: Number.isFinite(Number(summaryInput.total_seen)) ? Number(summaryInput.total_seen) : 0,
    },
  };
};

const normalizeAliasRows = (rows) => {
  if (!Array.isArray(rows)) {
    return [];
  }

  return rows
    .filter((row) => row && typeof row === 'object' && typeof row.key === 'string' && row.key !== '')
    .map((row) => ({
      key: row.key,
      default_label:
        typeof row.default_label === 'string' && row.default_label !== '' ? row.default_label : row.key,
      alias: typeof row.alias === 'string' ? row.alias : '',
      effective_label:
        typeof row.effective_label === 'string' && row.effective_label !== ''
          ? row.effective_label
          : typeof row.default_label === 'string' && row.default_label !== ''
            ? row.default_label
            : row.key,
    }));
};

export default function propertiesTab() {
  return {
    isLoading: false,
    isSavingAliases: false,
    isSyncing: false,
    syncProgressMessage: 'Preparing Barefoot sync request...',
    aliasRows: [],
    syncState: emptySyncState(),
    init() {
      this.loadSettings();
    },
    get summary() {
      return this.syncState.summary || emptySummary();
    },
    statusLabel() {
      const labels = {
        idle: 'Idle',
        running: 'Running',
        success: 'Success',
        error: 'Error',
      };

      return labels[this.syncState.last_status] || 'Idle';
    },
    hydrate(payload) {
      const data = payload && payload.data && typeof payload.data === 'object' ? payload.data : {};
      this.aliasRows = normalizeAliasRows(data.alias_rows);
      this.syncState = normalizeSyncState(data.sync_state);
    },
    async loadSettings() {
      if (this.isLoading) {
        return;
      }

      this.isLoading = true;

      try {
        const config = getPropertiesConfig();
        const response = await requestJson(config.settingsEndpoint, {
          method: 'GET',
        });

        this.hydrate(response);
      } catch (error) {
        dispatchAlert({
          variant: 'error',
          title: 'Load failed',
          replace: true,
          message:
            error instanceof Error && error.message
              ? error.message
              : 'Unable to load property settings.',
        });
      } finally {
        this.isLoading = false;
      }
    },
    updateAlias(index, value) {
      const row = this.aliasRows[index];
      if (!row) {
        return;
      }

      row.alias = value;
      row.effective_label = value.trim() !== '' ? value.trim() : row.default_label;
    },
    buildAliasPayload() {
      return this.aliasRows.reduce((aliases, row) => {
        if (!row || typeof row.key !== 'string') {
          return aliases;
        }

        const alias = typeof row.alias === 'string' ? row.alias.trim() : '';
        if (alias !== '') {
          aliases[row.key] = alias;
        }

        return aliases;
      }, {});
    },
    async saveAliases() {
      if (this.isSavingAliases || this.isLoading) {
        return;
      }

      this.isSavingAliases = true;

      try {
        const config = getPropertiesConfig();
        const response = await requestJson(config.aliasesEndpoint, {
          method: 'POST',
          body: JSON.stringify({
            aliases: this.buildAliasPayload(),
          }),
        });

        this.hydrate(response);

        dispatchAlert({
          variant: 'success',
          title: 'Aliases saved',
          replace: true,
          message:
            (response && typeof response.message === 'string' && response.message) ||
            'Property aliases saved successfully.',
        });
      } catch (error) {
        dispatchAlert({
          variant: 'error',
          title: 'Save failed',
          replace: true,
          message:
            error instanceof Error && error.message
              ? error.message
              : 'Unable to save property aliases.',
        });
      } finally {
        this.isSavingAliases = false;
      }
    },
    async syncProperties() {
      if (this.isSyncing || this.isLoading) {
        return;
      }

      this.isSyncing = true;
      this.syncProgressMessage = 'Fetching active properties from Barefoot and importing records...';

      try {
        const config = getPropertiesConfig();
        const response = await requestJson(config.syncEndpoint, {
          method: 'POST',
          body: JSON.stringify({}),
        });

        const settings =
          response &&
          response.data &&
          response.data.settings &&
          typeof response.data.settings === 'object'
            ? { data: response.data.settings }
            : response;

        this.hydrate(settings);

        dispatchAlert({
          variant: 'success',
          title: 'Sync complete',
          replace: true,
          message:
            (response && typeof response.message === 'string' && response.message) ||
            'Property sync completed successfully.',
        });
      } catch (error) {
        dispatchAlert({
          variant: 'error',
          title: 'Sync failed',
          replace: true,
          message:
            error instanceof Error && error.message
              ? error.message
              : 'Unable to sync properties.',
        });
      } finally {
        this.isSyncing = false;
        this.syncProgressMessage = 'Preparing Barefoot sync request...';
      }
    },
  };
}
