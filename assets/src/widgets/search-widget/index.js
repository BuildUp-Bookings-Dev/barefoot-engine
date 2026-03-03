import { BPSearchWidget } from '@braudypedrosa/bp-search-widget';
import '@braudypedrosa/bp-search-widget/styles';
import '@braudypedrosa/bp-calendar/styles';

const MOUNT_SELECTOR = '[data-be-search-widget]';

function bootSearchWidgets() {
  const mounts = document.querySelectorAll(MOUNT_SELECTOR);

  mounts.forEach((mountNode) => {
    if (!(mountNode instanceof HTMLElement)) {
      return;
    }

    const configId = mountNode.dataset.beSearchWidgetConfig;
    const config = readConfig(configId);

    if (!config) {
      return;
    }

    try {
      const { targetUrl, ...widgetOptions } = config;

      new BPSearchWidget(mountNode, {
        ...widgetOptions,
        onSearch: (payload) => {
          redirectToSearchResults(targetUrl, payload);
        },
      });
    } catch (error) {
      console.error('[barefoot-engine] Failed to initialize search widget.', error);
    }
  });
}

function readConfig(configId) {
  if (!configId) {
    console.error('[barefoot-engine] Search widget config id is missing.');
    return null;
  }

  const configNode = document.getElementById(configId);
  if (!(configNode instanceof HTMLScriptElement)) {
    console.error('[barefoot-engine] Search widget config node was not found.');
    return null;
  }

  try {
    return JSON.parse(configNode.textContent || '{}');
  } catch (error) {
    console.error('[barefoot-engine] Search widget config is invalid JSON.', error);
    return null;
  }
}

function redirectToSearchResults(targetUrl, payload) {
  const url = new URL(targetUrl || window.location.href, window.location.origin);
  const params = new URLSearchParams(url.search);

  clearManagedParams(params, payload);

  appendQueryValue(params, 'location', payload?.location);
  appendQueryValue(params, 'check_in', payload?.checkIn);
  appendQueryValue(params, 'check_out', payload?.checkOut);

  if (payload?.customFields && typeof payload.customFields === 'object') {
    Object.entries(payload.customFields).forEach(([key, value]) => {
      appendQueryValue(params, `field_${key}`, value);
    });
  }

  if (payload?.filters && typeof payload.filters === 'object') {
    Object.entries(payload.filters).forEach(([key, value]) => {
      appendQueryValue(params, `filter_${key}`, value);
    });
  }

  url.search = params.toString();
  window.location.assign(url.toString());
}

function clearManagedParams(params, payload) {
  params.delete('location');
  params.delete('check_in');
  params.delete('check_out');

  if (payload?.customFields && typeof payload.customFields === 'object') {
    Object.keys(payload.customFields).forEach((key) => {
      params.delete(`field_${key}`);
      params.delete(`field_${key}[]`);
    });
  }

  if (payload?.filters && typeof payload.filters === 'object') {
    Object.keys(payload.filters).forEach((key) => {
      params.delete(`filter_${key}`);
      params.delete(`filter_${key}[]`);
    });
  }
}

function appendQueryValue(params, key, value) {
  if (Array.isArray(value)) {
    value.forEach((item) => {
      if (isEmptyValue(item)) {
        return;
      }

      params.append(`${key}[]`, String(item));
    });

    return;
  }

  if (isEmptyValue(value)) {
    return;
  }

  params.set(key, String(value));
}

function isEmptyValue(value) {
  if (value === null || value === undefined) {
    return true;
  }

  if (typeof value === 'string') {
    return value.trim() === '';
  }

  return false;
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', bootSearchWidgets, { once: true });
} else {
  bootSearchWidgets();
}
