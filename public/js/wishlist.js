/**
 * Wishlist client.
 *
 * Signed in  -> the server is the source of truth (localStorage is just a cache)
 * Signed out -> localStorage, so guests keep saving as they always did
 *
 * On the first authenticated load any guest items in localStorage are merged
 * into the account, so nothing saved before logging in is lost.
 */
(function (global) {
  "use strict";

  var KEY = "hovWishlist";
  var merged = false;

  function readLocal() {
    try {
      return JSON.parse(localStorage.getItem(KEY) || "[]");
    } catch (e) {
      return [];
    }
  }

  function writeLocal(items) {
    try {
      localStorage.setItem(KEY, JSON.stringify(items));
    } catch (e) {
      /* private mode: the server copy is still correct */
    }
  }

  function api(path, options) {
    return fetch("api/wishlist" + path, options || {}).then(function (r) {
      if (r.status === 401) { Wishlist.isSignedIn = false; return null; }
      Wishlist.isSignedIn = true;
      return r.json().then(function (body) {
        return { ok: r.ok, body: body };
      });
    });
  }

  function post(path, payload) {
    return api(path, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload || {})
    });
  }

  var Wishlist = {
    /** Whether the last API call succeeded, i.e. whether we are signed in. */
    isSignedIn: null,

    /** Local copy, for rendering without a round trip. */
    get: readLocal,

    /** Whether the signed-in customer has this product saved. */
    has: function (productId) {
      return readLocal().some(function (i) {
        return Number(i.id) === Number(productId);
      });
    },

    /**
     * Toggle a product. Resolves to true when it ended up saved.
     */
    toggle: function (product, size, color) {
      var id = Number(product.id);
      var saved = Wishlist.has(id);

      // Optimistic: the heart fills immediately, and the server catches up.
      if (saved) {
        writeLocal(readLocal().filter(function (i) { return Number(i.id) !== id; }));
      } else {
        var copy = {
          id: id,
          name: product.name || "",
          price: Number(product.price) || 0,
          image: product.image || "",
          category: product.category || "",
          size: size || "M",
          color: color || "Default"
        };
        writeLocal(readLocal().concat([copy]));
      }

      var request = saved
        ? api("/" + encodeURIComponent(id), { method: "DELETE" })
        : post("", { productId: id, size: size || "M", color: color || "Default" });

      return request
        .then(function (res) {
          if (!res) return !saved;                    // signed out: local only
          if (!res.ok) {
            // Roll back so the heart never lies about what is saved.
            if (saved) {
              writeLocal(readLocal().concat([product]));
            } else {
              writeLocal(readLocal().filter(function (i) { return Number(i.id) !== id; }));
            }
            return null;
          }
          if (!saved && res.body.items) writeLocal(res.body.items);
          return !saved;
        })
      .catch(function () { return null; });
    },

    /** Change the saved size on a product (size picker on the wishlist page). */
    updateSize: function (productId, size) {
      return api("/" + encodeURIComponent(productId), {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ size: size })
      });
    },

    /**
     * Adopt an authoritative list.
     *
     * With a signed-in customer the list is diffed against the server so the
     * diff is expressed as adds and removes rather than a blind overwrite.
     */
    replaceAll: function (items) {
      writeLocal(items || []);

      return api("").then(function (res) {
        if (!res || !res.ok) return items || [];

        var wanted = (items || []).map(function (i) { return Number(i.id); });
        var current = (res.body.items || []).map(function (i) { return Number(i.id); });

        var removals = current.filter(function (id) { return wanted.indexOf(id) === -1; });
        var additions = wanted.filter(function (id) { return current.indexOf(id) === -1 && id > 0; });

        return removals.reduce(function (chain, id) {
          return chain.then(function () { return api("/" + encodeURIComponent(id), { method: "DELETE" }); });
        }, Promise.resolve()).then(function () {
          if (!additions.length) return items || [];
          return post("/merge", { productIds: additions }).then(function (m) {
            var merged = (m && m.ok && m.body.items) || [];
            writeLocal(merged);
            return merged;
          });
        });
      });
    },

    remove: function (productId) {
      var id = Number(productId);
      writeLocal(readLocal().filter(function (i) { return Number(i.id) !== id; }));
      return api("/" + encodeURIComponent(id), { method: "DELETE" });
    },

    clear: function () {
      writeLocal([]);
      return api("", { method: "DELETE" });
    },

    /**
     * Refresh from the server and, once per page load, fold in any guest
     * items that were saved before signing in.
     */
    load: function () {
      return api("").then(function (res) {
        if (!res || !res.ok) return readLocal();

        if (!merged) {
          merged = true;
          var guest = readLocal().filter(function (i) { return Number(i.id) > 0; });

          if (!guest.length) {
            writeLocal(res.body.items || []);
            return res.body.items || [];
          }

          return post("/merge", {
            productIds: guest.map(function (i) { return Number(i.id); })
          }).then(function (mergedRes) {
            var items = (mergedRes && mergedRes.ok && mergedRes.body.items) || res.body.items || [];
            writeLocal(items);
            return items;
          });
        }

        writeLocal(res.body.items || []);
        return res.body.items || [];
      });
    }
  };

  global.Wishlist = Wishlist;
})(window);
