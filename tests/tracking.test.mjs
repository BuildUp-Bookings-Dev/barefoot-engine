import assert from 'node:assert/strict';
import test from 'node:test';

import {
  buildBeginCheckoutEvent,
  buildPurchaseEvent,
  buildViewItemEvent,
  pushBookingTrackingEvent,
} from '../assets/src/public/tracking.mjs';

const propertySummary = {
  propertyId: 'CABIN-123',
  title: 'Smoky Mountain Hideaway',
};

test('builds a GA4 view_item payload from property summary data', () => {
  assert.deepEqual(buildViewItemEvent({
    propertyId: 'CABIN-123',
    propertySummary,
  }), {
    event: 'view_item',
    ecommerce: {
      currency: 'USD',
      value: 0,
      items: [
        {
          item_id: 'CABIN-123',
          item_name: 'Smoky Mountain Hideaway',
          price: 0,
          quantity: 1,
        },
      ],
    },
  });
});

test('builds a GA4 begin_checkout payload with stay details and totals', () => {
  assert.deepEqual(buildBeginCheckoutEvent({
    propertyId: 'CABIN-123',
    propertySummary,
    checkIn: '2026-08-14',
    checkOut: '2026-08-19',
    guests: 4,
    totals: {
      subtotal: 1200,
      grand_total: 1280,
    },
  }), {
    event: 'begin_checkout',
    ecommerce: {
      currency: 'USD',
      value: 1280,
      items: [
        {
          item_id: 'CABIN-123',
          item_name: 'Smoky Mountain Hideaway',
          price: 1200,
          quantity: 1,
          check_in_date: '2026-08-14',
          check_out_date: '2026-08-19',
          number_of_nights: 5,
          number_of_guests: 4,
        },
      ],
    },
  });
});

test('builds a GA4 purchase payload with reservation and payment context', () => {
  assert.deepEqual(buildPurchaseEvent({
    transactionId: 'RES-2026-00845',
    propertyId: 'CABIN-123',
    propertySummary,
    checkIn: '2026-08-14',
    checkOut: '2026-08-19',
    guests: 4,
    payments: {
      rent: 1200,
      tax: 95,
      total: 1335,
    },
  }), {
    event: 'purchase',
    ecommerce: {
      transaction_id: 'RES-2026-00845',
      value: 1335,
      tax: 95,
      currency: 'USD',
      items: [
        {
          item_id: 'CABIN-123',
          item_name: 'Smoky Mountain Hideaway',
          item_category: 'Property',
          price: 1200,
          quantity: 1,
          check_in_date: '2026-08-14',
          check_out_date: '2026-08-19',
          number_of_nights: 5,
          number_of_guests: 4,
        },
      ],
    },
  });
});

test('pushes ecommerce null before each ecommerce event and guards duplicate purchases', () => {
  const dataLayer = [];
  const storage = new Map();
  const storageAdapter = {
    getItem: (key) => storage.get(key) ?? null,
    setItem: (key, value) => storage.set(key, value),
  };
  const purchaseEvent = buildPurchaseEvent({
    transactionId: 'RES-2026-00845',
    propertyId: 'CABIN-123',
    propertySummary,
    payments: {
      rent: 1200,
      tax: 95,
      total: 1335,
    },
  });

  assert.equal(pushBookingTrackingEvent(dataLayer, purchaseEvent, { storage: storageAdapter }), true);
  assert.equal(pushBookingTrackingEvent(dataLayer, purchaseEvent, { storage: storageAdapter }), false);

  assert.deepEqual(dataLayer, [
    { ecommerce: null },
    purchaseEvent,
  ]);
});

test('sends direct GA4 gtag events for measurement IDs', () => {
  const dataLayer = [];
  const gtagCalls = [];
  const viewItemEvent = buildViewItemEvent({
    propertyId: 'CABIN-123',
    propertySummary,
  });

  assert.equal(pushBookingTrackingEvent(dataLayer, viewItemEvent, {
    googleTagId: 'G-9G8WSJYPC0',
    debugMode: true,
    gtag: (...args) => {
      gtagCalls.push(args);
    },
  }), true);

  assert.deepEqual(dataLayer, []);
  assert.deepEqual(gtagCalls, [
    [
      'event',
      'view_item',
      {
        currency: 'USD',
        value: 0,
        items: [
          {
            item_id: 'CABIN-123',
            item_name: 'Smoky Mountain Hideaway',
            price: 0,
            quantity: 1,
          },
        ],
        send_to: 'G-9G8WSJYPC0',
        debug_mode: true,
      },
    ],
  ]);
});
