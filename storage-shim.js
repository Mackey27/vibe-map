(function () {
  if (window.__vibemapStorage) {
    return;
  }

  var nativeStorage = null;
  try {
    if (window.localStorage) {
      nativeStorage = window.localStorage;
    }
  } catch (error) {
    nativeStorage = null;
  }

  if (nativeStorage) {
    window.__vibemapStorage = nativeStorage;
    return;
  }

  var memoryStore = {};
  window.__vibemapStorage = {
    getItem: function (key) {
      key = String(key);
      return Object.prototype.hasOwnProperty.call(memoryStore, key) ? memoryStore[key] : null;
    },
    setItem: function (key, value) {
      memoryStore[String(key)] = String(value);
    },
    removeItem: function (key) {
      delete memoryStore[String(key)];
    },
    clear: function () {
      memoryStore = {};
    },
    key: function (index) {
      var keys = Object.keys(memoryStore);
      return keys[index] || null;
    }
  };
})();
