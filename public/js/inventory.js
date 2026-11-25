/**
 * Stock-aware purchasing.
 *
 * Fetches each product's variants once, disables the options that are out of
 * stock, and disables Add to Bag when the chosen combination is unavailable.
 *
 * Advisory by design: stock can change between this fetch and the customer
 * pressing pay, so the order endpoint re-checks inside a transaction and
 * refuses the order there.
 */
(function (global) {
  "use strict";

  var cache = {};

  /**
   * "Ivory|M" -> { stock, inStock }
   */
  function index(variants) {
    var map = {};
    (variants || []).forEach(function (v) {
      map[String(v.colour || "").trim().toLowerCase() + "|" + String(v.size || "").trim().toLowerCase()] = v;
    });
    return map;
  }

  function key(colour, size) {
    return String(colour || "").trim().toLowerCase() + "|" + String(size || "").trim().toLowerCase();
  }

  // Expose globally for inline scripts
  global.Key = key;

  var Inventory = {
    /** Variants for a product, cached per page load. */
    load: function (productId) {
      if (cache[productId]) {
        return cache[productId];
      }

      cache[productId] = fetch("api/inventory?productId=" + encodeURIComponent(productId), {
        headers: { Accept: "application/json" }
      })
        .then(function (r) {
          if (!r.ok) {
            // Don't silently fail - let the caller handle the error
            var err = new Error("HTTP " + r.status);
            err.status = r.status;
            throw err;
          }
          return r.json();
        })
        .then(function (data) {
          if (!data || !data.success) {
            throw new Error("API returned error: " + (data?.error || "unknown"));
          }
          var map = index(data.variants);
          cache[productId] = map;
          return map;
        })
        .catch(function (err) {
          // Clear any stale cache on error
          delete cache[productId];
          throw err;
        });

      return cache[productId];
    },

    /** How many are left of one combination, or null when unknown. */
    stockOf: function (map, colour, size) {
      var entry = map[key(colour, size)];
      return entry ? entry.stock : null;
    },

    isAvailable: function (map, colour, size, qty) {
      var entry = map[key(colour, size)];
      if (!entry) return false;
      return entry.isInStock && entry.stock >= (qty || 1);
    },

    /** Forget cached stock, e.g. after returning from another tab. */
    clear: function () { cache = {}; }
  };

  global.Inventory = Inventory;
})(window);
