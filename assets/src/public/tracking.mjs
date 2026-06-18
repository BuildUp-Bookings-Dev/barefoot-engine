const DEFAULT_CURRENCY = 'USD';
const PURCHASE_STORAGE_PREFIX = 'barefoot_engine_purchase_tracked_';

let trackingBooted = false;

export function bootBookingTracking() {
  if (trackingBooted || typeof window === 'undefined' || typeof document === 'undefined') {
    return;
  }

  trackingBooted = true;

  if (!isBookingTrackingEnabled()) {
    return;
  }

  const config = getBookingTrackingConfig();
  const dispatchOptions = buildDispatchOptions(config);
  const dataLayer = ensureDataLayer();

  if (isPlainObject(config.propertyView)) {
    pushBookingTrackingEvent(dataLayer, buildViewItemEvent(config.propertyView), dispatchOptions);
  }

  const purchaseContext = readPurchaseTrackingContext(document);
  if (purchaseContext) {
    pushBookingTrackingEvent(dataLayer, buildPurchaseEvent(purchaseContext), dispatchOptions);
  }
}

export function trackBookingBeginCheckout(context) {
  const config = getBookingTrackingConfig();
  if (!isBookingTrackingEnabled(config)) {
    return false;
  }

  return pushBookingTrackingEvent(ensureDataLayer(), buildBeginCheckoutEvent(context), buildDispatchOptions(config));
}

export function buildViewItemEvent(context = {}) {
  const item = buildPropertyItem(context);
  const value = normalizeMoney(context.value, normalizeMoney(item.price, 0));

  return {
    event: 'view_item',
    ecommerce: {
      currency: normalizeCurrency(context.currency),
      value,
      items: [item],
    },
  };
}

export function buildBeginCheckoutEvent(context = {}) {
  const totals = isPlainObject(context.totals) ? context.totals : {};
  const value = normalizeMoney(
    context.value,
    normalizeMoney(totals.grand_total, normalizeMoney(totals.total, 0)),
  );
  const price = normalizeMoney(
    context.price,
    normalizeMoney(totals.subtotal, value),
  );
  const item = buildPropertyItem({ ...context, price });

  addStayDetails(item, context);

  return {
    event: 'begin_checkout',
    ecommerce: {
      currency: normalizeCurrency(context.currency),
      value,
      items: [item],
    },
  };
}

export function buildPurchaseEvent(context = {}) {
  const payments = isPlainObject(context.payments) ? context.payments : {};
  const transactionId = sanitizeText(
    context.transactionId ?? context.transaction_id,
    '',
  );
  const value = normalizeMoney(
    context.value,
    normalizeMoney(payments.total, normalizeMoney(payments.value, 0)),
  );
  const tax = normalizeMoney(context.tax, normalizeMoney(payments.tax, 0));
  const price = normalizeMoney(
    context.price,
    normalizeMoney(payments.rent, value),
  );
  const item = buildPropertyItem({
    ...context,
    itemCategory: context.itemCategory || 'Property',
    price,
  });

  addStayDetails(item, context);

  return {
    event: 'purchase',
    ecommerce: {
      transaction_id: transactionId,
      value,
      tax,
      currency: normalizeCurrency(context.currency),
      items: [item],
    },
  };
}

export function pushBookingTrackingEvent(dataLayer, event, options = {}) {
  if (!Array.isArray(dataLayer) || !isPlainObject(event) || sanitizeText(event.event, '') === '') {
    return false;
  }

  if (event.event === 'purchase' && isDuplicatePurchase(event, options.storage)) {
    return false;
  }

  if (shouldUseDirectGoogleTag(options)) {
    return pushDirectGoogleEvent(dataLayer, event, options);
  }

  if (isPlainObject(event.ecommerce)) {
    dataLayer.push({ ecommerce: null });
  }

  dataLayer.push(event);

  return true;
}

export function getBookingTrackingConfig() {
  if (typeof window === 'undefined') {
    return {};
  }

  const bootstrap = window.BarefootEnginePublic;
  if (!isPlainObject(bootstrap) || !isPlainObject(bootstrap.tracking)) {
    return {};
  }

  return bootstrap.tracking;
}

export function isBookingTrackingEnabled(config = getBookingTrackingConfig()) {
  return Boolean(isPlainObject(config) && config.enabled === true);
}

function buildDispatchOptions(config) {
  return {
    googleTagId: sanitizeText(config.googleTagId, ''),
    debugMode: isTrackingDebugModeEnabled(config),
  };
}

function shouldUseDirectGoogleTag(options) {
  return isDirectGoogleTagId(options.googleTagId);
}

function pushDirectGoogleEvent(dataLayer, event, options) {
  const gtag = typeof options.gtag === 'function' ? options.gtag : getGtagFunction();
  const params = buildDirectGoogleEventParams(event, Boolean(options.debugMode), options.googleTagId);

  if (typeof gtag === 'function') {
    gtag('event', event.event, params);
    return true;
  }

  dataLayer.push(['event', event.event, params]);
  return true;
}

function buildDirectGoogleEventParams(event, debugMode, googleTagId = '') {
  const ecommerce = isPlainObject(event.ecommerce) ? event.ecommerce : {};
  const params = { ...ecommerce };
  const sendTo = sanitizeText(googleTagId, '');

  if (sendTo !== '') {
    params.send_to = sendTo;
  }

  if (debugMode) {
    params.debug_mode = true;
  }

  return params;
}

