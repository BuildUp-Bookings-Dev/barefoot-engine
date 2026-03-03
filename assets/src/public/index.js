import { BPCalendar, BP_Calendar } from '@braudypedrosa/bp-calendar';
import listingsMapModule from '@braudypedrosa/bp-listings';
import { BPSearchWidget, BP_SearchWidget } from '@braudypedrosa/bp-search-widget';

const SEARCH_WIDGET_SELECTOR = '[data-be-search-widget]';
const LISTINGS_SELECTOR = '[data-be-listings]';
const ListingsMap = listingsMapModule?.ListingsMap ?? listingsMapModule ?? null;

if (typeof window !== 'undefined') {
  if (!window.BPCalendar) {
    window.BPCalendar = BPCalendar;
  }

  if (!window.BP_Calendar) {
    window.BP_Calendar = BP_Calendar;
  }

  if (!window.BPSearchWidget) {
    window.BPSearchWidget = BPSearchWidget;
  }

  if (!window.BP_SearchWidget) {
    window.BP_SearchWidget = BP_SearchWidget;
  }

  if (!window.ListingsMap && ListingsMap) {
    window.ListingsMap = ListingsMap;
  }
}

function bootSearchWidgets() {
  const mounts = document.querySelectorAll(SEARCH_WIDGET_SELECTOR);

  mounts.forEach((mountNode) => {
    if (!(mountNode instanceof HTMLElement) || mountNode.dataset.beSearchWidgetReady === 'true') {
      return;
    }

    const configId = mountNode.dataset.beSearchWidgetConfig;
    const config = readWidgetConfig(configId);

    if (!config) {
      return;
    }

    const { targetUrl = '', ...widgetOptions } = config;

    try {
      new BPSearchWidget(mountNode, {
        ...widgetOptions,
        onSearch: (payload) => {
          redirectToSearchResults(targetUrl, payload);
        },
      });
      mountNode.dataset.beSearchWidgetReady = 'true';
    } catch (error) {
      console.error('[barefoot-engine] Failed to initialize search widget.', error);
    }
  });
}

function bootListingsWidgets() {
  const mounts = document.querySelectorAll(LISTINGS_SELECTOR);
  const listingsApi = window.ListingsMap || ListingsMap;

  if (!listingsApi || typeof listingsApi.init !== 'function') {
    if (mounts.length > 0) {
      console.error('[barefoot-engine] ListingsMap.init is unavailable.');
    }

    return;
  }

  mounts.forEach((mountNode) => {
    if (!(mountNode instanceof HTMLElement) || mountNode.dataset.beListingsReady === 'true') {
      return;
    }

    const configId = mountNode.dataset.beListingsConfig;
    const config = readWidgetConfig(configId, 'Listings');

    if (!config) {
      return;
    }

    const allListings = Array.isArray(config.listings) ? config.listings : [];
    const searchWidgetConfig = isPlainObject(config.searchWidget) ? config.searchWidget : null;
    const fieldTypeMap = buildFieldTypeMap(searchWidgetConfig);
    const initialSearchPayload = parseSearchPayloadFromUrl(window.location.search);
    const filteredListings = hasSearchPayload(initialSearchPayload)
      ? allListings.filter((listing) => matchListing(listing, initialSearchPayload, fieldTypeMap))
      : allListings;
    const { searchWidget, ...widgetConfig } = config;

    try {
      const widget = listingsApi.init({
        container: mountNode,
        ...widgetConfig,
        listings: filteredListings,
        onListingClick: (listing) => {
          if (typeof listing?.permalink === 'string' && listing.permalink.trim() !== '') {
            window.location.assign(listing.permalink);
          }
        },
      });
      mountNode.beAllListings = allListings;
      mountNode.beListingsWidget = widget;
      mountNode.dataset.beListingsReady = 'true';
    } catch (error) {
      console.error('[barefoot-engine] Failed to initialize listings widget.', error);
    }
  });
}

