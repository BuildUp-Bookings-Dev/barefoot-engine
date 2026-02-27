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

const getUpdatesConfig = () => {
  const bootstrap = getBootstrapData();
  const config = bootstrap && bootstrap.updatesConfig && typeof bootstrap.updatesConfig === 'object'
    ? bootstrap.updatesConfig
    : {};

  return {
    statusEndpoint:
      typeof config.statusEndpoint === 'string' && config.statusEndpoint !== ''
        ? config.statusEndpoint
        : 'updates/status',
    checkEndpoint:
      typeof config.checkEndpoint === 'string' && config.checkEndpoint !== ''
        ? config.checkEndpoint
        : 'updates/check',
    releasesEndpoint:
      typeof config.releasesEndpoint === 'string' && config.releasesEndpoint !== ''
        ? config.releasesEndpoint
        : 'updates/releases',
    repository:
      typeof config.repository === 'string' && config.repository !== ''
        ? config.repository
        : '',
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

const formatDate = (value) => {
  if (typeof value !== 'string' || value.trim() === '') {
    return 'Unknown date';
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat(undefined, {
    year: 'numeric',
    month: 'long',
    day: '2-digit',
  }).format(date);
};

const normalizeStatus = (payload) => {
  const data = payload && payload.data && typeof payload.data === 'object' ? payload.data : {};
  const bootstrap = getBootstrapData();
  const currentVersionFallback =
    typeof bootstrap.pluginVersion === 'string' ? bootstrap.pluginVersion : '0.0.0';

  const currentVersion =
    typeof data.current_version === 'string' && data.current_version !== ''
      ? data.current_version
      : currentVersionFallback;

  const latestVersion =
    typeof data.latest_version === 'string' && data.latest_version !== ''
      ? data.latest_version
      : currentVersion;

  const hasUpdate = Boolean(data.has_update);
  const isLatest = !hasUpdate;

  return {
    currentVersion,
    latestVersion,
    hasUpdate,
    isLatest,
    summary:
      typeof data.summary === 'string' && data.summary !== ''
        ? data.summary
        : isLatest
          ? 'You are running the latest version of Barefoot Engine.'
          : 'A newer version is available.',
    lastChecked:
      typeof data.last_checked_human === 'string' && data.last_checked_human !== ''
        ? data.last_checked_human
        : 'Not checked yet',
  };
};

const normalizeRelease = (release) => {
  const fallbackTag =
    release && typeof release.tag_name === 'string' && release.tag_name !== ''
      ? release.tag_name
      : 'unknown';

  return {
    tagName: fallbackTag,
    title:
      release && typeof release.name === 'string' && release.name !== ''
        ? release.name
        : fallbackTag,
    publishedAt:
      release && typeof release.published_human === 'string' && release.published_human !== ''
        ? release.published_human
        : formatDate(release && typeof release.published_at === 'string' ? release.published_at : ''),
    url:
      release && typeof release.url === 'string' && release.url !== ''
        ? release.url
        : '',
    prerelease: Boolean(release && release.is_prerelease),
    bodyExcerpt:
      release && typeof release.body_excerpt === 'string' && release.body_excerpt !== ''
        ? release.body_excerpt
        : 'No release notes provided.',
  };
};

export default function updatesTab() {
  return {
    status: {
      currentVersion: '',
      latestVersion: '',
      hasUpdate: false,
      isLatest: true,
      summary: '',
      lastChecked: 'Not checked yet',
    },
    releases: [],
    isLoadingStatus: false,
    isLoadingReleases: false,
    isChecking: false,
    updatesConfig: getUpdatesConfig(),

    init() {
      this.refreshAll();
    },

    badgeClass() {
      return this.status.isLatest ? 'be-badge be-badge-success' : 'be-badge be-badge-primary';
    },

    badgeText() {
      return this.status.isLatest ? 'Latest' : 'Update available';
    },

    async refreshAll() {
      await Promise.all([this.loadStatus(), this.loadReleases()]);
    },

    async loadStatus() {
      this.isLoadingStatus = true;

      try {
        const response = await requestJson(this.updatesConfig.statusEndpoint, {
          method: 'GET',
        });
        this.status = normalizeStatus(response);
      } catch (error) {
        dispatchAlert({
          variant: 'error',
          title: 'Updates status failed',
          replace: true,
          message:
            error instanceof Error && error.message
              ? error.message
              : 'Unable to load update status.',
        });
      } finally {
        this.isLoadingStatus = false;
      }
    },

    async loadReleases() {
      this.isLoadingReleases = true;

      try {
        const response = await requestJson(this.updatesConfig.releasesEndpoint, {
          method: 'GET',
        });
        const data = response && response.data && typeof response.data === 'object' ? response.data : {};
        const releases = Array.isArray(data.releases) ? data.releases : [];
        this.releases = releases.map((release) => normalizeRelease(release));
      } catch (error) {
        dispatchAlert({
          variant: 'error',
          title: 'Release history failed',
          replace: true,
          message:
            error instanceof Error && error.message
              ? error.message
              : 'Unable to load release history.',
        });
      } finally {
        this.isLoadingReleases = false;
      }
    },

    async checkForUpdates() {
      if (this.isChecking) {
        return;
      }

      this.isChecking = true;

      try {
        const response = await requestJson(this.updatesConfig.checkEndpoint, {
          method: 'POST',
          body: JSON.stringify({}),
        });

        if (response && response.data && typeof response.data === 'object') {
          const data = response.data;

          if (data.status && typeof data.status === 'object') {
            this.status = normalizeStatus({ data: data.status });
          }

          if (Array.isArray(data.releases)) {
            this.releases = data.releases.map((release) => normalizeRelease(release));
          }
        } else {
          await this.refreshAll();
        }

        dispatchAlert({
          variant: 'success',
          title: 'Updates checked',
          replace: true,
          message:
            (response && typeof response.message === 'string' && response.message) ||
            'Update status refreshed successfully.',
        });
      } catch (error) {
        dispatchAlert({
          variant: 'error',
          title: 'Update check failed',
          replace: true,
          message:
            error instanceof Error && error.message
              ? error.message
              : 'Unable to check for updates right now.',
        });
      } finally {
        this.isChecking = false;
      }
    },
  };
}
