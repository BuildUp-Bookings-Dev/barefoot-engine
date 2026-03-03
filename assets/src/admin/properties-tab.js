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
    syncEndpoint:
      typeof config.syncEndpoint === 'string' && config.syncEndpoint !== ''
        ? config.syncEndpoint
        : 'properties/sync',
    partialSyncEndpoint:
      typeof config.partialSyncEndpoint === 'string' && config.partialSyncEndpoint !== ''
        ? config.partialSyncEndpoint
        : 'properties/partial-sync',
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

const emptyProgress = () => ({
  active: false,
  mode: 'none',
  stage: 'idle',
  current: 0,
  total: 0,
  message: '',
  current_property_id: '',
  current_property_title: '',
});

const emptySyncState = () => ({
  last_started_at: 0,
  last_finished_at: 0,
  last_started_human: 'Not available',
  last_finished_human: 'Not available',
  last_status: 'idle',
  last_error: '',
  last_sync_mode: 'none',
  last_full_started_at: 0,
  last_full_finished_at: 0,
  last_full_started_human: 'Not available',
  last_full_finished_human: 'Not available',
  last_full_status: 'idle',
  summary: emptySummary(),
  progress: emptyProgress(),
});

const normalizeProgress = (progress) => {
  const input = progress && typeof progress === 'object' ? progress : {};

  return {
    active: Boolean(input.active),
    mode: typeof input.mode === 'string' && input.mode !== '' ? input.mode : 'none',
    stage: typeof input.stage === 'string' && input.stage !== '' ? input.stage : 'idle',
    current: Number.isFinite(Number(input.current)) ? Number(input.current) : 0,
    total: Number.isFinite(Number(input.total)) ? Number(input.total) : 0,
    message: typeof input.message === 'string' ? input.message : '',
    current_property_id:
      typeof input.current_property_id === 'string' ? input.current_property_id : '',
    current_property_title:
      typeof input.current_property_title === 'string' ? input.current_property_title : '',
  };
};

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
    last_sync_mode:
      typeof input.last_sync_mode === 'string' && input.last_sync_mode !== ''
        ? input.last_sync_mode
        : 'none',
    last_full_started_at: Number.isFinite(Number(input.last_full_started_at))
      ? Number(input.last_full_started_at)
      : 0,
    last_full_finished_at: Number.isFinite(Number(input.last_full_finished_at))
      ? Number(input.last_full_finished_at)
      : 0,
    last_full_started_human:
      typeof input.last_full_started_human === 'string' && input.last_full_started_human !== ''
        ? input.last_full_started_human
        : 'Not available',
    last_full_finished_human:
      typeof input.last_full_finished_human === 'string' && input.last_full_finished_human !== ''
        ? input.last_full_finished_human
        : 'Not available',
    last_full_status:
      typeof input.last_full_status === 'string' && input.last_full_status !== ''
        ? input.last_full_status
        : 'idle',
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
    progress: normalizeProgress(input.progress),
  };
};