function readWidgetConfig(configId, label = 'Widget') {
  if (!configId) {
    console.error(`[barefoot-engine] ${label} config id is missing.`);
    return null;
  }

  const configNode = document.getElementById(configId);
  if (!(configNode instanceof HTMLScriptElement)) {
    console.error(`[barefoot-engine] ${label} config node was not found.`);
    return null;
  }

  try {
    return JSON.parse(configNode.textContent || '{}');
  } catch (error) {
    console.error(`[barefoot-engine] ${label} config is invalid JSON.`, error);
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

function buildFieldTypeMap(searchWidgetConfig) {
  const entries = [];

  if (Array.isArray(searchWidgetConfig?.fields)) {
    entries.push(...searchWidgetConfig.fields);
  }

  if (Array.isArray(searchWidgetConfig?.filters)) {
    entries.push(...searchWidgetConfig.filters);
  }

  return new Map(
    entries
      .filter((entry) => isPlainObject(entry) && typeof entry.key === 'string')
      .map((entry) => [entry.key, typeof entry.type === 'string' ? entry.type : 'input']),
  );
}

function parseSearchPayloadFromUrl(search) {
  const params = new URLSearchParams(search);
  const payload = {
    location: '',
    checkIn: '',
    checkOut: '',
    customFields: {},
    filters: {},
  };

  params.forEach((value, rawKey) => {
    if (rawKey === 'location') {
      payload.location = value;
      return;
    }

    if (rawKey === 'check_in') {
      payload.checkIn = value;
      return;
    }

    if (rawKey === 'check_out') {
      payload.checkOut = value;
      return;
    }

    if (rawKey.startsWith('field_')) {
      appendPayloadValue(payload.customFields, rawKey.slice(6), value);
      return;
    }

    if (rawKey.startsWith('filter_')) {
      appendPayloadValue(payload.filters, rawKey.slice(7), value);
    }
  });

  return payload;
}

function appendPayloadValue(target, rawKey, value) {
  const isArrayKey = rawKey.endsWith('[]');
  const key = isArrayKey ? rawKey.slice(0, -2) : rawKey;

  if (!key) {
    return;
  }

  if (isArrayKey) {
    if (!Array.isArray(target[key])) {
      target[key] = [];
    }

    target[key].push(value);
    return;
  }

  target[key] = value;
}

function hasSearchPayload(payload) {
  return !isEmptyValue(payload.location)
    || !isEmptyValue(payload.checkIn)
    || !isEmptyValue(payload.checkOut)
    || Object.keys(payload.customFields || {}).length > 0
    || Object.keys(payload.filters || {}).length > 0;
}

function matchListing(listing, payload, fieldTypeMap) {
  const searchData = isPlainObject(listing?.searchData) ? listing.searchData : {};
  const fieldValues = isPlainObject(searchData.fields) ? searchData.fields : {};
  const filterValues = isPlainObject(searchData.filters) ? searchData.filters : {};

  if (!matchesSubstring(searchData.location, payload.location)) {
    return false;
  }

  if (
    !isEmptyValue(payload.checkIn)
    && !isEmptyValue(payload.checkOut)
    && !matchesAvailability(searchData.availability, payload.checkIn, payload.checkOut)
  ) {
    return false;
  }

  for (const [key, value] of Object.entries(payload.customFields || {})) {
    if (!matchValue(key, fieldTypeMap.get(key), fieldValues[key], value)) {
      return false;
    }
  }

  for (const [key, value] of Object.entries(payload.filters || {})) {
    if (!matchValue(key, fieldTypeMap.get(key), filterValues[key], value)) {
      return false;
    }
  }

  return true;
}

function matchValue(key, type, listingValue, submittedValue) {
  if (type === 'checkbox') {
    return matchesAllChoices(listingValue, submittedValue);
  }

  if (type === 'counter') {
    return matchesNumericAtLeast(listingValue, submittedValue);
  }

  if (type === 'select' || type === 'radio') {
    if (isGuestKey(key) && hasNumericValue(listingValue) && hasNumericValue(submittedValue)) {
      return matchesNumericAtLeast(listingValue, submittedValue);
    }

    return matchesExact(listingValue, submittedValue);
  }

  if (Array.isArray(submittedValue)) {
    return matchesAllChoices(listingValue, submittedValue);
  }

  if (isGuestKey(key) && hasNumericValue(listingValue) && hasNumericValue(submittedValue)) {
    return matchesNumericAtLeast(listingValue, submittedValue);
  }

  return matchesSubstring(listingValue, submittedValue);
}

function matchesSubstring(haystackValue, needleValue) {
  const needle = normalizeString(needleValue);
  if (!needle) {
    return true;
  }

  return normalizeStringArray(haystackValue).some((value) => value.includes(needle));
}

function matchesExact(haystackValue, needleValue) {
  const needle = normalizeString(needleValue);
  if (!needle) {
    return true;
  }

  return normalizeStringArray(haystackValue).some((value) => value === needle);
}

function matchesAllChoices(haystackValue, needleValues) {
  const selectedValues = normalizeStringArray(needleValues);
  if (selectedValues.length === 0) {
    return true;
  }

  const availableValues = new Set(normalizeStringArray(haystackValue));

  return selectedValues.every((value) => availableValues.has(value));
}

function matchesNumericAtLeast(haystackValue, needleValue) {
  const numericHaystack = extractNumericValue(haystackValue);
  const numericNeedle = extractNumericValue(needleValue);

  if (!Number.isFinite(numericNeedle)) {
    return true;
  }

  if (!Number.isFinite(numericHaystack)) {
    return false;
  }

  return numericHaystack >= numericNeedle;
}

function matchesAvailability(availability, checkIn, checkOut) {
  if (!isValidDateString(checkIn) || !isValidDateString(checkOut)) {
    return true;
  }

  if (!Array.isArray(availability) || availability.length === 0) {
    return true;
  }

  return availability.some(
    (windowRange) => isPlainObject(windowRange)
      && isValidDateString(windowRange.start)
      && isValidDateString(windowRange.end)
      && windowRange.start <= checkIn
      && windowRange.end >= checkOut,
  );
}

function isValidDateString(value) {
  return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value);
}

function normalizeString(value) {
  if (value === null || value === undefined) {
    return '';
  }

  return String(value).trim().toLowerCase();
}

function normalizeStringArray(value) {
  return toArray(value)
    .map((entry) => normalizeString(entry))
    .filter(Boolean);
}

function toArray(value) {
  if (Array.isArray(value)) {
    return value;
  }

  if (value === null || value === undefined) {
    return [];
  }

  return [value];
}

function extractNumericValue(value) {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value;
  }

  if (Array.isArray(value) && value.length > 0) {
    return extractNumericValue(value[0]);
  }

  const match = String(value ?? '').match(/-?\d+(\.\d+)?/);

  return match ? Number(match[0]) : Number.NaN;
}

function hasNumericValue(value) {
  return Number.isFinite(extractNumericValue(value));
}

function isGuestKey(key) {
  return typeof key === 'string' && /(guest|occupancy)/i.test(key);
}

function isPlainObject(value) {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}

if (document.readyState === 'loading') {
  document.addEventListener(
    'DOMContentLoaded',
    () => {
      bootSearchWidgets();
      bootListingsWidgets();
    },
    { once: true },
  );
} else {
  bootSearchWidgets();
  bootListingsWidgets();
}
