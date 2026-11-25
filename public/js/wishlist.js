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
      if (r.status === 401) {
        Wishlist.isSignedIn = false;
        ensureWishlistModal();
        var modalEl = document.getElementById("wishlistLoginModal");
        if (modalEl) {
          try {
            var modal = new bootstrap.Modal(modalEl);
            modal.show();
          } catch (e) {
            console.error("Failed to show login modal:", e);
            alert("Please sign in to save items to your wishlist.");
          }
        }
        return Promise.reject({ status: 401, message: "Not authenticated" });
      }
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

  // Ensure modal is injected early and persists
  function ensureWishlistModal() {
    if (!document.getElementById("wishlistLoginModal")) {
      var modalHtml = '<div class="modal fade" id="wishlistLoginModal" tabindex="-1" aria-labelledby="wishlistLoginModalLabel" aria-hidden="true">'
        + '<div class="modal-dialog modal-dialog-centered">'
        + '<div class="modal-content" style="border: none; border-radius: 16px; background: var(--ivory); box-shadow: 0 20px 60px rgba(45,15,9,0.15);">'
        + '<div class="modal-header" style="border: none; padding: 24px 24px 0; background: transparent;">'
        + '<h5 class="modal-title" id="wishlistLoginModalLabel" style="font-family: \"Cormorant Garamond\", serif; font-size: 24px; color: var(--espresso); font-weight: 600;">Sign in to save</h5>'
        + '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(30%) sepia(20%) saturate(500%) hue-rotate(340deg);"></button>'
        + '</div>'
        + '<div class="modal-body" style="padding: 0 24px 24px;">'
        + '<p class="mb-4" style="color: var(--muted); font-size: 14px; line-height: 1.6;">Sign in to save items to your wishlist and access them from any device.</p>'
        + '<div class="d-grid gap-2">'
        + '<a href="login" class="btn" data-bs-dismiss="modal" style="background: var(--espresso); color: var(--ivory); border: none; padding: 14px 24px; font-size: 14px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; border-radius: 8px; transition: all 0.2s ease;">Sign In</a>'
        + '<a href="registration" class="btn" data-bs-dismiss="modal" style="background: transparent; color: var(--brown); border: 2px solid var(--line); padding: 14px 24px; font-size: 14px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; border-radius: 8px; transition: all 0.2s ease;">Create Account</a>'
        + '</div>'
        + '</div>'
        + '</div>'
        + '</div>';
      document.body.insertAdjacentHTML('beforeend', modalHtml);
    }
  }

  // Inject modal early and persistently
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function() { ensureWishlistModal(); });
  } else {
    ensureWishlistModal();
  }

  // Also re-ensure on any wishlist interaction
  document.addEventListener("click", function(e) {
    if (e.target.closest(".wishlist-btn, .wishlist-product")) {
      ensureWishlistModal();
    }
  });

  function ensureWishlistModal() {
    if (!document.getElementById("wishlistLoginModal")) {
      var modalHtml = '<div class="modal fade" id="wishlistLoginModal" tabindex="-1" aria-labelledby="wishlistLoginModalLabel" aria-hidden="true">'
        + '<div class="modal-dialog modal-dialog-centered">'
        + '<div class="modal-content" style="border: none; border-radius: 16px; background: var(--ivory); box-shadow: 0 20px 60px rgba(45,15,9,0.15);">'
        + '<div class="modal-header" style="border: none; padding: 24px 24px 0; background: transparent;">'
        + '<h5 class="modal-title" id="wishlistLoginModalLabel" style="font-family: \"Cormorant Garamond\", serif; font-size: 24px; color: var(--espresso); font-weight: 600;">Sign in to save</h5>'
        + '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(30%) sepia(20%) saturate(500%) hue-rotate(340deg);"></button>'
        + '</div>'
        + '<div class="modal-body" style="padding: 0 24px 24px;">'
        + '<p class="mb-4" style="color: var(--muted); font-size: 14px; line-height: 1.6;">Sign in to save items to your wishlist and access them from any device.</p>'
        + '<div class="d-grid gap-2">'
        + '<a href="login" class="btn" data-bs-dismiss="modal" style="background: var(--espresso); color: var(--ivory); border: none; padding: 14px 24px; font-size: 14px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; border-radius: 8px; transition: all 0.2s ease;">Sign In</a>'
        + '<a href="registration" class="btn" data-bs-dismiss="modal" style="background: transparent; color: var(--brown); border: 2px solid var(--line); padding: 14px 24px; font-size: 14px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; border-radius: 8px; transition: all 0.2s ease;">Create Account</a>'
        + '</div>'
        + '</div>'
        + '</div>'
        + '</div>';
      document.body.insertAdjacentHTML('beforeend', modalHtml);
    }
  }

  // Inject modal early and persistently
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function() { ensureWishlistModal(); });
  } else {
    ensureWishlistModal();
  }

  // Also re-ensure on any wishlist interaction
  document.addEventListener("click", function(e) {
    if (e.target.closest(".wishlist-btn, .wishlist-product")) {
      ensureWishlistModal();
    }
  });

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
      if (r.status === 401) {
        Wishlist.isSignedIn = false;
        ensureWishlistModal();
        var modalEl = document.getElementById("wishlistLoginModal");
        if (modalEl) {
          try {
            var modal = new bootstrap.Modal(modalEl);
            modal.show();
          } catch (e) {
            console.error("Failed to show login modal:", e);
            alert("Please sign in to save items to your wishlist.");
          }
        }
        return Promise.reject({ status: 401, message: "Not authenticated" });
      }
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

      console.log("[Wishlist] toggle called, isSignedIn:", Wishlist.isSignedIn, "saved:", saved);

      // SYNCHRONOUS GUARD: Block immediately if not signed in
      if (!Wishlist.isSignedIn) {
        console.log("[Wishlist] Not signed in, blocking request and showing login modal");
        ensureWishlistModal();
        var modalEl = document.getElementById("wishlistLoginModal");
        if (!modalEl) {
          console.error("Wishlist login modal not found in DOM - forcing injection");
          ensureWishlistModal();
          modalEl = document.getElementById("wishlistLoginModal");
        }
        
        if (modalEl) {
          try {
            var modal = new bootstrap.Modal(modalEl);
            modal.show();
          } catch (e) {
            console.error("Failed to show login modal:", e);
            alert("Please sign in to save items to your wishlist.");
          }
        } else {
          console.error("Wishlist login modal not found in DOM after injection");
          alert("Please sign in to save items to your wishlist.");
        }
        
        // BLOCK: Return rejected promise to prevent any further processing
        return Promise.reject(new Error("Not authenticated"));
      }

      // Optimistic: the heart fills immediately, and the server catches up.
      var saved = Wishlist.has(id);

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
          if (!res) return !saved;
          if (!res.ok) {
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