export default function propertiesTab() {
  return {
    isLoading: false,
    isSyncing: false,
    isPolling: false,
    activeSyncMode: 'none',
    pollTimer: null,
    syncState: emptySyncState(),
    init() {
      this.loadSettings();
    },
    get summary() {
      return this.syncState.summary || emptySummary();
    },
    get progress() {
      return this.syncState.progress || emptyProgress();
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
    fullSyncButtonLabel() {
      return this.canRunPartialSync() ? 'Full Sync' : 'Sync Properties';
    },
    canRunPartialSync() {
      return this.syncState.last_full_finished_at > 0;
    },
    shouldPoll() {
      return this.syncState.last_status === 'running' || this.progress.active;
    },
    isSyncRunning() {
      return this.syncState.last_status === 'running' || this.progress.active;
    },
    isSyncCompleteState() {
      return (
        !this.isSyncRunning() &&
        this.syncState.last_status === 'success' &&
        this.progress.stage === 'complete' &&
        this.progress.total > 0
      );
    },
    shouldShowProgress() {
      return this.isSyncRunning() || this.isSyncCompleteState();
    },
    progressStateClass() {
      return {
        'is-running': this.isSyncRunning(),
        'is-complete': this.isSyncCompleteState(),
      };
    },
    hydrate(payload) {
      const data = payload && payload.data && typeof payload.data === 'object' ? payload.data : {};
      this.syncState = normalizeSyncState(data.sync_state);
      this.isSyncing = this.shouldPoll();

      if (this.shouldPoll()) {
        this.startPolling();
      } else {
        this.stopPolling();
      }
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
    startPolling() {
      if (this.pollTimer) {
        return;
      }

      this.pollTimer = window.setInterval(() => {
        void this.pollSyncState();
      }, 1000);
    },
    stopPolling() {
      if (!this.pollTimer) {
        return;
      }

      window.clearInterval(this.pollTimer);
      this.pollTimer = null;
    },
    async pollSyncState() {
      if (this.isPolling) {
        return;
      }

      this.isPolling = true;

      try {
        const config = getPropertiesConfig();
        const response = await requestJson(config.settingsEndpoint, {
          method: 'GET',
        });

        this.hydrate(response);
      } catch (error) {
        if (!this.shouldPoll()) {
          this.stopPolling();
        }
      } finally {
        this.isPolling = false;
      }
    },
    async runSync(mode = 'full') {
      if (this.isSyncing || this.isLoading) {
        return;
      }

      const config = getPropertiesConfig();
      const endpoint =
        mode === 'partial' ? config.partialSyncEndpoint : config.syncEndpoint;

      this.activeSyncMode = mode;
      this.isSyncing = true;
      this.startPolling();

      try {
        const response = await requestJson(endpoint, {
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
          title: mode === 'partial' ? 'Partial sync complete' : 'Sync complete',
          replace: true,
          message:
            (response && typeof response.message === 'string' && response.message) ||
            (mode === 'partial'
              ? 'Partial property sync completed successfully.'
              : 'Property sync completed successfully.'),
        });
      } catch (error) {
        dispatchAlert({
          variant: 'error',
          title: mode === 'partial' ? 'Partial sync failed' : 'Sync failed',
          replace: true,
          message:
            error instanceof Error && error.message
              ? error.message
              : mode === 'partial'
                ? 'Unable to run partial property sync.'
                : 'Unable to sync properties.',
        });
      } finally {
        this.activeSyncMode = 'none';
        this.isSyncing = this.shouldPoll();
        if (!this.isSyncing) {
          this.stopPolling();
        }
      }
    },
    syncProperties() {
      void this.runSync('full');
    },
    partialSyncProperties() {
      void this.runSync('partial');
    },
    hasDeterminateProgress() {
      return this.progress.total > 0;
    },
    progressBarStyle() {
      if (!this.hasDeterminateProgress()) {
        return '';
      }

      const current = Math.max(0, Math.min(this.progress.current, this.progress.total));
      const percent = this.progress.total > 0 ? (current / this.progress.total) * 100 : 0;

      return `width:${percent}%;`;
    },
    progressStatusText() {
      if (this.isSyncCompleteState()) {
        return 'Sync complete';
      }

      if (this.isSyncRunning()) {
        return this.activeSyncMode === 'partial' ? 'Partial Sync in progress' : 'Full Sync in progress';
      }

      return '';
    },
    progressSummaryText() {
      if (this.isSyncCompleteState()) {
        return `Sync complete. ${this.progress.current} of ${this.progress.total} properties synced`;
      }

      if (this.progress.total > 0) {
        return `${this.progress.current} of ${this.progress.total} properties synced`;
      }

      if (this.progress.message) {
        return this.progress.message;
      }

      return 'Sync in progress…';
    },
    progressCurrentItemText() {
      if (this.isSyncCompleteState()) {
        return 'All properties finished syncing successfully.';
      }

      const title = this.progress.current_property_title || '';
      const id = this.progress.current_property_id || '';

      if (title && id) {
        return `Current: ${title} (${id})`;
      }

      if (id) {
        return `Current: ${id}`;
      }

      return '';
    },
    progressAriaText() {
      return this.progress.message || this.progressSummaryText();
    },
    progressValueMax() {
      return this.progress.total > 0 ? this.progress.total : 100;
    },
  };
}
