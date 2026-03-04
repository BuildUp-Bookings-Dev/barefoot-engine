import '../../../node_modules/@braudypedrosa/bp-listings/src/bp-listings.scss';
import listingsSource from '../../../node_modules/@braudypedrosa/bp-listings/src/bp-listings.js?raw';

let listingsMap = null;

if (typeof window !== 'undefined') {
  if (window.ListingsMap && typeof window.ListingsMap.init === 'function') {
    listingsMap = window.ListingsMap;
  } else {
    const runtimeSource = listingsSource.replace(/^\s*import\s+['"].*?;\s*$/m, '');
    const bootstrap = new Function(`${runtimeSource}\nreturn globalThis.ListingsMap || window.ListingsMap || null;`);

    listingsMap = bootstrap.call(window);

    if (listingsMap && !window.ListingsMap) {
      window.ListingsMap = listingsMap;
    }
  }
}

export default listingsMap;