function getGtagFunction() {
  if (typeof window === 'undefined' || typeof window.gtag !== 'function') {
    return null;
  }

  return window.gtag;
}

function isDirectGoogleTagId(value) {
  const tagId = sanitizeText(value, '').toUpperCase();

  return tagId !== '' && !tagId.startsWith('GTM-') && /^(G|GT|AW|DC)-[A-Z0-9]+$/.test(tagId);
}

function isTrackingDebugModeEnabled(config) {
  if (isPlainObject(config) && config.debugMode === true) {
    return true;
  }

  if (typeof window === 'undefined' || !window.location) {
    return false;
  }

  try {
    return new URLSearchParams(window.location.search).get('be_ga_debug') === '1';
  } catch (error) {
    return false;
  }
}

function readPurchaseTrackingContext(root) {
  const script = root.querySelector('[data-be-booking-purchase-tracking]');
  if (!(script instanceof HTMLScriptElement)) {
    return null;
  }

  try {
    const payload = JSON.parse(script.textContent || '{}');
    return isPlainObject(payload) ? payload : null;
  } catch (error) {
    return null;
  }
}

function ensureDataLayer() {
  window.dataLayer = Array.isArray(window.dataLayer) ? window.dataLayer : [];

  return window.dataLayer;
}

function buildPropertyItem(context) {
  const summary = isPlainObject(context.propertySummary) ? context.propertySummary : {};
  const propertyId = sanitizeText(
    context.propertyId ?? context.property_id ?? summary.propertyId ?? summary.property_id,
    'property',
  );
  const propertyName = sanitizeText(
    context.itemName ?? context.item_name ?? summary.title ?? summary.name,
    'Property',
  );
  const item = {
    item_id: propertyId,
    item_name: propertyName,
  };
  const category = sanitizeText(context.itemCategory ?? context.item_category, '');

  if (category !== '') {
    item.item_category = category;
  }

  item.price = normalizeMoney(context.price, 0);
  item.quantity = 1;

  return item;
}

function addStayDetails(item, context) {
  const checkIn = sanitizeDate(context.checkIn ?? context.check_in);
  const checkOut = sanitizeDate(context.checkOut ?? context.check_out);
  const nights = calculateNights(checkIn, checkOut);
  const guests = normalizePositiveInteger(context.guests ?? context.number_of_guests, 0);

  if (checkIn !== '') {
    item.check_in_date = checkIn;
  }

  if (checkOut !== '') {
    item.check_out_date = checkOut;
  }

  if (nights > 0) {
    item.number_of_nights = nights;
  }

  if (guests > 0) {
    item.number_of_guests = guests;
  }
}

function isDuplicatePurchase(event, storage) {
  const transactionId = sanitizeText(event.ecommerce?.transaction_id, '');
  if (transactionId === '') {
    return false;
  }

  const storageKey = PURCHASE_STORAGE_PREFIX + transactionId;
  const resolvedStorage = storage || getPurchaseStorage();
  if (!resolvedStorage) {
    return false;
  }

  try {
    if (resolvedStorage.getItem(storageKey)) {
      return true;
    }

    resolvedStorage.setItem(storageKey, String(Date.now()));
  } catch (error) {
    return false;
  }

  return false;
}

function getPurchaseStorage() {
  if (typeof window === 'undefined') {
    return null;
  }

  try {
    return window.localStorage || window.sessionStorage || null;
  } catch (error) {
    return null;
  }
}

function calculateNights(checkIn, checkOut) {
  if (checkIn === '' || checkOut === '') {
    return 0;
  }

  const start = new Date(`${checkIn}T00:00:00Z`);
  const end = new Date(`${checkOut}T00:00:00Z`);
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
    return 0;
  }

  const nights = Math.round((end.getTime() - start.getTime()) / 86400000);

  return nights > 0 ? nights : 0;
}

function normalizeCurrency(value) {
  const currency = sanitizeText(value, DEFAULT_CURRENCY).toUpperCase();

  return /^[A-Z]{3}$/.test(currency) ? currency : DEFAULT_CURRENCY;
}

function normalizeMoney(value, fallback = 0) {
  const numeric = Number(value);
  const resolved = Number.isFinite(numeric) ? numeric : Number(fallback);
  if (!Number.isFinite(resolved)) {
    return 0;
  }

  return Number(resolved.toFixed(2));
}

function normalizePositiveInteger(value, fallback = 0) {
  const numeric = Number(value);
  if (!Number.isFinite(numeric)) {
    return fallback;
  }

  return Math.max(0, Math.round(numeric));
}

function sanitizeDate(value) {
  const text = sanitizeText(value, '');

  return /^\d{4}-\d{2}-\d{2}$/.test(text) ? text : '';
}

function sanitizeText(value, fallback = '') {
  if (typeof value === 'string') {
    const normalized = value.trim();
    return normalized === '' ? fallback : normalized;
  }

  if (typeof value === 'number' && Number.isFinite(value)) {
    return String(value);
  }

  return fallback;
}

function isPlainObject(value) {
  return Boolean(value && typeof value === 'object' && !Array.isArray(value));
}
